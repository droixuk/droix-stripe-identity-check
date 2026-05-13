<?php
/**
 * Checkout integration class.
 *
 * Handles auto-verification when customer ships to different address.
 *
 * @package    DSIC
 * @subpackage DSIC/public
 * @since      1.6.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Checkout
 *
 * @since 1.6.0
 */
class DSIC_Checkout {

	/**
	 * Settings instance.
	 *
	 * @since 1.6.0
	 * @var DSIC_Settings
	 */
	private DSIC_Settings $settings;

	/**
	 * Whether auto-verification is enabled.
	 *
	 * @since 1.6.0
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Constructor.
	 *
	 * @since 1.6.0
	 */
	public function __construct() {
		$this->settings = new DSIC_Settings();
		$auto_verify    = $this->settings->get_auto_verify_settings();
		$this->enabled  = $auto_verify['enabled'];

		// Only initialize if feature is enabled.
		if ( ! $this->enabled ) {
			return;
		}

		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	private function init_hooks(): void {
		// CRITICAL: Tell WooCommerce that on-hold orders pending verification ARE paid.
		// This prevents payment gateway webhooks from trying to "complete" the payment again.
		// Without this, Stripe webhook sees is_paid()=false and calls payment_complete() again.
		add_filter( 'woocommerce_order_is_paid_statuses', array( $this, 'add_onhold_to_paid_statuses' ) );

		// Enqueue checkout assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_assets' ) );

		// Classic checkout: Mark order for verification (before payment).
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'maybe_mark_for_verification' ), 10, 3 );

