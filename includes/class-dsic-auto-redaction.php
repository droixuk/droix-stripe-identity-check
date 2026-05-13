<?php
/**
 * Automatic data redaction class.
 *
 * Handles automatic deletion of verification data from Stripe after retention period.
 *
 * @package    DSIC
 * @subpackage DSIC/includes
 * @since      1.7.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Auto_Redaction
 *
 * @since 1.7.0
 */
class DSIC_Auto_Redaction {

	/**
	 * Initialize hooks.
	 *
	 * @since 1.7.0
	 * @return void
	 */
	public static function init(): void {
		// Register Action Scheduler hooks.
		add_action( 'dsic_daily_redaction_check', array( __CLASS__, 'process_daily_check' ) );
		add_action( 'dsic_redact_order_data', array( __CLASS__, 'redact_order' ) );

		// Admin AJAX for manual redaction.
		add_action( 'wp_ajax_dsic_manual_redaction', array( __CLASS__, 'ajax_manual_redaction' ) );
	}

	/**
	 * Schedule daily redaction check via Action Scheduler.
	 *
	 * @since 1.7.0
	 * @return void
	 */
	public static function schedule_daily_check(): void {
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			// Log if logger is available (may not be loaded during activation).
			if ( class_exists( 'DSIC_Logger' ) ) {
				DSIC_Logger::warning( 'Action Scheduler not available - using WP-Cron fallback' );
			}

			// Fallback to wp_cron if Action Scheduler unavailable.
			if ( ! wp_next_scheduled( 'dsic_daily_redaction_check' ) ) {
				$schedule_time = get_option( 'dsic_redaction_schedule_time', '01:00' );
				$timestamp     = strtotime( 'tomorrow ' . $schedule_time );
				wp_schedule_event( $timestamp, 'daily', 'dsic_daily_redaction_check' );
				if ( class_exists( 'DSIC_Logger' ) ) {
					DSIC_Logger::info( 'Scheduled daily redaction check (WP-Cron) for ' . gmdate( 'Y-m-d H:i:s', $timestamp ) );
				}
			}
			return;
		}

		// Clear any existing schedule first.
		as_unschedule_all_actions( 'dsic_daily_redaction_check' );

		// Schedule new recurring action.
		$schedule_time = get_option( 'dsic_redaction_schedule_time', '01:00' );
		$timestamp     = strtotime( 'tomorrow ' . $schedule_time );

		as_schedule_recurring_action( $timestamp, DAY_IN_SECONDS, 'dsic_daily_redaction_check', array(), 'dsic' );

