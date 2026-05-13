<?php
/**
 * Order amount threshold check class.
 *
 * Automatically triggers ID verification for orders whose total
 * exceeds a configurable monetary threshold, with currency conversion.
 *
 * @package    DSIC
 * @subpackage DSIC/includes
 * @since      1.10.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Amount_Threshold_Check
 *
 * @since 1.10.0
 */
class DSIC_Amount_Threshold_Check {

	/**
	 * Exchange rates (USD as base).
	 *
	 * Empty by default to avoid shipping stale rates. Multicurrency stores can
	 * supply rates through the dsic_amount_threshold_exchange_rates filter.
	 *
	 * @since 1.10.0
	 * @var array<string, float>
	 */
	private static array $rates_to_usd = array();

	/**
	 * Initialize hooks.
	 *
	 * @since 1.10.0
	 * @return void
	 */
	public static function init(): void {
		// Check after payment completes — priority 15, before address mismatch (20) and Radar (25).
		add_action( 'woocommerce_payment_complete', array( new self(), 'check_order_amount' ), 15 );

		// Display threshold-specific notice on thank you page — priority 3, before Radar (4) and checkout (5).
		add_action( 'woocommerce_thankyou', array( new self(), 'display_threshold_thankyou_notice' ), 3 );
	}

	/**
	 * Check order total against the configured threshold.
	 *
	 * @since 1.10.0
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function check_order_amount( int $order_id ): void {
		// Bail if plugin disabled.
		if ( ! get_option( 'dsic_enabled', false ) ) {
			return;
		}

		// Bail if amount threshold check disabled.
		if ( ! get_option( 'dsic_amount_threshold_enabled', false ) ) {
			return;
		}

		$threshold = (float) get_option( 'dsic_amount_threshold_value', 0 );
		if ( $threshold <= 0 ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Skip cancelled/refunded/failed orders.
		if ( $order->has_status( array( 'cancelled', 'refunded', 'failed' ) ) ) {
			return;
		}

		// Skip if already verified.
		if ( 'verified' === $order->get_meta( '_dsic_verification_status' ) ) {
			return;
		}

		// Skip if another trigger already requested verification.
		if ( $order->get_meta( '_dsic_auto_verification_triggered' ) ) {
			return;
		}

		$threshold_currency = get_option( 'dsic_amount_threshold_currency', '' );
		if ( empty( $threshold_currency ) ) {
			$threshold_currency = get_woocommerce_currency();
		}

		$order_total    = (float) $order->get_total();
		$order_currency = $order->get_currency();

		$converted_total = $this->convert_currency( $order_total, $order_currency, $threshold_currency );
		if ( null === $converted_total ) {
			return;
		}

		if ( $converted_total >= $threshold ) {
			$this->trigger_threshold_verification( $order, $converted_total, $threshold, $threshold_currency );
		}
	}

	/**
	 * Convert an amount between currencies using USD-base rates supplied by filter.
	 *
	 * @since 1.10.0
	 * @param float  $amount Amount to convert.
	 * @param string $from   Source currency code.
	 * @param string $to     Target currency code.
	 * @return float|null Converted amount, or null when no conversion rate is available.
	 */
	private function convert_currency( float $amount, string $from, string $to ): ?float {
		if ( $from === $to ) {
			return $amount;
		}

		$rates = apply_filters( 'dsic_amount_threshold_exchange_rates', self::$rates_to_usd );

		if ( ! isset( $rates[ $from ] ) ) {
			DSIC_Logger::warning( sprintf( 'Amount threshold: no conversion rate for source currency "%s"; threshold check skipped.', $from ) );
			return null;
		}

		if ( ! isset( $rates[ $to ] ) ) {
			DSIC_Logger::warning( sprintf( 'Amount threshold: no conversion rate for target currency "%s"; threshold check skipped.', $to ) );
			return null;
		}

		return $amount / $rates[ $from ] * $rates[ $to ];
	}

	/**
	 * Trigger ID verification because the order amount exceeds the threshold.
	 *
	 * @since 1.10.0
	 * @param WC_Order $order              Order object.
	 * @param float    $converted_total    Order total converted to threshold currency.
	 * @param float    $threshold          Configured threshold value.
	 * @param string   $threshold_currency Threshold currency code.
	 * @return void
	 */
	private function trigger_threshold_verification( WC_Order $order, float $converted_total, float $threshold, string $threshold_currency ): void {
		$order_id = $order->get_id();

		// Mark shared auto-verification flag (prevents duplicate verification from other triggers).
		$order->update_meta_data( '_dsic_auto_verification_triggered', time() );
		// Mark specifically as threshold-triggered (used for customer notice).
		$order->update_meta_data( '_dsic_amount_threshold_triggered', time() );
		$order->save();

		// Set order on hold.
		if ( ! $order->has_status( 'on-hold' ) ) {
			$order->update_status(
				'on-hold',
				sprintf(
					/* translators: 1: Converted order total with currency, 2: Threshold with currency */
					__( 'ID verification required (order total %1$s %2$s exceeds threshold of %3$s %4$s)', 'droix-stripe-id-check' ),
					number_format( $converted_total, 2 ),
					$threshold_currency,
					number_format( $threshold, 2 ),
					$threshold_currency
				)
			);
		}

		// Add order note.
		$order->add_order_note(
			sprintf(
				/* translators: 1: Converted order total, 2: Threshold currency, 3: Threshold value, 4: Threshold currency */
				__( 'ID verification required (order total %1$s %2$s exceeds threshold of %3$s %4$s)', 'droix-stripe-id-check' ),
				number_format( $converted_total, 2 ),
				$threshold_currency,
				number_format( $threshold, 2 ),
				$threshold_currency
			)
		);

		// Request verification via the order handler.
		if ( ! class_exists( 'DSIC_Order_Handler' ) ) {
			require_once DSIC_PLUGIN_DIR . 'admin/class-dsic-order-handler.php';
		}
		$order_handler = new DSIC_Order_Handler();
		$order_handler->request_verification( $order_id );

		// Fire CRM notification.
		do_action( 'dsic_crm_notification', $order_id, 'amount_threshold', '', array() );

		DSIC_Logger::info(
			sprintf(
				'Amount threshold: verification triggered for order #%d (total: %.2f %s >= threshold: %.2f %s)',
				$order_id,
				$converted_total,
				$threshold_currency,
				$threshold,
				$threshold_currency
			)
		);
	}

	/**
	 * Display a security notice on the thank you page for threshold-triggered orders.
	 *
	 * @since 1.10.0
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function display_threshold_thankyou_notice( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Only show when amount threshold specifically triggered verification.
		if ( ! $order->get_meta( '_dsic_amount_threshold_triggered' ) ) {
			return;
		}

		if ( class_exists( 'DSIC_WPML' ) ) {
			$message = DSIC_WPML::get_radar_thankyou_message();
		} else {
			$settings = new DSIC_Settings();
			$message  = get_option( 'dsic_radar_thankyou_message', $settings->get_default_radar_thankyou_message() );
		}

		?>
		<div class="dsic-thankyou-notice woocommerce-message">
			<span class="dsic-notice-icon" aria-hidden="true"></span>
			<?php echo wp_kses_post( $message ); ?>
		</div>
		<?php
	}
}