		// Block checkout support.
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'maybe_mark_for_verification_block' ) );

		// Hook into payment complete - this fires after payment but we can still change status.
		add_action( 'woocommerce_payment_complete', array( $this, 'maybe_trigger_verification_after_payment' ), 20 );

		// Multiple fallback hooks to catch status changes from various sources (payment gateways, webhooks, etc).
		add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_enforce_on_hold_status' ), 999, 4 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'enforce_on_hold_for_processing' ), 999, 2 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'enforce_on_hold_for_completed' ), 999, 2 );

		// ==========================================================================
		// UNIVERSAL GATEWAY SUPPORT
		// ==========================================================================
		// WooCommerce core filter - applies to ALL gateways that use payment_complete().
		// This catches: PayPal, Affirm, Afterpay, Klarna, and most well-behaved gateways.
		// Reference: https://github.com/woocommerce/woocommerce-paypal-payments/issues/399
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'filter_payment_complete_order_status' ), 999, 3 );

		// ==========================================================================
		// STRIPE-SPECIFIC HOOKS
		// ==========================================================================
		// These are critical because Stripe webhooks bypass WooCommerce's payment_complete()
		// method and directly call update_status(). See GitHub Issue #601.
		// https://github.com/woocommerce/woocommerce-gateway-stripe/issues/601

		// Payment Plugins for Stripe (woo-stripe-payment) - fires after their payment_complete.
		add_action( 'wc_stripe_order_payment_complete', array( $this, 'enforce_on_hold_after_stripe' ), 999, 2 );

		// Payment Plugins for Stripe - filter order status before it's set.
		add_filter( 'wc_stripe_payment_complete_order_status', array( $this, 'filter_gateway_order_status' ), 999, 2 );

		// Official WooCommerce Stripe Gateway - filter order status.
		add_filter( 'woocommerce_stripe_process_payment_order_status', array( $this, 'filter_gateway_order_status' ), 999, 2 );

		// ==========================================================================
		// OTHER GATEWAY-SPECIFIC HOOKS (for gateways with their own filters)
		// ==========================================================================
		// These follow the pattern: woocommerce_GATEWAYID_process_payment_order_status

		// Affirm gateway.
		add_filter( 'woocommerce_affirm_process_payment_order_status', array( $this, 'filter_gateway_order_status' ), 999, 2 );

		// Afterpay gateway.
		add_filter( 'woocommerce_afterpay_process_payment_order_status', array( $this, 'filter_gateway_order_status' ), 999, 2 );

		// Klarna Payments gateway.
		add_filter( 'woocommerce_klarna_payments_process_payment_order_status', array( $this, 'filter_gateway_order_status' ), 999, 2 );

		// PayPal gateways (ppcp = PayPal Commerce Platform).
		add_filter( 'woocommerce_ppcp-gateway_process_payment_order_status', array( $this, 'filter_gateway_order_status' ), 999, 2 );
		add_filter( 'woocommerce_ppcp-credit-card-gateway_process_payment_order_status', array( $this, 'filter_gateway_order_status' ), 999, 2 );

		// Thank you page notice.
		add_action( 'woocommerce_thankyou', array( $this, 'display_verification_notice' ), 5 );
	}

	/**
	 * Enqueue checkout assets.
	 *
	 * Only loads on checkout page when WooCommerce allows different shipping address.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function enqueue_checkout_assets(): void {
		// Only load on checkout page (not thank you page).
		if ( ! is_checkout() || is_order_received_page() ) {
			return;
		}

		// Check if WooCommerce allows different shipping address.
		// Only applicable when "Default to customer shipping address" is selected.
		$ship_to_destination = get_option( 'woocommerce_ship_to_destination', 'shipping' );
		if ( 'shipping' !== $ship_to_destination ) {
			return; // Feature not applicable - shipping forced to billing or billing default.
		}

		// Enqueue styles.
		wp_enqueue_style(
			'dsic-checkout',
			DSIC_PLUGIN_URL . 'assets/css/checkout.css',
			array(),
			DSIC_VERSION
		);

		// Enqueue scripts.
		wp_enqueue_script(
			'dsic-checkout',
			DSIC_PLUGIN_URL . 'assets/js/checkout.js',
			array( 'jquery' ),
			DSIC_VERSION,
			true
		);

		// Localize script with translated message.
		wp_localize_script(
			'dsic-checkout',
			'dsic_checkout',
			array(
				'enabled' => true,
				'message' => $this->get_translated_checkout_message(),
			)
		);
	}

	/**
	 * Get translated checkout message.
	 *
	 * @since 1.6.0
	 * @return string Translated message.
	 */
	private function get_translated_checkout_message(): string {
		// Use WPML translation if available.
		if ( class_exists( 'DSIC_WPML' ) && DSIC_WPML::is_multilingual() ) {
			return DSIC_WPML::get_auto_verify_checkout_message();
		}

		// Fallback to settings.
		$auto_verify = $this->settings->get_auto_verify_settings();
		return $auto_verify['checkout_message'];
	}

	/**
	 * Get translated thank you message.
	 *
	 * @since 1.6.0
	 * @return string Translated message.
	 */
	private function get_translated_thankyou_message(): string {
		// Use WPML translation if available.
		if ( class_exists( 'DSIC_WPML' ) && DSIC_WPML::is_multilingual() ) {
			return DSIC_WPML::get_auto_verify_thankyou_message();
		}

		// Fallback to settings.
		$auto_verify = $this->settings->get_auto_verify_settings();
		return $auto_verify['thankyou_message'];
	}

	/**
	 * Mark order for verification (classic checkout).
	 *
	 * Called BEFORE payment processing. Only marks the order, doesn't trigger verification yet.
	 *
	 * @since 1.6.0
	 * @param int      $order_id    Order ID.
	 * @param array    $posted_data Posted data from checkout form.
	 * @param WC_Order $order       Order object.
	 * @return void
	 */
	public function maybe_mark_for_verification( int $order_id, array $posted_data, WC_Order $order ): void {
		// Check if customer chose different shipping address.
		if ( empty( $posted_data['ship_to_different_address'] ) ) {
			return;
		}

		// Verify that addresses actually differ (not just checkbox checked).
		if ( ! $this->addresses_differ( $order ) ) {
			DSIC_Logger::debug( 'Skipping verification for order #' . $order_id . ': addresses are the same despite checkbox being checked' );
			return;
		}

		$this->mark_order_for_verification( $order );
	}

	/**
	 * Mark order for verification (block checkout).
	 *
	 * Called BEFORE payment processing. Only marks the order, doesn't trigger verification yet.
	 *
	 * @since 1.6.0
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function maybe_mark_for_verification_block( WC_Order $order ): void {
		// For block checkout, compare billing and shipping addresses.
		if ( ! $this->addresses_differ( $order ) ) {
			return;
		}

		$this->mark_order_for_verification( $order );
	}

	/**
	 * Mark order for auto-verification.
	 *
	 * Sets meta flags but doesn't trigger verification yet (payment not complete).
	 *
	 * @since 1.6.0
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	private function mark_order_for_verification( WC_Order $order ): void {
		$order_id = $order->get_id();

		// Skip for virtual-only orders (no shipping needed).
		if ( ! $order->needs_shipping_address() ) {
			DSIC_Logger::debug( 'Auto verification skipped for order #' . $order_id . ': virtual-only order' );
			return;
		}

		// Check if plugin is enabled.
		if ( ! get_option( 'dsic_enabled', false ) ) {
			DSIC_Logger::debug( 'Auto verification skipped for order #' . $order_id . ': plugin disabled' );
			return;
		}

		DSIC_Logger::info( 'Marking order #' . $order_id . ' for auto-verification (different shipping address)' );

		// Mark order for auto-verification (will be processed after payment).
		$order->update_meta_data( '_dsic_auto_verification_pending', true );
		$order->update_meta_data( '_dsic_auto_verification_reason', 'different_shipping_address' );
		$order->save();
	}

	/**
	 * Trigger verification after payment is complete.
	 *
	 * Called via woocommerce_payment_complete hook.
	 *
	 * @since 1.6.0
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function maybe_trigger_verification_after_payment( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Check if order was marked for auto-verification.
		$pending = $order->get_meta( '_dsic_auto_verification_pending' );
		if ( ! $pending ) {
			return;
		}

		DSIC_Logger::info( 'Payment complete for order #' . $order_id . ' - triggering auto-verification' );

		// Remove pending flag and set triggered flag.
		$order->delete_meta_data( '_dsic_auto_verification_pending' );
		$order->update_meta_data( '_dsic_auto_verification_triggered', true );
		$order->save();

		$this->trigger_auto_verification( $order );
	}

	/**
	 * Enforce on-hold status for orders that need verification.
	 *
	 * This is a fallback to catch orders that were set to processing
	 * after we already set them to on-hold.
	 *
	 * @since 1.6.0
	 * @param int    $order_id   Order ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 * @param WC_Order $order    Order object.
	 * @return void
	 */
	public function maybe_enforce_on_hold_status( int $order_id, string $old_status, string $new_status, WC_Order $order ): void {
		// Only act if order is going TO processing or completed.
		if ( ! in_array( $new_status, array( 'processing', 'completed' ), true ) ) {
			return;
		}

		$this->enforce_on_hold_if_needed( $order_id, $order, 'status_changed_to_' . $new_status );
	}

	/**
	 * Enforce on-hold when order transitions to processing.
	 *
	 * This hook fires specifically when status becomes processing.
	 *
	 * @since 1.6.1
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order object.
	 * @return void
	 */
	public function enforce_on_hold_for_processing( int $order_id, WC_Order $order ): void {
		$this->enforce_on_hold_if_needed( $order_id, $order, 'processing_hook' );
	}

	/**
	 * Enforce on-hold when order transitions to completed.
	 *
	 * This hook fires specifically when status becomes completed.
	 *
	 * @since 1.6.1
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order object.
	 * @return void
	 */
	public function enforce_on_hold_for_completed( int $order_id, WC_Order $order ): void {
		$this->enforce_on_hold_if_needed( $order_id, $order, 'completed_hook' );
	}

	/**
	 * Core enforcement logic - sets order back to on-hold if verification is pending.
	 *
	 * @since 1.6.1
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order object.
	 * @param string   $source   Source of the call for logging.
	 * @return void
	 */
	private function enforce_on_hold_if_needed( int $order_id, WC_Order $order, string $source ): void {
		// Prevent recursive calls.
		static $processing = array();
		if ( isset( $processing[ $order_id ] ) ) {
			return;
		}

		// Check if this order was triggered for auto-verification.
		// Refresh order object from database to ensure we have latest meta.
		$fresh_order = wc_get_order( $order_id );
		if ( ! $fresh_order ) {
			return;
		}

		$triggered = $fresh_order->get_meta( '_dsic_auto_verification_triggered', true );

		if ( ! $triggered ) {
			return;
		}

		// Only enforce while verification is actively pending.
		$verification_status = $fresh_order->get_meta( '_dsic_verification_status', true );
		if ( 'pending' !== $verification_status ) {
			DSIC_Logger::debug( 'Order #' . $order_id . ' verification is not pending (' . $verification_status . '), allowing status change (' . $source . ')' );
			return;
		}

		// Check if order is already on-hold.
		$current_status = $fresh_order->get_status();
		if ( 'on-hold' === $current_status ) {
			return; // Already on-hold, nothing to do.
		}

		// Mark as processing to prevent recursion.
		$processing[ $order_id ] = true;

		DSIC_Logger::info( 'Enforcing on-hold status for order #' . $order_id . ' (source: ' . $source . ', was: ' . $current_status . ')' );

		// Remove all our hooks temporarily to prevent infinite loop.
		remove_action( 'woocommerce_order_status_changed', array( $this, 'maybe_enforce_on_hold_status' ), 999 );
		remove_action( 'woocommerce_order_status_processing', array( $this, 'enforce_on_hold_for_processing' ), 999 );
		remove_action( 'woocommerce_order_status_completed', array( $this, 'enforce_on_hold_for_completed' ), 999 );

		$fresh_order->set_status(
			'on-hold',
			__( 'Order kept on hold: ID verification still pending.', 'droix-stripe-id-check' )
		);
		$fresh_order->save();

		// Re-add all hooks.
		add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_enforce_on_hold_status' ), 999, 4 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'enforce_on_hold_for_processing' ), 999, 2 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'enforce_on_hold_for_completed' ), 999, 2 );

		// Clear processing flag.
		unset( $processing[ $order_id ] );
	}

	/**
	 * Check if billing and shipping addresses differ.
	 *
	 * Used for block checkout where we don't have the checkbox state.
	 *
	 * Compares addresses by combining all fields and checking for content similarity,
	 * rather than strict field-by-field comparison. This prevents false positives when
	 * customers enter the same address with different field distributions.
	 *
	 * @since 1.6.0
	 * @param WC_Order $order Order object.
	 * @return bool True if addresses differ.
	 */
	private function addresses_differ( WC_Order $order ): bool {
		$billing  = $order->get_address( 'billing' );
		$shipping = $order->get_address( 'shipping' );

		// Remove email and phone from comparison (only in billing).
		unset( $billing['email'], $billing['phone'] );

		// Check if shipping address is essentially empty.
		// This handles cases where:
		// 1. Customer checked "ship to different address" but didn't fill it in
		// 2. BNPL gateway hasn't populated shipping data yet (async flow)
		// In both cases, treat as same as billing - don't trigger verification.
		$shipping_key_fields = $this->get_combined_address(
			$shipping,
			array( 'address_1', 'city', 'postcode' )
		);
		if ( empty( trim( $shipping_key_fields ) ) ) {
			DSIC_Logger::debug(
				'Order #' . $order->get_id() . ': Shipping address is empty/incomplete, treating as same as billing'
			);
			return false;
		}

		// Fields to compare (excluding company name which may differ).
		$fields_to_compare = array(
			'first_name',
			'last_name',
			'address_1',
			'address_2',
			'city',
			'state',
			'postcode',
			'country',
		);

		// First, check if critical fields match exactly (country and postcode).
		// If these differ, addresses are definitely different.
		$billing_country  = isset( $billing['country'] ) ? strtoupper( trim( $billing['country'] ) ) : '';
		$shipping_country = isset( $shipping['country'] ) ? strtoupper( trim( $shipping['country'] ) ) : '';

		if ( $billing_country !== $shipping_country ) {
			DSIC_Logger::debug( 'Addresses differ: different countries' );
			return true;
		}

		$billing_postcode  = $this->normalize_postcode( $billing['postcode'] ?? '' );
		$shipping_postcode = $this->normalize_postcode( $shipping['postcode'] ?? '' );

		if ( $billing_postcode !== $shipping_postcode ) {
			DSIC_Logger::debug( 'Addresses differ: different postcodes' );
			return true;
		}

		// Combine all address components into normalized strings.
		$billing_combined  = $this->get_combined_address( $billing, $fields_to_compare );
		$shipping_combined = $this->get_combined_address( $shipping, $fields_to_compare );

		// If combined strings are identical, addresses are the same.
		if ( $billing_combined === $shipping_combined ) {
			DSIC_Logger::debug( 'Addresses match: identical combined strings' );
			return false;
		}

		// Use similarity comparison to handle different field distributions.
		$similarity = $this->calculate_address_similarity( $billing_combined, $shipping_combined );

		// If similarity is 85% or higher, consider addresses the same.
		// This threshold allows for minor variations (middle names, abbreviations, etc.)
		// while still catching genuinely different addresses.
		if ( $similarity >= 85 ) {
			DSIC_Logger::debug( sprintf( 'Addresses match: %d%% similar', $similarity ) );
			return false;
		}

		DSIC_Logger::debug( sprintf( 'Addresses differ: only %d%% similar', $similarity ) );
		return true;
	}

	/**
	 * Normalize an address field for comparison.
	 *
	 * Removes extra whitespace, converts to lowercase, and removes common punctuation.
	 * This ensures that minor formatting differences don't trigger false positives.
	 *
	 * @since 1.6.2
	 * @param string $value Address field value.
	 * @return string Normalized value.
	 */
	private function normalize_address_field( string $value ): string {
		// Convert to lowercase.
		$normalized = strtolower( $value );

		// Remove common punctuation that doesn't affect address meaning.
		$normalized = str_replace( array( '.', ',', '-', '#' ), ' ', $normalized );

		// Normalize whitespace: trim and collapse multiple spaces to single space.
		$normalized = trim( preg_replace( '/\s+/', ' ', $normalized ) );

		return $normalized;
	}

	/**
	 * Normalize a postcode for comparison.
	 *
	 * Removes spaces and converts to uppercase for consistent comparison.
	 *
	 * @since 1.6.2
	 * @param string $postcode Postcode value.
	 * @return string Normalized postcode.
	 */
	private function normalize_postcode( string $postcode ): string {
		// Remove all whitespace and convert to uppercase.
		return strtoupper( str_replace( ' ', '', trim( $postcode ) ) );
	}

	/**
	 * Combine address fields into a single normalized string.
	 *
	 * Concatenates all address components (excluding postcode/country which are checked separately)
	 * into a single string for similarity comparison.
	 *
	 * @since 1.6.2
	 * @param array $address Address array.
	 * @param array $fields  Fields to include in combination.
	 * @return string Combined and normalized address string.
	 */
	private function get_combined_address( array $address, array $fields ): string {
		$parts = array();

		// Exclude postcode and country (already checked separately).
		$exclude = array( 'postcode', 'country' );

		foreach ( $fields as $field ) {
			if ( in_array( $field, $exclude, true ) ) {
				continue;
			}

			if ( ! empty( $address[ $field ] ) ) {
				$parts[] = $this->normalize_address_field( $address[ $field ] );
			}
		}

		// Join with space and normalize again to handle any edge cases.
		$combined = implode( ' ', $parts );
		return trim( preg_replace( '/\s+/', ' ', $combined ) );
	}

	/**
	 * Calculate similarity between two address strings.
	 *
	 * Uses word-based comparison to handle different field distributions.
	 * Returns a percentage (0-100) indicating how similar the addresses are.
	 *
	 * @since 1.6.2
	 * @param string $address1 First address string (normalized).
	 * @param string $address2 Second address string (normalized).
	 * @return int Similarity percentage (0-100).
	 */
	private function calculate_address_similarity( string $address1, string $address2 ): int {
		// Empty address check.
		if ( empty( $address1 ) || empty( $address2 ) ) {
			return 0;
		}

		// Extract words from both addresses.
		$words1 = array_filter( explode( ' ', $address1 ) );
		$words2 = array_filter( explode( ' ', $address2 ) );

		// Count total unique words.
		$all_words = array_unique( array_merge( $words1, $words2 ) );
		$total     = count( $all_words );

		if ( 0 === $total ) {
			return 0;
		}

		// Count matching words.
		$matches = count( array_intersect( $words1, $words2 ) );

		// Calculate percentage.
		// We use a weighted approach: matching words / average word count
		// This is more forgiving than requiring ALL words to match.
		$avg_word_count = ( count( $words1 ) + count( $words2 ) ) / 2;
		$similarity     = ( $matches / $avg_word_count ) * 100;

		return (int) round( $similarity );
	}

	/**
	 * Trigger auto-verification for an order.
	 *
	 * Called AFTER payment is complete (order is processing/completed).
	 *
	 * @since 1.6.0
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	private function trigger_auto_verification( WC_Order $order ): void {
		$order_id = $order->get_id();

		DSIC_Logger::info( 'Auto verification triggered for order #' . $order_id . ' (different shipping address)' );

		// Set order to on-hold (payment already processed).
		$order->set_status(
			'on-hold',
			__( 'Order placed on hold: ID verification required (different shipping address).', 'droix-stripe-id-check' )
		);
		$order->save();

		// Trigger verification request using existing order handler.
		$order_handler = new DSIC_Order_Handler();
		$result        = $order_handler->request_verification( $order_id );

		if ( is_wp_error( $result ) ) {
			DSIC_Logger::error( 'Auto verification failed for order #' . $order_id . ': ' . $result->get_error_message() );
			return;
		}

		// Get fresh order object after verification request (may have been modified).
		$order = wc_get_order( $order_id );

		// Send CRM notification with order URL.
		$order_edit_url = $this->get_order_edit_url( $order );

		/**
		 * Fires when auto-verification is triggered.
		 *
		 * @since 1.6.0
		 * @param int    $order_id       Order ID.
		 * @param string $status         Verification status ('auto_triggered').
		 * @param string $session_id     Stripe session ID (empty for auto-triggered).
		 * @param array  $details        Additional details.
		 */
		do_action(
			'dsic_crm_notification',
			$order_id,
			'auto_triggered',
			'',
			array(
				'reason'         => 'different_shipping_address',
				'order_edit_url' => $order_edit_url,
			)
		);

		DSIC_Logger::info( 'Auto verification CRM notification sent for order #' . $order_id );
	}

	/**
	 * Get order edit URL (HPOS compatible).
	 *
	 * @since 1.6.0
	 * @param WC_Order $order Order object.
	 * @return string Order edit URL.
	 */
	private function get_order_edit_url( WC_Order $order ): string {
		// Check if HPOS is enabled.
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) &&
			\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order->get_id() );
		}

		// Traditional post-based orders.
		return admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' );
	}

	/**
	 * Add 'on-hold' to paid statuses for verification orders.
	 *
	 * This prevents payment gateways from trying to "complete" payment again
	 * when they see an order is on-hold for ID verification.
	 *
	 * Without this filter, the Stripe webhook would:
	 * 1. Check $order->is_paid() - returns false (on-hold not in paid statuses)
	 * 2. Call payment_complete() which sets status to 'processing'
	 * 3. Our hooks catch it and set back to on-hold (race condition)
	 *
	 * With this filter:
	 * 1. Check $order->is_paid() - returns true (on-hold IS in paid statuses)
	 * 2. Skip payment_complete() - order stays on-hold
	 *
	 * This is safe because our orders only go on-hold AFTER payment
	 * is confirmed - the hold is for ID verification, not payment issues.
	 *
	 * @since 1.6.1
	 * @param array $statuses Array of statuses considered "paid".
	 * @return array Modified array of paid statuses.
	 */
	public function add_onhold_to_paid_statuses( array $statuses ): array {
		if ( ! in_array( 'on-hold', $statuses, true ) ) {
			$statuses[] = 'on-hold';
		}

		return $statuses;
	}

	/**
	 * Enforce on-hold status after Stripe's payment_complete fires.
	 *
	 * This hook is called by Payment Plugins for Stripe (woo-stripe-payment)
	 * after their payment_complete() method runs. Since webhooks bypass
	 * WooCommerce's standard payment_complete(), we need this Stripe-specific hook.
	 *
	 * @since 1.6.1
	 * @param \Stripe\Charge $charge Stripe charge object.
	 * @param WC_Order       $order  Order object.
	 * @return void
	 */
	public function enforce_on_hold_after_stripe( $charge, $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$order_id = $order->get_id();

		// Check if this order needs verification.
		$triggered = $order->get_meta( '_dsic_auto_verification_triggered', true );
		if ( ! $triggered ) {
			return;
		}

		// Only enforce while verification is actively pending.
		$verification_status = $order->get_meta( '_dsic_verification_status', true );
		if ( 'pending' !== $verification_status ) {
			return;
		}

		// Check current status.
		$current_status = $order->get_status();
		if ( 'on-hold' === $current_status ) {
			return;
		}

		DSIC_Logger::info( 'Stripe payment_complete hook fired - enforcing on-hold for order #' . $order_id . ' (was: ' . $current_status . ')' );

		// Set back to on-hold.
		$order->set_status(
			'on-hold',
			__( 'Order kept on hold: ID verification still pending (after Stripe webhook).', 'droix-stripe-id-check' )
		);
		$order->save();
	}

	/**
	 * Filter WooCommerce core payment_complete order status.
	 *
	 * This is the universal filter that applies to ALL gateways using WC's payment_complete().
	 * Supported gateways: PayPal, Affirm, Afterpay, Klarna, and any well-behaved gateway.
	 *
	 * @since 1.6.1
	 * @param string   $status   Order status (processing or completed).
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order object (optional, may not be passed).
	 * @return string Modified order status.
	 */
	public function filter_payment_complete_order_status( $status, $order_id, $order = null ) {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order instanceof WC_Order ) {
			return $status;
		}

		// Check if this order needs verification.
		$triggered = $order->get_meta( '_dsic_auto_verification_triggered', true );
		if ( ! $triggered ) {
			return $status;
		}

		// Only enforce while verification is actively pending.
		$verification_status = $order->get_meta( '_dsic_verification_status', true );
		if ( 'pending' !== $verification_status ) {
			return $status;
		}

		DSIC_Logger::info( 'Filtering payment_complete order status to on-hold for order #' . $order->get_id() . ' (was: ' . $status . ')' );

		return 'on-hold';
	}

	/**
	 * Filter gateway-specific order status to return on-hold for verification orders.
	 *
	 * This filter is used by multiple payment gateways that have their own status filters:
	 * - Payment Plugins for Stripe: wc_stripe_payment_complete_order_status
	 * - Official WooCommerce Stripe: woocommerce_stripe_process_payment_order_status
	 * - Affirm: woocommerce_affirm_process_payment_order_status
	 * - Afterpay: woocommerce_afterpay_process_payment_order_status
	 * - Klarna: woocommerce_klarna_payments_process_payment_order_status
	 * - PayPal: woocommerce_ppcp-gateway_process_payment_order_status
	 *
	 * @since 1.6.1
	 * @param string   $status Order status.
	 * @param WC_Order $order  Order object (may be order ID in some cases).
	 * @return string Modified order status.
	 */
	public function filter_gateway_order_status( $status, $order ) {
		// Handle case where $order might be order ID.
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order instanceof WC_Order ) {
			return $status;
		}

		// Check if this order needs verification.
		$triggered = $order->get_meta( '_dsic_auto_verification_triggered', true );
		if ( ! $triggered ) {
			return $status;
		}

		// Only enforce while verification is actively pending.
		$verification_status = $order->get_meta( '_dsic_verification_status', true );
		if ( 'pending' !== $verification_status ) {
			return $status;
		}

		$gateway = $order->get_payment_method();
		DSIC_Logger::info( 'Filtering ' . $gateway . ' order status to on-hold for order #' . $order->get_id() . ' (was: ' . $status . ')' );

		return 'on-hold';
	}

	/**
	 * Display verification notice on thank you page.
	 *
	 * @since 1.6.0
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function display_verification_notice( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Check if auto-verification was triggered for this order.
		$auto_triggered = $order->get_meta( '_dsic_auto_verification_triggered' );
		if ( ! $auto_triggered ) {
			return;
		}

		// If Radar triggered this verification, the Radar notice (priority 4) already handled it.
		if ( $order->get_meta( '_dsic_radar_verification_triggered' ) ) {
			return;
		}

		// If amount threshold triggered this verification, the threshold notice (priority 3) already handled it.
		if ( $order->get_meta( '_dsic_amount_threshold_triggered' ) ) {
			return;
		}

		$message = $this->get_translated_thankyou_message();

		?>
		<div class="dsic-thankyou-notice woocommerce-message">
			<span class="dsic-notice-icon" aria-hidden="true"></span>
			<?php echo wp_kses_post( $message ); ?>
		</div>
		<?php
	}
}