		// Log if logger is available (may not be loaded during activation).
		if ( class_exists( 'DSIC_Logger' ) ) {
			DSIC_Logger::info( 'Scheduled daily redaction check (Action Scheduler) for ' . gmdate( 'Y-m-d H:i:s', $timestamp ) );
		}
	}

	/**
	 * Daily check for orders needing redaction.
	 * Schedules individual redaction actions for each order.
	 *
	 * @since 1.7.0
	 * @return void
	 */
	public static function process_daily_check(): void {
		if ( ! get_option( 'dsic_auto_redaction_enabled', '0' ) ) {
			DSIC_Logger::debug( 'Daily redaction check skipped: feature disabled' );
			return;
		}

		$days       = absint( get_option( 'dsic_redaction_days', '30' ) );
		$batch_size = absint( get_option( 'dsic_redaction_batch_size', '20' ) );

		DSIC_Logger::info( '=== Starting daily redaction check (retention: ' . $days . ' days, batch: ' . $batch_size . ') ===' );

		// Find orders needing redaction.
		$orders = self::get_orders_for_redaction( $days, $batch_size );

		if ( empty( $orders ) ) {
			DSIC_Logger::info( 'No orders found for redaction' );
			return;
		}

		DSIC_Logger::info( 'Found ' . count( $orders ) . ' orders for redaction' );

		// Schedule individual actions (staggered across 1 hour).
		$delay           = 0;
		$delay_increment = count( $orders ) > 0 ? ( 3600 / count( $orders ) ) : 0; // Spread across 1 hour.

		foreach ( $orders as $order_id ) {
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action(
					time() + $delay,
					'dsic_redact_order_data',
					array( 'order_id' => $order_id ),
					'dsic'
				);
			} else {
				// WP-Cron fallback.
				wp_schedule_single_event(
					time() + $delay,
					'dsic_redact_order_data',
					array( $order_id )
				);
			}

			$delay += $delay_increment;
		}

		DSIC_Logger::info( 'Scheduled ' . count( $orders ) . ' redaction actions (staggered over 1 hour)' );
	}

	/**
	 * Get orders eligible for redaction.
	 *
	 * @since 1.7.0
	 * @param int $days  Retention period in days.
	 * @param int $limit Maximum orders to return.
	 * @return array Array of order IDs.
	 */
	private static function get_orders_for_redaction( int $days, int $limit ): array {
		$cutoff_time = time() - ( $days * DAY_IN_SECONDS );

		$args = array(
			'type'       => 'shop_order',
			'status'     => 'any',
			'limit'      => $limit,
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'     => '_dsic_verification_status',
					'value'   => array( 'verified', 'failed' ), // Both verified AND failed.
					'compare' => 'IN',
				),
				array(
					'key'     => '_dsic_data_redaction_status',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_dsic_verification_completed',
					'value'   => $cutoff_time,
					'compare' => '<=',
					'type'    => 'NUMERIC',
				),
			),
			'orderby'    => array(
				'meta_value_num' => 'ASC', // Oldest first.
			),
			'meta_key'   => '_dsic_verification_completed',
			'return'     => 'ids',
		);

		return wc_get_orders( $args );
	}

	/**
	 * Redact data for a single order.
	 * Called by Action Scheduler for each order.
	 *
	 * @since 1.7.0
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function redact_order( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			DSIC_Logger::error( 'Redaction failed: Order #' . $order_id . ' not found' );
			return;
		}

		// Prevent duplicate redaction.
		$existing_status = $order->get_meta( '_dsic_data_redaction_status' );
		if ( in_array( $existing_status, array( 'completed', 'pending' ), true ) ) {
			DSIC_Logger::info( 'Skipping order #' . $order_id . ': Already ' . $existing_status );
			return;
		}

		DSIC_Logger::info( '=== Starting redaction for order #' . $order_id . ' ===' );

		// Mark as pending.
		$order->update_meta_data( '_dsic_data_redaction_status', 'pending' );
		$order->update_meta_data( '_dsic_data_redaction_requested', time() );
		$order->save();

		// Get session ID.
		$session_id = $order->get_meta( '_dsic_verification_session_id' );

		if ( empty( $session_id ) ) {
			DSIC_Logger::error( 'Redaction failed: No session ID for order #' . $order_id );
			self::mark_redaction_failed( $order, 'No session ID found' );
			return;
		}

		// Call Stripe redaction API.
		$api    = new DSIC_Stripe_API();
		$result = $api->redact_verification_session( $session_id );

		if ( is_wp_error( $result ) ) {
			$error_msg = $result->get_error_message();

			// If already redacted, treat as success.
			if ( strpos( $error_msg, 'already been redacted' ) !== false ) {
				DSIC_Logger::info( 'Session already redacted, marking as completed' );
				self::mark_redaction_completed( $order, $session_id, true );
				return;
			}

			DSIC_Logger::error( 'Redaction API failed for order #' . $order_id . ': ' . $error_msg );

			// Check retry count.
			$error_count = (int) $order->get_meta( '_dsic_redaction_error_count' );
			$error_count++;

			$order->update_meta_data( '_dsic_redaction_error_count', $error_count );
			$order->update_meta_data( '_dsic_redaction_error_message', $error_msg );
			$order->save();

			if ( $error_count >= 3 ) {
				self::mark_redaction_failed( $order, $error_msg );
				return;
			}

			// Throw exception to trigger Action Scheduler retry.
			throw new Exception( $error_msg );
		}

		// Success - mark as completed immediately (don't wait for webhook).
		self::mark_redaction_completed( $order, $session_id );

		DSIC_Logger::info( '=== Redaction completed for order #' . $order_id . ' ===' );
	}

	/**
	 * Mark redaction as completed.
	 *
	 * @since 1.7.0
	 * @param WC_Order $order            Order object.
	 * @param string   $session_id       Stripe session ID.
	 * @param bool     $already_redacted Whether already redacted.
	 * @return void
	 */
	private static function mark_redaction_completed( WC_Order $order, string $session_id, bool $already_redacted = false ): void {
		$order_id          = $order->get_id();
		$verification_date = $order->get_meta( '_dsic_verification_completed' );
		$days_retained     = $verification_date ? round( ( time() - $verification_date ) / DAY_IN_SECONDS ) : 0;

		// Update order meta.
		$order->update_meta_data( '_dsic_data_redaction_status', 'completed' );
		$order->update_meta_data( '_dsic_data_redaction_completed', time() );
		$order->update_meta_data( '_dsic_verification_session_id', $session_id . ' [REDACTED]' );
		$order->save();

		// Add order note.
		$note_parts = array(
			'🗑️ VERIFICATION DATA REDACTED',
			sprintf( '📅 Redacted: %s', wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
			sprintf( '⏱️ Verification Date: %s', $verification_date ? wp_date( get_option( 'date_format' ), $verification_date ) : 'Unknown' ),
			sprintf( '⏰ Days Retained: %d days', $days_retained ),
			sprintf( '🔗 Stripe Session: %s [REDACTED]', substr( $session_id, 0, 15 ) . '...' ),
			'📋 Compliance: GDPR Article 17 (Right to Erasure)',
			$already_redacted ? '✅ Status: Already redacted (confirmed)' : '✅ Status: Redaction requested successfully',
		);

		$order->add_order_note( implode( "\n", $note_parts ) );

		// Log to compliance audit.
		self::log_to_compliance_audit(
			$order_id,
			'completed',
			array(
				'session_id'        => $session_id,
				'days_retained'     => $days_retained,
				'verification_date' => $verification_date,
				'already_redacted'  => $already_redacted,
			)
		);

		// Send customer notification email.
		if ( get_option( 'dsic_redaction_notify_customer', '1' ) ) {
			self::send_customer_notification( $order );
		}
	}

	/**
	 * Mark redaction as failed.
	 *
	 * @since 1.7.0
	 * @param WC_Order $order         Order object.
	 * @param string   $error_message Error message.
	 * @return void
	 */
	private static function mark_redaction_failed( WC_Order $order, string $error_message ): void {
		$order_id = $order->get_id();

		$order->update_meta_data( '_dsic_data_redaction_status', 'failed' );
		$order->save();

		$order->add_order_note(
			'❌ DATA REDACTION FAILED' . "\n" .
			'Error: ' . $error_message . "\n" .
			'Attempts: 3' . "\n" .
			'Action Required: Manual redaction needed'
		);

		// Log to compliance audit.
		self::log_to_compliance_audit(
			$order_id,
			'failed',
			array(
				'error'    => $error_message,
				'attempts' => 3,
			)
		);

		// Alert admin.
		$crm_email = get_option( 'dsic_crm_email' );
		if ( $crm_email ) {
			wp_mail(
				$crm_email,
				'Data Redaction Failed - Order #' . $order_id,
				"Automatic data redaction failed after 3 attempts.\n\n" .
				'Order: #' . $order_id . "\n" .
				'Error: ' . $error_message . "\n\n" .
				"Manual intervention required.\n" .
				'View order: ' . $order->get_edit_order_url()
			);
		}

		DSIC_Logger::error( 'Redaction failed permanently for order #' . $order_id );
	}

	/**
	 * Send customer notification email.
	 *
	 * @since 1.7.0
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	private static function send_customer_notification( WC_Order $order ): void {
		// Trigger WooCommerce email.
		WC()->mailer();
		do_action( 'dsic_data_redaction_complete', $order->get_id() );

		$order->update_meta_data( '_dsic_redaction_email_sent', time() );
		$order->save();

		DSIC_Logger::info( 'Redaction notification email sent to ' . $order->get_billing_email() . ' for order #' . $order->get_id() );
	}

	/**
	 * Log to compliance audit table.
	 *
	 * @since 1.7.0
	 * @param int    $order_id Order ID.
	 * @param string $status   Status (completed, failed, pending).
	 * @param array  $details  Additional details.
	 * @return void
	 */
	private static function log_to_compliance_audit( int $order_id, string $status, array $details ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'dsic_compliance_log';

		$wpdb->insert(
			$table_name,
			array(
				'order_id'   => $order_id,
				'action'     => 'data_redaction',
				'status'     => $status,
				'details'    => wp_json_encode( $details ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Manual redaction (triggered from order admin).
	 *
	 * @since 1.7.0
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function manual_redaction( int $order_id ): void {
		$current_user = wp_get_current_user();
		DSIC_Logger::info( 'Manual redaction triggered for order #' . $order_id . ' by ' . $current_user->display_name );

		// Schedule immediate redaction.
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time(), 'dsic_redact_order_data', array( 'order_id' => $order_id ), 'dsic' );
		} else {
			wp_schedule_single_event( time(), 'dsic_redact_order_data', array( $order_id ) );
		}
	}

	/**
	 * AJAX handler for manual redaction.
	 *
	 * @since 1.7.0
	 * @return void
	 */
	public static function ajax_manual_redaction(): void {
		check_ajax_referer( 'dsic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'droix-stripe-id-check' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID', 'droix-stripe-id-check' ) ) );
		}

		// Trigger manual redaction.
		self::manual_redaction( $order_id );

		wp_send_json_success(
			array(
				'message' => __( 'Redaction scheduled. Page will reload in 2 seconds...', 'droix-stripe-id-check' ),
			)
		);
	}
}
