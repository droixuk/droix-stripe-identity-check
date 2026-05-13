<?php
/**
 * Webhook handler class.
 *
 * Handles Stripe webhooks and verification endpoints.
 *
 * @package    DSIC
 * @subpackage DSIC/api
 * @since      0.0.1
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Webhook_Handler
 *
 * @since 0.0.1
 */
class DSIC_Webhook_Handler {

	/**
	 * Constructor.
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	private function init_hooks(): void {
		// WooCommerce API endpoints.
		add_action( 'woocommerce_api_dsic_initiate_verification', array( $this, 'handle_initiate_verification' ) );
		add_action( 'woocommerce_api_dsic_verification_return', array( $this, 'handle_verification_return' ) );

		// REST API webhook endpoint.
		add_action( 'rest_api_init', array( $this, 'register_webhook_endpoint' ) );
	}

	/**
	 * Register REST API webhook endpoint.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function register_webhook_endpoint(): void {
		register_rest_route(
			'dsic/v1',
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true', // Verified via signature.
			)
		);
	}

	/**
	 * Handle JIT verification initiation.
	 *
	 * Customer clicks link in email -> we create Stripe session -> redirect to Stripe.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function handle_initiate_verification(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		DSIC_Logger::info( 'Verification initiation requested for order #' . $order_id );

		// Validate order.
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			DSIC_Logger::error( 'Verification initiation failed: Invalid order #' . $order_id );
			$this->display_error_page( __( 'Invalid verification link. Please contact support.', 'droix-stripe-id-check' ), $order_id );
			exit;
		}

		// Validate token.
		$stored_token = $order->get_meta( '_dsic_verification_token' );

		if ( empty( $token ) || ! hash_equals( $stored_token, $token ) ) {
			DSIC_Logger::error( 'Verification initiation failed: Token mismatch for order #' . $order_id );
			$this->display_error_page( __( 'Invalid or expired verification link. Please contact support.', 'droix-stripe-id-check' ), $order_id );
			exit;
		}

		// Check verification status.
		$status = $order->get_meta( '_dsic_verification_status' );

		if ( 'verified' === $status ) {
			DSIC_Logger::info( 'Order #' . $order_id . ' already verified, redirecting to thank you' );
			$this->display_already_verified_page( $order );
			exit;
		}

		// Track that customer clicked the verification link.
		$order->update_meta_data( '_dsic_link_clicked', time() );
		$order->save();
		DSIC_Logger::info( 'Customer clicked verification link for order #' . $order_id );

		// Clear stats cache since link was clicked.
		if ( class_exists( 'DSIC_Stats' ) ) {
			DSIC_Stats::clear_cache();
		}

		// Check for existing valid session.
		$session_id = $order->get_meta( '_dsic_verification_session_id' );

		if ( ! empty( $session_id ) ) {
			$api     = new DSIC_Stripe_API();
			$session = $api->get_verification_session( $session_id );

			if ( ! is_wp_error( $session ) && 'requires_input' === $session['status'] && ! empty( $session['url'] ) ) {
				DSIC_Logger::info( 'Reusing existing session ' . $session_id . ' for order #' . $order_id );
				wp_redirect( $session['url'] );
				exit;
			}
		}

		// Create new verification session.
		$api    = new DSIC_Stripe_API();
		$result = $api->create_verification_session( $order_id );

		if ( is_wp_error( $result ) ) {
			DSIC_Logger::error( 'Failed to create verification session: ' . $result->get_error_message() );
			$this->display_error_page( __( 'Unable to start verification. Please try again or contact support.', 'droix-stripe-id-check' ), $order_id );
			exit;
		}

		// Store session ID.
		$order->update_meta_data( '_dsic_verification_session_id', $result['session_id'] );
		$order->save();

		DSIC_Logger::info( 'Created verification session ' . $result['session_id'] . ' for order #' . $order_id );

		// Redirect to Stripe.
		if ( ! empty( $result['url'] ) ) {
			wp_redirect( $result['url'] );
			exit;
		}

		$this->display_error_page( __( 'Verification session created but no URL returned. Please contact support.', 'droix-stripe-id-check' ), $order_id );
		exit;
	}

	/**
	 * Handle verification return from Stripe.
	 *
	 * Redirects customer to their order page where the verification status
	 * section shows the current status. This provides a better integrated
	 * experience than showing standalone pages.
	 *
	 * @since 0.0.1
	 * @since 0.4.0 Now redirects to customer order page instead of static pages.
	 * @return void
	 */
	public function handle_verification_return(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

		DSIC_Logger::info( 'Verification return for order #' . $order_id );

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			$this->display_error_page( __( 'Order not found.', 'droix-stripe-id-check' ), $order_id );
			exit;
		}

