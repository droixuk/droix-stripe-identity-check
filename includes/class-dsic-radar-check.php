<?php
/**
 * Radar fraud check class.
 *
 * Handles Stripe Radar fraud score evaluation and automatic
 * ID verification triggering for high-risk orders.
 *
 * @package    DSIC
 * @subpackage DSIC/includes
 * @since      1.8.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Radar_Check
 *
 * @since 1.8.0
 */
class DSIC_Radar_Check {

	/**
	 * Maximum number of delayed retry attempts.
	 *
	 * @since 1.8.0
	 * @var int
	 */
	private const MAX_RETRY_ATTEMPTS = 3;

	/**
	 * Delay in seconds between retry attempts.
	 *
	 * @since 1.8.0
	 * @var int
	 */
	private const RETRY_DELAY_SECONDS = 30;

	/**
	 * Stripe payment method slugs to check.
	 *
	 * @since 1.8.0
	 * @var array
	 */
	private static array $stripe_payment_methods = array(
		'stripe',
		'stripe_cc',
		'stripe_sepa',
		'stripe_giropay',
		'stripe_ideal',
		'stripe_bancontact',
		'stripe_sofort',
		'stripe_eps',
		'stripe_p24',
		'stripe_boleto',
		'stripe_oxxo',
	);

	/**
	 * Initialize hooks.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public static function init(): void {
		// Check after payment completes (priority 25, after checkout's priority 20).
		add_action( 'woocommerce_payment_complete', array( new self(), 'check_order_risk' ), 25 );

		// Action Scheduler callback for delayed retries.
		add_action( 'dsic_radar_delayed_check', array( new self(), 'handle_delayed_check' ) );

		// Display Radar-specific notice on thank you page (runs independently of DSIC_Checkout).
		add_action( 'woocommerce_thankyou', array( new self(), 'display_radar_thankyou_notice' ), 4 );
	}

	/**
	 * Check order risk after payment completes.
	 *
	 * @since 1.8.0
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function check_order_risk( int $order_id ): void {
		// Bail if plugin disabled.
		if ( ! get_option( 'dsic_enabled', false ) ) {
			return;
		}

		// Bail if radar check disabled.
		if ( ! get_option( 'dsic_radar_check_enabled', false ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Skip if order is already cancelled or verified.
		if ( $order->has_status( array( 'cancelled', 'refunded', 'failed' ) ) ) {
			return;
		}

		$verification_status = $order->get_meta( '_dsic_verification_status' );
		if ( 'verified' === $verification_status ) {
			return;
		}

		// Skip if already radar-checked.
		if ( $order->get_meta( '_dsic_radar_checked' ) ) {
			return;
		}

		// Check if this is a Stripe payment method.
		$payment_method = $order->get_payment_method();
		$stripe_methods = apply_filters( 'dsic_stripe_payment_methods', self::$stripe_payment_methods );

		if ( ! in_array( $payment_method, $stripe_methods, true ) ) {
			DSIC_Logger::debug( 'Radar check skipped for order #' . $order_id . ': non-Stripe payment method (' . $payment_method . ')' );
			return;
		}

		// Skip if order total is below the configured minimum amount.
		$minimum_amount = (float) get_option( 'dsic_radar_minimum_order_amount', 0 );
		if ( $minimum_amount > 0 ) {
			$order_total = (float) $order->get_total();
			if ( $order_total < $minimum_amount ) {
				DSIC_Logger::debug(
					sprintf(
						'Radar check skipped for order #%d: order total (%.2f) is below minimum amount (%.2f)',
						$order_id,
						$order_total,
						$minimum_amount
					)
				);
				return;
			}
		}

		// Get charge ID from transaction ID or resolve via PaymentIntent.
		$charge_id = $this->resolve_charge_id( $order );

		if ( empty( $charge_id ) ) {
			// Schedule delayed retry if no charge ID yet.
			$this->schedule_delayed_check( $order_id );
			return;
		}

		// Fetch charge data.
		$api    = new DSIC_Stripe_API();
		$charge = $api->get_charge( $charge_id );

		if ( is_wp_error( $charge ) ) {
			DSIC_Logger::error( 'Radar check failed for order #' . $order_id . ': ' . $charge->get_error_message() );
			$order->add_order_note(
				sprintf(
					/* translators: %s: Error message */
					__( 'Stripe Radar check failed: %s', 'droix-stripe-id-check' ),
					$charge->get_error_message()
				)
			);
			return;
		}

		$outcome = $charge['outcome'] ?? array();

		if ( empty( $outcome ) ) {
			DSIC_Logger::debug( 'Radar check skipped for order #' . $order_id . ': no outcome data in charge' );
			return;
		}

		// Store risk data as order meta.
		$risk_level = $outcome['risk_level'] ?? 'unknown';
		$risk_score = $outcome['risk_score'] ?? null;

		$order->update_meta_data( '_dsic_radar_risk_level', sanitize_text_field( $risk_level ) );
		if ( null !== $risk_score ) {
			$order->update_meta_data( '_dsic_radar_risk_score', (int) $risk_score );
		}
		$order->update_meta_data( '_dsic_radar_checked', time() );
		$order->save();

		DSIC_Logger::info(
			sprintf(
				'Radar check for order #%d: risk_level=%s, risk_score=%s',
				$order_id,
				$risk_level,
				null !== $risk_score ? (string) $risk_score : 'N/A'
			)
		);

		// Evaluate risk against threshold.
		if ( $this->evaluate_risk( $outcome ) ) {
			$risk_data = array(
				'risk_level' => $risk_level,
				'risk_score' => $risk_score,
			);
			$this->trigger_radar_verification( $order, $risk_data );
		}
	}

	/**
	 * Resolve the charge ID from an order.
	 *
	 * Tries transaction_id first, then _stripe_intent_id meta.
	 *
	 * @since 1.8.0
	 * @param WC_Order $order Order object.
	 * @return string Charge ID or empty string.
	 */
	private function resolve_charge_id( WC_Order $order ): string {
		$transaction_id = $order->get_transaction_id();

		// If transaction ID starts with 'ch_', it's a charge ID.
		if ( ! empty( $transaction_id ) && str_starts_with( $transaction_id, 'ch_' ) ) {
			return $transaction_id;
		}

		// If transaction ID starts with 'pi_', resolve charge from PaymentIntent.
		if ( ! empty( $transaction_id ) && str_starts_with( $transaction_id, 'pi_' ) ) {
			$api    = new DSIC_Stripe_API();
			$charge = $api->get_payment_intent_charge( $transaction_id );

			if ( ! is_wp_error( $charge ) && ! empty( $charge['id'] ) ) {
				return $charge['id'];
			}
		}

		// Try _stripe_intent_id meta (WooCommerce Stripe gateway stores this).
		$intent_id = $order->get_meta( '_stripe_intent_id' );
		if ( ! empty( $intent_id ) && str_starts_with( $intent_id, 'pi_' ) ) {
			$api    = new DSIC_Stripe_API();
			$charge = $api->get_payment_intent_charge( $intent_id );

			if ( ! is_wp_error( $charge ) && ! empty( $charge['id'] ) ) {
				return $charge['id'];
			}
		}

		return '';
	}

	/**
	 * Evaluate whether the risk meets the configured threshold.
	 *
	 * @since 1.8.0
	 * @param array $outcome Charge outcome data from Stripe.
	 * @return bool True if risk exceeds threshold.
	 */
	private function evaluate_risk( array $outcome ): bool {
		$mode = get_option( 'dsic_radar_check_mode', 'risk_level' );

		if ( 'risk_score' === $mode ) {
			$risk_score = $outcome['risk_score'] ?? null;

			if ( null !== $risk_score ) {
				$threshold = (int) get_option( 'dsic_radar_risk_score_threshold', 65 );
				return (int) $risk_score >= $threshold;
			}

			// Fallback to risk_level if score not available.
			DSIC_Logger::warning( 'Radar risk_score not available, falling back to risk_level evaluation' );
		}

		// Risk level evaluation.
		$risk_level = $outcome['risk_level'] ?? 'normal';
		$threshold  = get_option( 'dsic_radar_risk_level_threshold', 'elevated' );

		$level_values = array(
			'normal'   => 0,
			'elevated' => 1,
			'highest'  => 2,
		);

		$current_value   = $level_values[ $risk_level ] ?? 0;
		$threshold_value = $level_values[ $threshold ] ?? 1;

		return $current_value >= $threshold_value;
	}

	/**
	 * Trigger ID verification for a high-risk order.
	 *
	 * @since 1.8.0
	 * @param WC_Order $order     Order object.
	 * @param array    $risk_data Risk data (risk_level, risk_score).
	 * @return void
	 */
	private function trigger_radar_verification( WC_Order $order, array $risk_data ): void {
		$order_id   = $order->get_id();
		$risk_level = $risk_data['risk_level'] ?? 'unknown';
		$risk_score = $risk_data['risk_score'] ?? null;

		$note_message = sprintf(
			/* translators: 1: Risk level, 2: Risk score or N/A */
			__( 'ID verification required (Stripe Radar risk level: %1$s, score: %2$s)', 'droix-stripe-id-check' ),
			$risk_level,
			null !== $risk_score ? (string) $risk_score : 'N/A'
		);

		// Check if verification was already triggered (e.g., by address mismatch).
		if ( $order->get_meta( '_dsic_auto_verification_triggered' ) ) {
			$order->add_order_note( $note_message . ' ' . __( '(verification already in progress)', 'droix-stripe-id-check' ) );
			$order->save();
			DSIC_Logger::info( 'Radar: verification already triggered for order #' . $order_id . ', added note only' );
			return;
		}

		// Mark as auto-verification triggered (shared flag used by all flows).
		$order->update_meta_data( '_dsic_auto_verification_triggered', time() );
		// Mark specifically as Radar-triggered (used to show Radar-specific customer message).
		$order->update_meta_data( '_dsic_radar_verification_triggered', time() );
		$order->save();

		// Set order on hold.
		if ( ! $order->has_status( 'on-hold' ) ) {
			$order->update_status( 'on-hold', __( 'High fraud risk detected by Stripe Radar.', 'droix-stripe-id-check' ) );
		}

		// Add detailed order note.
		$order->add_order_note( $note_message );

		// Request verification via the order handler.
		if ( ! class_exists( 'DSIC_Order_Handler' ) ) {
			require_once DSIC_PLUGIN_DIR . 'admin/class-dsic-order-handler.php';
		}
		$order_handler = new DSIC_Order_Handler();
		$order_handler->request_verification( $order_id );

		// Fire CRM notification.
		do_action( 'dsic_crm_notification', $order_id, 'radar_flagged', '', $risk_data );

		DSIC_Logger::info( 'Radar: verification triggered for order #' . $order_id . ' (level: ' . $risk_level . ', score: ' . ( $risk_score ?? 'N/A' ) . ')' );
	}

	/**
	 * Schedule a delayed radar check via Action Scheduler.
	 *
	 * @since 1.8.0
	 * @param int $order_id Order ID.
	 * @return void
	 */
	private function schedule_delayed_check( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$attempts = (int) $order->get_meta( '_dsic_radar_retry_attempts' );

		if ( $attempts >= self::MAX_RETRY_ATTEMPTS ) {
			DSIC_Logger::warning( 'Radar check: max retry attempts reached for order #' . $order_id );
			$order->add_order_note( __( 'Stripe Radar check skipped: charge ID not available after maximum retry attempts.', 'droix-stripe-id-check' ) );
			return;
		}

		$order->update_meta_data( '_dsic_radar_retry_attempts', $attempts + 1 );
		$order->save();

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time() + self::RETRY_DELAY_SECONDS,
				'dsic_radar_delayed_check',
				array( $order_id ),
				'dsic-radar'
			);
			DSIC_Logger::debug( 'Radar check: scheduled retry #' . ( $attempts + 1 ) . ' for order #' . $order_id );
		}
	}

	/**
	 * Handle delayed radar check (Action Scheduler callback).
	 *
	 * @since 1.8.0
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function handle_delayed_check( int $order_id ): void {
		DSIC_Logger::debug( 'Radar: executing delayed check for order #' . $order_id );
		$this->check_order_risk( $order_id );
	}

	/**
	 * Handle an early fraud warning for an order.
	 *
	 * Called from the webhook handler when a radar.early_fraud_warning.created event is received.
	 *
	 * @since 1.8.0
	 * @param int   $order_id     Order ID.
	 * @param array $warning_data Early fraud warning data.
	 * @return void
	 */
	public function handle_early_fraud_warning( int $order_id, array $warning_data ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$warning_id   = $warning_data['id'] ?? '';
		$fraud_type   = $warning_data['fraud_type'] ?? 'unknown';

		// Store early warning meta.
		$order->update_meta_data( '_dsic_radar_early_warning', sanitize_text_field( $warning_id ) );
		$order->update_meta_data( '_dsic_radar_early_warning_type', sanitize_text_field( $fraud_type ) );
		$order->save();

		$note_message = sprintf(
			/* translators: 1: Fraud type, 2: Warning ID */
			__( 'Early Fraud Warning received from card issuer (type: %1$s, warning: %2$s)', 'droix-stripe-id-check' ),
			$fraud_type,
			$warning_id
		);

		// Check if already verified - skip triggering.
		$verification_status = $order->get_meta( '_dsic_verification_status' );
		if ( 'verified' === $verification_status ) {
			$order->add_order_note( $note_message . ' ' . __( '(order already verified)', 'droix-stripe-id-check' ) );
			$order->save();
			return;
		}

		// Mark as EFW-triggered (distinct from checkout-time auto-verify).
		// This lets Linnworks integration lock the order even when _dsic_auto_verification_triggered is set.
		$order->update_meta_data( '_dsic_efw_triggered', time() );
		$order->save();

		// Trigger verification.
		$already_processed = in_array( $order->get_status(), array( 'processing', 'completed' ), true );
		$risk_data         = array(
			'risk_level'        => 'highest',
			'risk_score'        => null,
			'early_warning'     => true,
			'early_warning_type' => $fraud_type,
			'already_processed' => $already_processed,
			'order_status'      => $order->get_status(),
		);
		$this->trigger_radar_verification( $order, $risk_data );

		// Send Slack EFW notification.
		if ( class_exists( 'DSIC_Slack' ) ) {
			DSIC_Slack::notify_efw( $order, $fraud_type, $already_processed );
		}

		$order->add_order_note( $note_message );

		DSIC_Logger::info( 'Radar: early fraud warning processed for order #' . $order_id . ' (type: ' . $fraud_type . ')' );
	}

	/**
	 * Display Radar verification notice on the thank you page.
	 *
	 * Runs independently of DSIC_Checkout so it works even when
	 * address-mismatch auto-verification is disabled.
	 *
	 * @since 1.9.0
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function display_radar_thankyou_notice( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// If the Radar check hasn't completed yet (charge ID wasn't available at
		// woocommerce_payment_complete time), try synchronously now. By the time
		// the customer reaches the thank-you page the PaymentIntent has a latest_charge.
		if ( ! $order->get_meta( '_dsic_radar_checked' ) && ! $order->get_meta( '_dsic_radar_verification_triggered' ) ) {
			$this->check_order_risk( $order_id );
			$order = wc_get_order( $order_id );
		}

		// Only show when Radar specifically triggered verification.
		if ( ! $order->get_meta( '_dsic_radar_verification_triggered' ) ) {
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
