<?php
/**
 * Linnworks Integration Class.
 *
 * Handles automatic locking/unlocking of orders in Linnworks
 * based on ID verification status.
 *
 * @package    DSIC
 * @subpackage DSIC/includes
 * @since      1.5.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Linnworks_Integration
 *
 * @since 1.5.0
 */
class DSIC_Linnworks_Integration {

	/**
	 * Initialize the integration.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function init(): void {
		// Always register test handlers (needed before enabling integration).
		add_action( 'wp_ajax_dsic_linnworks_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_dsic_linnworks_test_search', array( __CLASS__, 'ajax_test_search' ) );
		add_action( 'wp_ajax_dsic_linnworks_test_lock', array( __CLASS__, 'ajax_test_lock' ) );
		add_action( 'wp_ajax_dsic_linnworks_test_unlock', array( __CLASS__, 'ajax_test_unlock' ) );

		// Only initialize workflow hooks if integration is enabled.
		if ( ! DSIC_Linnworks_API::is_enabled() ) {
			DSIC_Logger::debug( 'Linnworks: Integration disabled - workflow hooks not registered' );
			return;
		}

		DSIC_Logger::info( 'Linnworks: Integration enabled - registering workflow hooks' );

		// Hook into verification workflow.
		add_action( 'dsic_verification_requested', array( __CLASS__, 'on_verification_requested' ), 10, 2 );
		add_action( 'dsic_verification_passed', array( __CLASS__, 'on_verification_passed' ), 10, 1 );
		add_action( 'dsic_verification_cancelled', array( __CLASS__, 'on_verification_cancelled' ), 10, 1 );

		// Add AJAX handlers for manual lock/unlock from admin.
		add_action( 'wp_ajax_dsic_linnworks_lock_order', array( __CLASS__, 'ajax_lock_order' ) );
		add_action( 'wp_ajax_dsic_linnworks_unlock_order', array( __CLASS__, 'ajax_unlock_order' ) );
	}

	/**
	 * Handle verification request - lock the order in Linnworks.
	 *
	 * @since 1.5.0
	 * @param int    $order_id         WooCommerce order ID.
	 * @param string $verification_url The verification URL (unused).
	 * @return void
	 */
	public static function on_verification_requested( int $order_id, string $verification_url ): void {
		DSIC_Logger::info( 'Linnworks: on_verification_requested hook fired for order #' . $order_id );

		// Skip locking for checkout/payment-time auto-triggered verifications (order not in Linnworks yet).
		// BUT do NOT skip for early fraud warnings — those orders may be days old and already in Linnworks.
		$order = wc_get_order( $order_id );
		if ( $order && $order->get_meta( '_dsic_auto_verification_triggered' ) && ! $order->get_meta( '_dsic_efw_triggered' ) ) {
			DSIC_Logger::info( 'Linnworks: Skipping auto-lock for order #' . $order_id . ' (auto-triggered verification - order not synced to Linnworks yet)' );
			return;
		}

		// Check if auto-lock is enabled.
		$auto_lock = get_option( 'dsic_linnworks_auto_lock', '1' );
		DSIC_Logger::info( 'Linnworks: Auto-lock setting value: "' . $auto_lock . '"' );

		if ( ! $auto_lock || '0' === $auto_lock || 'no' === $auto_lock ) {
			DSIC_Logger::warning( 'Linnworks: Auto-lock disabled, skipping lock for order #' . $order_id );
			return;
		}

		DSIC_Logger::info( 'Linnworks: Proceeding to auto-lock order #' . $order_id );

		$api    = new DSIC_Linnworks_API();
		$result = $api->lock_order_by_wc_id( $order_id, 'auto' );

		if ( $result['success'] ) {
			DSIC_Logger::info( 'Linnworks: Order #' . $order_id . ' locked successfully' );

			// Add detailed order note about Linnworks lock.
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$note_parts = array(
					__( '🔒 Order LOCKED in Linnworks', 'droix-stripe-id-check' ),
					sprintf(
						/* translators: %s: Date and time */
						__( '📅 Locked: %s', 'droix-stripe-id-check' ),
						wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
					),
					__( '📋 Reason: Pending ID verification', 'droix-stripe-id-check' ),
					sprintf(
						/* translators: %s: Linnworks ID */
						__( '🏷️ Linnworks ID: %s', 'droix-stripe-id-check' ),
						$result['linnworks_id'] ?? 'N/A'
					),
				);
				$order->add_order_note( implode( "\n", $note_parts ) );
			}
		} else {
			DSIC_Logger::error( 'Linnworks: Failed to lock order #' . $order_id . ': ' . ( $result['error'] ?? 'Unknown error' ) );

			// Add order note about failure.
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order->add_order_note(
					sprintf(
						/* translators: %s: Error message */
						__( 'Failed to lock order in Linnworks: %s', 'droix-stripe-id-check' ),
						$result['error'] ?? __( 'Unknown error', 'droix-stripe-id-check' )
					)
				);
			}
		}
	}

	/**
	 * Handle verification passed - unlock the order in Linnworks.
	 *
	 * @since 1.5.0
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public static function on_verification_passed( int $order_id ): void {
		// Check if auto-unlock is enabled.
		if ( ! get_option( 'dsic_linnworks_auto_unlock', '1' ) ) {
			DSIC_Logger::debug( 'Linnworks auto-unlock disabled, skipping for order #' . $order_id );
			return;
		}

		DSIC_Logger::info( 'Linnworks: Auto-unlocking order #' . $order_id . ' (verification passed)' );

		$api    = new DSIC_Linnworks_API();
		$result = $api->unlock_order_by_wc_id( $order_id, 'auto', 'ID verification passed' );

		if ( $result['success'] ) {
			DSIC_Logger::info( 'Linnworks: Order #' . $order_id . ' unlocked successfully' );

			// Add detailed order note about Linnworks unlock.
			$order = wc_get_order( $order_id );
			if ( $order ) {
				// Get Stripe session URL.
				$session_id = $order->get_meta( '_dsic_verification_session_id' );
				$stripe_url = self::get_stripe_dashboard_url( $session_id );

				$note_parts = array(
					__( '🔓 Order UNLOCKED in Linnworks', 'droix-stripe-id-check' ),
					sprintf(
						/* translators: %s: Date and time */
						__( '📅 Unlocked: %s', 'droix-stripe-id-check' ),
						wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
					),
					__( '✅ Reason: ID verification passed', 'droix-stripe-id-check' ),
					sprintf(
						/* translators: %s: Linnworks ID */
						__( '🏷️ Linnworks ID: %s', 'droix-stripe-id-check' ),
						$result['linnworks_id'] ?? 'N/A'
					),
				);

				if ( ! empty( $session_id ) ) {
					$note_parts[] = sprintf(
						/* translators: %s: Stripe dashboard URL */
						__( '🔗 Stripe Session: %s', 'droix-stripe-id-check' ),
						$stripe_url
					);
				}

				$note_parts[] = __( '📋 Order is ready to be processed in Linnworks.', 'droix-stripe-id-check' );
				$order->add_order_note( implode( "\n", $note_parts ) );
			}
		} else {
			DSIC_Logger::error( 'Linnworks: Failed to unlock order #' . $order_id . ': ' . ( $result['error'] ?? 'Unknown error' ) );

			// Add order note about failure.
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order->add_order_note(
					sprintf(
						/* translators: %s: Error message */
						__( 'Failed to unlock order in Linnworks: %s', 'droix-stripe-id-check' ),
						$result['error'] ?? __( 'Unknown error', 'droix-stripe-id-check' )
					)
				);
			}
		}
	}

	/**
	 * Handle verification cancelled - unlock the order in Linnworks.
	 *
	 * @since 1.5.1
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public static function on_verification_cancelled( int $order_id ): void {
		// Check if auto-unlock is enabled.
		if ( ! get_option( 'dsic_linnworks_auto_unlock', '1' ) ) {
			DSIC_Logger::debug( 'Linnworks auto-unlock disabled, skipping for cancelled order #' . $order_id );
			return;
		}

		DSIC_Logger::info( 'Linnworks: Auto-unlocking order #' . $order_id . ' (verification cancelled)' );

		$api    = new DSIC_Linnworks_API();
		$result = $api->unlock_order_by_wc_id( $order_id, 'auto', 'ID verification cancelled' );

		if ( $result['success'] ) {
			DSIC_Logger::info( 'Linnworks: Order #' . $order_id . ' unlocked successfully (cancelled)' );

			// Add order note about Linnworks unlock.
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$note_parts = array(
					__( '🔓 Order UNLOCKED in Linnworks', 'droix-stripe-id-check' ),
					sprintf(
						/* translators: %s: Date and time */
						__( '📅 Unlocked: %s', 'droix-stripe-id-check' ),
						wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
					),
					__( '⚠️ Reason: ID verification cancelled', 'droix-stripe-id-check' ),
					sprintf(
						/* translators: %s: Linnworks ID */
						__( '🏷️ Linnworks ID: %s', 'droix-stripe-id-check' ),
						$result['linnworks_id'] ?? 'N/A'
					),
				);
				$order->add_order_note( implode( "\n", $note_parts ) );
			}
		} else {
			DSIC_Logger::error( 'Linnworks: Failed to unlock order #' . $order_id . ' (cancelled): ' . ( $result['error'] ?? 'Unknown error' ) );

			// Add order note about failure.
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order->add_order_note(
					sprintf(
						/* translators: %s: Error message */
						__( 'Failed to unlock order in Linnworks after cancellation: %s', 'droix-stripe-id-check' ),
						$result['error'] ?? __( 'Unknown error', 'droix-stripe-id-check' )
					)
				);
			}
		}
	}

	/**
	 * Get Stripe dashboard URL for a verification session.
	 *
	 * @since 1.5.0
	 * @param string $session_id Stripe session ID.
	 * @return string Dashboard URL.
	 */
	private static function get_stripe_dashboard_url( string $session_id ): string {
		if ( empty( $session_id ) ) {
			return '';
		}

		$test_mode = get_option( 'dsic_test_mode', true );
		$base_url  = $test_mode
			? 'https://dashboard.stripe.com/test/identity/verification-sessions/'
			: 'https://dashboard.stripe.com/identity/verification-sessions/';

		return $base_url . $session_id;
	}

	/**
	 * AJAX handler for manual lock order.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function ajax_lock_order(): void {
		check_ajax_referer( 'dsic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'droix-stripe-id-check' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID', 'droix-stripe-id-check' ) ) );
		}

		$api    = new DSIC_Linnworks_API();
		$result = $api->lock_order_by_wc_id( $order_id, 'manual' );

		if ( $result['success'] ) {
			// Add order note.
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order->add_order_note(
					sprintf(
						/* translators: %s: Admin username */
						__( 'Order manually locked in Linnworks by %s.', 'droix-stripe-id-check' ),
						wp_get_current_user()->display_name
					)
				);
			}

			wp_send_json_success(
				array(
					'message'      => __( 'Order locked successfully.', 'droix-stripe-id-check' ),
					'linnworks_id' => $result['linnworks_id'],
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => $result['error'] ?? __( 'Failed to lock order.', 'droix-stripe-id-check' ),
				)
			);
		}
	}

	/**
	 * AJAX handler for manual unlock order.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function ajax_unlock_order(): void {
		check_ajax_referer( 'dsic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'droix-stripe-id-check' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$reason   = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID', 'droix-stripe-id-check' ) ) );
		}

		$api    = new DSIC_Linnworks_API();
		$result = $api->unlock_order_by_wc_id( $order_id, 'manual', $reason );

		if ( $result['success'] ) {
			// Add order note.
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$note = sprintf(
					/* translators: %s: Admin username */
					__( 'Order manually unlocked in Linnworks by %s.', 'droix-stripe-id-check' ),
					wp_get_current_user()->display_name
				);
				if ( $reason ) {
					$note .= ' ' . sprintf(
						/* translators: %s: Reason */
						__( 'Reason: %s', 'droix-stripe-id-check' ),
						$reason
					);
				}
				$order->add_order_note( $note );
			}

			wp_send_json_success(
				array(
					'message'      => __( 'Order unlocked successfully.', 'droix-stripe-id-check' ),
					'linnworks_id' => $result['linnworks_id'],
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => $result['error'] ?? __( 'Failed to unlock order.', 'droix-stripe-id-check' ),
				)
			);
		}
	}

	/**
	 * AJAX handler for testing Linnworks connection.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function ajax_test_connection(): void {
		check_ajax_referer( 'dsic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'droix-stripe-id-check' ) ) );
		}

		$api    = new DSIC_Linnworks_API();
		$result = $api->test_connection();

		if ( $result['success'] ) {
			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %s: Linnworks server */
						__( 'Connection successful! Connected to %s', 'droix-stripe-id-check' ),
						$result['server']
					),
					'server'  => $result['server'],
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => $result['error'] ?? __( 'Connection failed.', 'droix-stripe-id-check' ),
				)
			);
		}
	}

	/**
	 * AJAX handler for test searching an order in Linnworks.
	 *
	 * Searches Linnworks directly by reference number without
	 * requiring the order to exist in WooCommerce (for testing purposes).
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function ajax_test_search(): void {
		check_ajax_referer( 'dsic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'droix-stripe-id-check' ) ) );
		}

		$order_ref = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';

		if ( empty( $order_ref ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter an order reference number.', 'droix-stripe-id-check' ) ) );
		}

		$api    = new DSIC_Linnworks_API();
		$result = $api->search_order( $order_ref );

		// Check for WP_Error.
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		}

		// Check if found.
		if ( ! empty( $result['found'] ) ) {
			// Extract data from raw_data if available.
			$raw_data      = $result['raw_data'] ?? array();
			$customer_name = '';
			$total         = '';
			$created_date  = '';
			$locked        = false;

			// Try to extract fields from raw data (structure varies for open vs processed).
			if ( ! empty( $raw_data['CustomerName'] ) ) {
				$customer_name = $raw_data['CustomerName'];
			}
			if ( ! empty( $raw_data['TotalCost'] ) ) {
				$total = $raw_data['TotalCost'];
			}
			if ( ! empty( $raw_data['ReceivedDate'] ) ) {
				$created_date = $raw_data['ReceivedDate'];
			}
			if ( isset( $raw_data['IsLocked'] ) ) {
				$locked = (bool) $raw_data['IsLocked'];
			}

			wp_send_json_success(
				array(
					'message'      => sprintf(
						/* translators: %s: Order reference */
						__( 'Order %s found in Linnworks.', 'droix-stripe-id-check' ),
						$order_ref
					),
					'linnworks_id' => $result['linnworks_id'],
					'order_data'   => array(
						'linnworks_order_id' => $result['linnworks_id'],
						'reference_number'   => $result['reference'] ?? $order_ref,
						'status'             => $result['status'] ?? 'unknown',
						'is_open_order'      => $result['is_open'] ?? false,
						'locked'             => $locked,
						'customer_name'      => $customer_name,
						'total'              => $total,
						'created_date'       => $created_date,
					),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: Order reference */
						__( 'Order %s not found in Linnworks.', 'droix-stripe-id-check' ),
						$order_ref
					),
				)
			);
		}
	}

	/**
	 * AJAX handler for test locking an order in Linnworks.
	 *
	 * Searches Linnworks directly by reference number and locks it
	 * without requiring the order to exist in WooCommerce (for testing purposes).
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function ajax_test_lock(): void {
		check_ajax_referer( 'dsic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'droix-stripe-id-check' ) ) );
		}

		$order_ref = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';

		if ( empty( $order_ref ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter an order reference number.', 'droix-stripe-id-check' ) ) );
		}

		$api = new DSIC_Linnworks_API();

		// First search for the order in Linnworks.
		$search_result = $api->search_order( $order_ref );

		if ( is_wp_error( $search_result ) ) {
			wp_send_json_error(
				array(
					'message' => $search_result->get_error_message(),
				)
			);
		}

		if ( empty( $search_result['found'] ) || empty( $search_result['linnworks_id'] ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: Order reference */
						__( 'Order %s not found in Linnworks.', 'droix-stripe-id-check' ),
						$order_ref
					),
				)
			);
		}

		// Check if it's an open order (can only lock open orders).
		if ( empty( $search_result['is_open'] ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: Order reference */
						__( 'Order %s is a processed order and cannot be locked.', 'droix-stripe-id-check' ),
						$order_ref
					),
				)
			);
		}

		// Lock the order.
		$lock_result = $api->lock_order( $search_result['linnworks_id'], true );

		if ( is_wp_error( $lock_result ) ) {
			wp_send_json_error(
				array(
					'message' => $lock_result->get_error_message(),
				)
			);
		}

		if ( ! empty( $lock_result['success'] ) ) {
			wp_send_json_success(
				array(
					'message'      => sprintf(
						/* translators: %s: Order reference */
						__( 'Order %s locked successfully in Linnworks.', 'droix-stripe-id-check' ),
						$order_ref
					),
					'linnworks_id' => $search_result['linnworks_id'],
					'locked'       => true,
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to lock order in Linnworks.', 'droix-stripe-id-check' ),
				)
			);
		}
	}

	/**
	 * AJAX handler for test unlocking an order in Linnworks.
	 *
	 * Searches Linnworks directly by reference number and unlocks it
	 * without requiring the order to exist in WooCommerce (for testing purposes).
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function ajax_test_unlock(): void {
		check_ajax_referer( 'dsic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'droix-stripe-id-check' ) ) );
		}

		$order_ref = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';

		if ( empty( $order_ref ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter an order reference number.', 'droix-stripe-id-check' ) ) );
		}

		$api = new DSIC_Linnworks_API();

		// First search for the order in Linnworks.
		$search_result = $api->search_order( $order_ref );

		if ( is_wp_error( $search_result ) ) {
			wp_send_json_error(
				array(
					'message' => $search_result->get_error_message(),
				)
			);
		}

		if ( empty( $search_result['found'] ) || empty( $search_result['linnworks_id'] ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: Order reference */
						__( 'Order %s not found in Linnworks.', 'droix-stripe-id-check' ),
						$order_ref
					),
				)
			);
		}

		// Check if it's an open order (can only unlock open orders).
		if ( empty( $search_result['is_open'] ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: Order reference */
						__( 'Order %s is a processed order and cannot be unlocked.', 'droix-stripe-id-check' ),
						$order_ref
					),
				)
			);
		}

		// Unlock the order.
		$unlock_result = $api->unlock_order( $search_result['linnworks_id'], true, 'Test unlock from settings panel' );

		if ( is_wp_error( $unlock_result ) ) {
			wp_send_json_error(
				array(
					'message' => $unlock_result->get_error_message(),
				)
			);
		}

		if ( ! empty( $unlock_result['success'] ) ) {
			wp_send_json_success(
				array(
					'message'      => sprintf(
						/* translators: %s: Order reference */
						__( 'Order %s unlocked successfully in Linnworks.', 'droix-stripe-id-check' ),
						$order_ref
					),
					'linnworks_id' => $search_result['linnworks_id'],
					'locked'       => false,
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to unlock order in Linnworks.', 'droix-stripe-id-check' ),
				)
			);
		}
	}
}