		// Load frontend class if not already loaded.
		if ( ! class_exists( 'DSIC_Frontend' ) ) {
			require_once DSIC_PLUGIN_DIR . 'public/class-dsic-frontend.php';
		}

		// Redirect to customer's order page.
		$redirect_url = DSIC_Frontend::get_customer_return_url( $order );

		DSIC_Logger::info( 'Redirecting customer to order page: ' . $redirect_url );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle Stripe webhook.
	 *
	 * @since 0.0.1
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function handle_webhook( WP_REST_Request $request ) {
		$payload    = $request->get_body();
		$sig_header = $request->get_header( 'Stripe-Signature' );

		DSIC_Logger::debug( 'Webhook received with signature: ' . substr( $sig_header ?? '', 0, 50 ) . '...' );

		// Verify signature.
		if ( ! $this->verify_webhook_signature( $payload, $sig_header ) ) {
			DSIC_Logger::error( 'Webhook signature verification failed' );
			return new WP_Error( 'invalid_signature', 'Invalid webhook signature', array( 'status' => 400 ) );
		}

		$event = json_decode( $payload, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			DSIC_Logger::error( 'Webhook JSON decode error: ' . json_last_error_msg() );
			return new WP_Error( 'invalid_json', 'Invalid JSON payload', array( 'status' => 400 ) );
		}

		$event_type = $event['type'] ?? '';
		$session    = $event['data']['object'] ?? array();

		DSIC_Logger::info( 'Webhook event: ' . $event_type . ' for session: ' . ( $session['id'] ?? 'unknown' ) );

		switch ( $event_type ) {
			case 'identity.verification_session.verified':
				$this->handle_verification_verified( $session );
				break;

			case 'identity.verification_session.requires_input':
				$this->handle_verification_failed( $session );
				break;

			case 'identity.verification_session.canceled':
				$this->handle_verification_cancelled( $session );
				break;

			case 'identity.verification_session.redacted':
				$this->handle_verification_redacted( $session );
				break;

			case 'radar.early_fraud_warning.created':
				$this->handle_early_fraud_warning_webhook( $session );
				break;

			case 'radar.early_fraud_warning.updated':
				$this->handle_early_fraud_warning_updated_webhook( $session );
				break;

			default:
				DSIC_Logger::debug( 'Unhandled webhook event type: ' . $event_type );
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * Verify Stripe webhook signature.
	 *
	 * @since 0.0.1
	 * @param string $payload    Raw request body.
	 * @param string $sig_header Stripe-Signature header.
	 * @return bool True if valid, false otherwise.
	 */
	private function verify_webhook_signature( string $payload, ?string $sig_header ): bool {
		if ( empty( $sig_header ) ) {
			return false;
		}

		// Parse signature header once — reused for each secret attempt.
		$elements = array();
		foreach ( explode( ',', $sig_header ) as $element ) {
			$parts = explode( '=', $element, 2 );
			if ( count( $parts ) === 2 ) {
				$elements[ $parts[0] ] = $parts[1];
			}
		}

		$timestamp = $elements['t'] ?? '';
		$signature = $elements['v1'] ?? '';

		if ( empty( $timestamp ) || empty( $signature ) ) {
			DSIC_Logger::error( 'Webhook signature header missing timestamp or signature' );
			return false;
		}

		// Check timestamp (5 minute tolerance).
		if ( abs( time() - (int) $timestamp ) > 300 ) {
			DSIC_Logger::error( 'Webhook timestamp too old - possible replay attack' );
			return false;
		}

		$signed_payload = $timestamp . '.' . $payload;

		// Build list of secrets to try: Identity secret first, then Radar secret
		// (only added if it differs — i.e. a separate Stripe account is configured).
		$secrets = array();

		$identity_secret = DSIC_Stripe_API::get_webhook_secret();
		if ( ! empty( $identity_secret ) ) {
			$secrets[] = $identity_secret;
		}

		$radar_secret = DSIC_Stripe_API::get_radar_webhook_secret();
		if ( ! empty( $radar_secret ) && $radar_secret !== $identity_secret ) {
			$secrets[] = $radar_secret;
		}

		if ( empty( $secrets ) ) {
			DSIC_Logger::error( 'Webhook secret not configured' );
			return false;
		}

		foreach ( $secrets as $secret ) {
			$expected = hash_hmac( 'sha256', $signed_payload, $secret );
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Handle successful verification.
	 *
	 * @since 0.0.1
	 * @param array $session Stripe session data.
	 * @return void
	 */
	private function handle_verification_verified( array $session ): void {
		$session_id = $session['id'] ?? '';
		$order_id   = $session['metadata']['order_id'] ?? 0;

		DSIC_Logger::info( 'Processing verified session ' . $session_id . ' for order #' . $order_id );

		$order = $this->get_order_by_session( $session_id, $order_id );

		if ( ! $order ) {
			DSIC_Logger::error( 'Could not find order for verified session ' . $session_id );
			return;
		}

		// Verify session matches this order to prevent cross-order verification.
		$stored_session = $order->get_meta( '_dsic_verification_session_id' );
		if ( $stored_session !== $session_id ) {
			DSIC_Logger::error(
				sprintf(
					'Session ID mismatch! Webhook session: %s, Order #%d has session: %s. Aborting verification.',
					$session_id,
					$order->get_id(),
					$stored_session
				)
			);
			return;
		}

		DSIC_Logger::info(
			sprintf(
				'Session ID verified: %s matches order #%d. Proceeding with verification.',
				$session_id,
				$order->get_id()
			)
		);

		// Update order meta.
		$order->update_meta_data( '_dsic_verification_status', 'verified' );
		$order->update_meta_data( '_dsic_verification_completed', time() );
		$order->save();

		// Build detailed order note for CS team.
		$stripe_url = $this->get_stripe_dashboard_url( $session_id );
		$note_parts = array(
			__( '✅ ID Verification PASSED', 'droix-stripe-id-check' ),
			sprintf(
				/* translators: %s: Date and time */
				__( '📅 Verified: %s', 'droix-stripe-id-check' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
			),
			sprintf(
				/* translators: %s: Customer email */
				__( '📧 Customer: %s (%s)', 'droix-stripe-id-check' ),
				$order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				$order->get_billing_email()
			),
			sprintf(
				/* translators: %s: Stripe dashboard URL */
				__( '🔗 Stripe: %s', 'droix-stripe-id-check' ),
				'<a href="' . esc_url( $stripe_url ) . '" target="_blank">' . __( 'View in Stripe Dashboard', 'droix-stripe-id-check' ) . '</a>'
			),
			__( '📋 Order is ready to be processed.', 'droix-stripe-id-check' ),
		);
		$order->add_order_note( implode( "\n", $note_parts ) );

		// Update order status to processing if on hold.
		if ( $order->has_status( 'on-hold' ) ) {
			$order->update_status( 'processing', __( 'ID verification passed.', 'droix-stripe-id-check' ) );
		}

		// Trigger emails.
		do_action( 'dsic_verification_passed', $order->get_id() );
		do_action( 'dsic_crm_notification', $order->get_id(), 'verified', $session_id, array() );

		DSIC_Logger::info( 'Verification completed for order #' . $order->get_id() );
	}

	/**
	 * Handle failed verification.
	 *
	 * @since 0.0.1
	 * @param array $session Stripe session data.
	 * @return void
	 */
	private function handle_verification_failed( array $session ): void {
		$session_id = $session['id'] ?? '';
		$order_id   = $session['metadata']['order_id'] ?? 0;
		$last_error = $session['last_error'] ?? array();

		$error_reason = $this->get_error_reason( $last_error );
		$error_code   = $last_error['code'] ?? '';

		DSIC_Logger::info( 'Processing failed session ' . $session_id . ' for order #' . $order_id . ': ' . $error_reason );

		$order = $this->get_order_by_session( $session_id, $order_id );

		if ( ! $order ) {
			DSIC_Logger::error( 'Could not find order for failed session ' . $session_id );
			return;
		}

		// Update order meta.
		$order->update_meta_data( '_dsic_verification_status', 'failed' );
		$order->update_meta_data( '_dsic_verification_completed', time() );
		$order->update_meta_data( '_dsic_verification_error_msg', $error_reason );
		$order->save();

		// Build detailed order note for CS team.
		$stripe_url = $this->get_stripe_dashboard_url( $session_id );
		$note_parts = array(
			__( '❌ ID Verification FAILED', 'droix-stripe-id-check' ),
			sprintf(
				/* translators: %s: Date and time */
				__( '📅 Failed: %s', 'droix-stripe-id-check' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
			),
			sprintf(
				/* translators: %s: Customer email */
				__( '📧 Customer: %s (%s)', 'droix-stripe-id-check' ),
				$order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				$order->get_billing_email()
			),
			sprintf(
				/* translators: %s: Error reason */
				__( '⚠️ Reason: %s', 'droix-stripe-id-check' ),
				$error_reason
			),
		);

		// Add error code if available (helpful for debugging).
		if ( ! empty( $error_code ) ) {
			$note_parts[] = sprintf(
				/* translators: %s: Error code */
				__( '🔧 Error Code: %s', 'droix-stripe-id-check' ),
				$error_code
			);
		}

		$note_parts[] = sprintf(
			/* translators: %s: Stripe dashboard URL */
			__( '🔗 Stripe: %s', 'droix-stripe-id-check' ),
			'<a href="' . esc_url( $stripe_url ) . '" target="_blank">' . __( 'View in Stripe Dashboard', 'droix-stripe-id-check' ) . '</a>'
		);
		$note_parts[] = __( '📋 Order remains on hold. Customer may retry or contact support.', 'droix-stripe-id-check' );

		$order->add_order_note( implode( "\n", $note_parts ) );

		// Ensure order is on-hold after failed verification.
		if ( ! $order->has_status( 'on-hold' ) ) {
			$order->update_status( 'on-hold', __( 'ID verification failed.', 'droix-stripe-id-check' ) );
		}

		// Trigger emails.
		do_action( 'dsic_verification_failed', $order->get_id(), $error_reason );
		do_action( 'dsic_crm_notification', $order->get_id(), 'failed', $session_id, array( 'reason' => $error_reason ) );

		DSIC_Logger::info( 'Verification failed for order #' . $order->get_id() );
	}

	/**
	 * Handle cancelled verification.
	 *
	 * @since 0.0.1
	 * @param array $session Stripe session data.
	 * @return void
	 */
	private function handle_verification_cancelled( array $session ): void {
		$session_id = $session['id'] ?? '';
		$order_id   = $session['metadata']['order_id'] ?? 0;

		DSIC_Logger::info( 'Processing cancelled session ' . $session_id . ' for order #' . $order_id );

		$order = $this->get_order_by_session( $session_id, $order_id );

		if ( ! $order ) {
			return;
		}

		// Clear verification and auto-verification state so cancelled sessions cannot keep the order on hold.
		$order->delete_meta_data( '_dsic_verification_token' );
		$order->delete_meta_data( '_dsic_verification_session_id' );
		$order->delete_meta_data( '_dsic_verification_status' );
		$order->delete_meta_data( '_dsic_auto_verification_triggered' );
		$order->delete_meta_data( '_dsic_auto_verification_pending' );
		$order->delete_meta_data( '_dsic_auto_verification_reason' );
		$order->save();

		if ( $order->has_status( 'on-hold' ) ) {
			$order->update_status( 'processing', __( 'ID verification cancelled - returning to processing.', 'droix-stripe-id-check' ) );
		}

		$order->add_order_note( __( 'ID verification session was cancelled.', 'droix-stripe-id-check' ) );
		do_action( 'dsic_verification_cancelled', $order->get_id() );
	}

	/**
	 * Handle redacted verification session.
	 *
	 * Stripe has confirmed that the verification data has been deleted.
	 *
	 * @since 0.5.6
	 * @param array $session Stripe session data.
	 * @return void
	 */
	private function handle_verification_redacted( array $session ): void {
		$session_id = $session['id'] ?? '';
		$order_id   = $session['metadata']['order_id'] ?? 0;

		DSIC_Logger::info( 'Processing redacted session ' . $session_id . ' for order #' . $order_id );

		$order = $this->get_order_by_session( $session_id, $order_id );

		if ( ! $order ) {
			DSIC_Logger::error( 'Could not find order for redacted session ' . $session_id );
			return;
		}

		// Update order meta to confirm redaction is complete.
		$order->update_meta_data( '_dsic_data_redaction_status', 'redacted' );
		$order->update_meta_data( '_dsic_data_redaction_completed', time() );
		$order->save();

		// Add order note.
		$order->add_order_note( __( 'Verification data has been permanently deleted from Stripe.', 'droix-stripe-id-check' ) );

		// Trigger email to customer confirming deletion.
		WC()->mailer();
		do_action( 'dsic_data_redaction_completed', $order->get_id() );

		DSIC_Logger::info( 'Verification data redaction confirmed for order #' . $order->get_id() );
	}

	/**
	 * Get order by session ID or order ID from metadata.
	 *
	 * @since 0.0.1
	 * @param string $session_id Stripe session ID.
	 * @param int    $order_id   Order ID from metadata.
	 * @return WC_Order|null Order or null if not found.
	 */
	private function get_order_by_session( string $session_id, int $order_id ): ?WC_Order {
		// Try by order ID from metadata first.
		if ( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( $order ) {
				$stored_session = $order->get_meta( '_dsic_verification_session_id' );

				// Verify session ID matches.
				if ( $stored_session === $session_id ) {
					return $order;
				}
			}
		}

		// Fallback: search by session ID.
		$orders = wc_get_orders(
			array(
				'meta_key'   => '_dsic_verification_session_id',
				'meta_value' => $session_id,
				'limit'      => 1,
			)
		);

		return ! empty( $orders ) ? $orders[0] : null;
	}

	/**
	 * Get Stripe dashboard URL for a verification session.
	 *
	 * @since 1.5.0
	 * @param string $session_id Stripe session ID.
	 * @return string Dashboard URL.
	 */
	/**
	 * Handle early fraud warning webhook.
	 *
	 * @since 1.8.0
	 * @param array $warning Warning data from Stripe.
	 * @return void
	 */
	private function handle_early_fraud_warning_webhook( array $warning ): void {
		// Check if early warnings are enabled.
		if ( ! get_option( 'dsic_radar_early_warnings_enabled', false ) ) {
			DSIC_Logger::debug( 'Early fraud warning received but feature is disabled' );
			return;
		}

		$charge_id = $warning['charge'] ?? '';

		if ( empty( $charge_id ) ) {
			DSIC_Logger::error( 'Early fraud warning: no charge ID in webhook data' );
			return;
		}

		DSIC_Logger::info( 'Processing early fraud warning for charge: ' . $charge_id );

		// Look up WooCommerce order by transaction_id matching charge ID.
		$orders = wc_get_orders(
			array(
				'transaction_id' => $charge_id,
				'limit'          => 1,
			)
		);

		if ( empty( $orders ) ) {
			DSIC_Logger::warning( 'Early fraud warning: no order found for charge ' . $charge_id );
			return;
		}

		$order    = $orders[0];
		$order_id = $order->get_id();

		// Skip if already verified.
		$verification_status = $order->get_meta( '_dsic_verification_status' );
		if ( 'verified' === $verification_status ) {
			DSIC_Logger::info( 'Early fraud warning: order #' . $order_id . ' already verified, adding note only' );
			$order->add_order_note(
				sprintf(
					/* translators: 1: Fraud type */
					__( 'Early Fraud Warning received from card issuer (type: %1$s) - order already verified.', 'droix-stripe-id-check' ),
					$warning['fraud_type'] ?? 'unknown'
				)
			);
			return;
		}

		// Delegate to DSIC_Radar_Check.
		if ( class_exists( 'DSIC_Radar_Check' ) ) {
			$radar_check = new DSIC_Radar_Check();
			$radar_check->handle_early_fraud_warning( $order_id, $warning );
		}
	}

	/**
	 * Handle early fraud warning updated webhook.
	 *
	 * Records the updated status/fraud_type as an order note only.
	 * Does NOT re-trigger verification (already in progress or completed).
	 *
	 * @since 1.9.3
	 * @param array $warning Warning data from Stripe.
	 * @return void
	 */
	private function handle_early_fraud_warning_updated_webhook( array $warning ): void {
		if ( ! get_option( 'dsic_radar_early_warnings_enabled', false ) ) {
			DSIC_Logger::debug( 'Early fraud warning update received but feature is disabled' );
			return;
		}

		$charge_id  = $warning['charge'] ?? '';
		$fraud_type = $warning['fraud_type'] ?? 'unknown';
		$status     = $warning['status'] ?? 'unknown';

		if ( empty( $charge_id ) ) {
			DSIC_Logger::error( 'Early fraud warning update: no charge ID in webhook data' );
			return;
		}

		DSIC_Logger::info( 'Processing early fraud warning update for charge: ' . $charge_id );

		$orders = wc_get_orders(
			array(
				'transaction_id' => $charge_id,
				'limit'          => 1,
			)
		);

		if ( empty( $orders ) ) {
			DSIC_Logger::warning( 'Early fraud warning update: no order found for charge ' . $charge_id );
			return;
		}

		$order    = $orders[0];
		$order_id = $order->get_id();

		// Update stored fraud type if changed.
		$stored_type = $order->get_meta( '_dsic_radar_early_warning_type' );
		if ( $stored_type !== $fraud_type ) {
			$order->update_meta_data( '_dsic_radar_early_warning_type', $fraud_type );
			$order->save();
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1: Fraud type, 2: Warning status */
				__( '⚠️ Early Fraud Warning Updated — Type: %1$s | Status: %2$s (no further action taken)', 'droix-stripe-id-check' ),
				$fraud_type,
				$status
			)
		);

		DSIC_Logger::info( 'Early fraud warning update noted for order #' . $order_id );
	}

	private function get_stripe_dashboard_url( string $session_id ): string {
		$test_mode = get_option( 'dsic_test_mode', true );
		$base_url  = $test_mode
			? 'https://dashboard.stripe.com/test/identity/verification-sessions/'
			: 'https://dashboard.stripe.com/identity/verification-sessions/';

		return $base_url . $session_id;
	}

	/**
	 * Get human-readable error reason.
	 *
	 * @since 0.0.1
	 * @param array $last_error Error data from Stripe.
	 * @return string Error reason.
	 */
	private function get_error_reason( array $last_error ): string {
		$code   = $last_error['code'] ?? '';
		$reason = $last_error['reason'] ?? '';

		$error_messages = array(
			// Document errors.
			'document_expired'              => __( 'The document has expired.', 'droix-stripe-id-check' ),
			'document_type_not_supported'   => __( 'The document type is not supported.', 'droix-stripe-id-check' ),
			'document_unverified_other'     => __( 'The document could not be verified.', 'droix-stripe-id-check' ),
			'document_invalid'              => __( 'The document is invalid.', 'droix-stripe-id-check' ),
			'document_fraudulent'           => __( 'The document appears to be fraudulent.', 'droix-stripe-id-check' ),
			'document_incomplete'           => __( 'The document is incomplete.', 'droix-stripe-id-check' ),
			'document_failed_copy'          => __( 'The document appears to be a copy or screenshot.', 'droix-stripe-id-check' ),
			'document_failed_greyscale'     => __( 'The document is not in color.', 'droix-stripe-id-check' ),
			'document_failed_other'         => __( 'The document failed verification checks.', 'droix-stripe-id-check' ),
			'document_not_readable'         => __( 'The document is not readable.', 'droix-stripe-id-check' ),
			'document_not_uploaded'         => __( 'The document was not uploaded.', 'droix-stripe-id-check' ),
			'document_too_large'            => __( 'The document file is too large.', 'droix-stripe-id-check' ),
			// Selfie errors.
			'selfie_document_missing_photo' => __( 'The document is missing a photo.', 'droix-stripe-id-check' ),
			'selfie_face_mismatch'          => __( 'The selfie does not match the document photo.', 'droix-stripe-id-check' ),
			'selfie_unverified_other'       => __( 'The selfie could not be verified.', 'droix-stripe-id-check' ),
			'selfie_manipulated'            => __( 'The selfie appears to be manipulated.', 'droix-stripe-id-check' ),
			'selfie_not_uploaded'           => __( 'The selfie was not uploaded.', 'droix-stripe-id-check' ),
			// ID number errors.
			'id_number_mismatch'            => __( 'The ID number does not match the document.', 'droix-stripe-id-check' ),
			'id_number_unverified_other'    => __( 'The ID number could not be verified.', 'droix-stripe-id-check' ),
			// User action errors.
			'consent_declined'              => __( 'Consent was declined.', 'droix-stripe-id-check' ),
			'abandoned'                     => __( 'The verification was abandoned.', 'droix-stripe-id-check' ),
			'under_supported_age'           => __( 'The person is under the supported age.', 'droix-stripe-id-check' ),
			// Country/region errors.
			'country_not_supported'         => __( 'The document country is not supported.', 'droix-stripe-id-check' ),
			// Manual review (admin override in Stripe dashboard).
			'requires_review'               => __( 'Manual review required - flagged by admin.', 'droix-stripe-id-check' ),
			'fraudulent'                    => __( 'Flagged as fraudulent by admin review.', 'droix-stripe-id-check' ),
		);

		if ( isset( $error_messages[ $code ] ) ) {
			return $error_messages[ $code ];
		}

		if ( ! empty( $reason ) ) {
			return ucfirst( str_replace( '_', ' ', $reason ) );
		}

		return __( 'Verification could not be completed.', 'droix-stripe-id-check' );
	}

	/**
	 * Display error page.
	 *
	 * @since 0.0.1
	 * @since 1.6.0 Added order_id parameter for support email.
	 * @param string $message  Error message.
	 * @param int    $order_id Optional order ID for support email.
	 * @return void
	 */
	private function display_error_page( string $message, int $order_id = 0 ): void {
		// Build support email link.
		$support_email = get_option( 'dsic_crm_email', get_option( 'admin_email' ) );
		$site_name     = get_bloginfo( 'name' );

		$email_subject = $order_id
			? sprintf( __( 'ID Verification Error - Order #%d', 'droix-stripe-id-check' ), $order_id )
			: __( 'ID Verification Error', 'droix-stripe-id-check' );

		$email_body = sprintf(
			/* translators: 1: Site name, 2: Order ID or N/A, 3: Error message */
			__( "Hello,\n\nI encountered an error while trying to verify my identity on %1\$s.\n\nOrder ID: %2\$s\nError: %3\$s\n\nPlease help me resolve this issue.\n\nThank you.", 'droix-stripe-id-check' ),
			$site_name,
			$order_id ? '#' . $order_id : 'N/A',
			$message
		);

		$mailto_url = sprintf(
			'mailto:%s?subject=%s&body=%s',
			rawurlencode( $support_email ),
			rawurlencode( $email_subject ),
			rawurlencode( $email_body )
		);

		$content = '<div class="dsic-error-box"><p>' . esc_html( $message ) . '</p></div>';
		$content .= '<a href="' . esc_url( $mailto_url ) . '" class="dsic-support-link">';
		$content .= esc_html__( 'Contact Support', 'droix-stripe-id-check' );
		$content .= '</a>';

		$this->display_page(
			__( 'Verification Error', 'droix-stripe-id-check' ),
			$content
		);
	}

	/**
	 * Display already verified page.
	 *
	 * @since 0.0.1
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	private function display_already_verified_page( WC_Order $order ): void {
		$message = sprintf(
			/* translators: %s: Order number */
			__( 'Your identity has already been verified for order #%s. No further action is required.', 'droix-stripe-id-check' ),
			$order->get_order_number()
		);

		$this->display_page(
			__( 'Already Verified', 'droix-stripe-id-check' ),
			'<div class="dsic-success-box"><p>' . esc_html( $message ) . '</p></div>'
		);
	}

	/**
	 * Display success page.
	 *
	 * @since 0.0.1
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	private function display_success_page( WC_Order $order ): void {
		$message = sprintf(
			/* translators: %s: Order number */
			__( 'Thank you! Your identity has been successfully verified for order #%s. Your order will now be processed.', 'droix-stripe-id-check' ),
			$order->get_order_number()
		);

		$this->display_page(
			__( 'Verification Successful', 'droix-stripe-id-check' ),
			'<div class="dsic-success-box"><p>' . esc_html( $message ) . '</p></div>'
		);
	}

	/**
	 * Display failed page.
	 *
	 * @since 0.0.1
	 * @param WC_Order $order Order object.
	 * @param string   $error Error reason.
	 * @return void
	 */
	private function display_failed_page( WC_Order $order, string $error ): void {
		$message = sprintf(
			/* translators: 1: Order number, 2: Error reason */
			__( 'Unfortunately, we could not verify your identity for order #%1$s. Reason: %2$s. Please contact us for assistance.', 'droix-stripe-id-check' ),
			$order->get_order_number(),
			$error
		);

		$this->display_page(
			__( 'Verification Failed', 'droix-stripe-id-check' ),
			'<div class="dsic-error-box"><p>' . esc_html( $message ) . '</p></div>'
		);
	}

	/**
	 * Display processing page.
	 *
	 * @since 0.0.1
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	private function display_processing_page( WC_Order $order ): void {
		$message = sprintf(
			/* translators: %s: Order number */
			__( 'Thank you for completing the verification for order #%s. We are reviewing your submission and will update you shortly.', 'droix-stripe-id-check' ),
			$order->get_order_number()
		);

		$this->display_page(
			__( 'Verification Processing', 'droix-stripe-id-check' ),
			'<div class="dsic-info-box"><p>' . esc_html( $message ) . '</p></div>'
		);
	}

	/**
	 * Display a simple page.
	 *
	 * @since 0.0.1
	 * @param string $title   Page title.
	 * @param string $content Page content.
	 * @return void
	 */
	private function display_page( string $title, string $content ): void {
		$site_name = get_bloginfo( 'name' );
		$home_url  = home_url();

		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php echo esc_html( $title . ' - ' . $site_name ); ?></title>
			<style>
				body {
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
					background: #f0f0f1;
					margin: 0;
					padding: 40px 20px;
					min-height: 100vh;
					display: flex;
					align-items: center;
					justify-content: center;
				}
				.dsic-page {
					background: #fff;
					border-radius: 8px;
					box-shadow: 0 2px 10px rgba(0,0,0,0.1);
					max-width: 500px;
					padding: 40px;
					text-align: center;
				}
				.dsic-page h1 {
					margin: 0 0 20px;
					font-size: 24px;
					color: #1d2327;
				}
				.dsic-success-box { background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 15px; color: #155724; }
				.dsic-error-box { background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 15px; color: #721c24; }
				.dsic-info-box { background: #cce5ff; border: 1px solid #b8daff; border-radius: 4px; padding: 15px; color: #004085; }
				.dsic-page p { margin: 10px 0; line-height: 1.6; }
				.dsic-home-link {
					display: inline-block;
					margin-top: 20px;
					padding: 10px 25px;
					background: #2271b1;
					color: #fff;
					text-decoration: none;
					border-radius: 4px;
				}
				.dsic-home-link:hover { background: #135e96; }
				.dsic-support-link {
					display: inline-block;
					margin-top: 15px;
					margin-right: 10px;
					padding: 10px 25px;
					background: #d63638;
					color: #fff;
					text-decoration: none;
					border-radius: 4px;
				}
				.dsic-support-link:hover { background: #b32d2e; color: #fff; }
			</style>
		</head>
		<body>
			<div class="dsic-page">
				<h1><?php echo esc_html( $title ); ?></h1>
				<?php echo wp_kses_post( $content ); ?>
				<a href="<?php echo esc_url( $home_url ); ?>" class="dsic-home-link">
					<?php esc_html_e( 'Return to Shop', 'droix-stripe-id-check' ); ?>
				</a>
			</div>
		</body>
		</html>
		<?php
	}
}
