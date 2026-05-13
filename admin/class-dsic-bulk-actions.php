<?php
/**
 * Bulk actions class.
 *
 * Handles bulk actions for orders in the WooCommerce orders list.
 *
 * @package    DSIC
 * @subpackage DSIC/admin
 * @since      0.3.1
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Bulk_Actions
 *
 * @since 0.3.1
 */
class DSIC_Bulk_Actions {

	/**
	 * Constructor.
	 *
	 * @since 0.3.1
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 0.3.1
	 * @return void
	 */
	private function init_hooks(): void {
		// Add bulk actions - Legacy (CPT).
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_bulk_actions' ), 10, 3 );

		// Add bulk actions - HPOS.
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handle_bulk_actions' ), 10, 3 );

		// Admin notices for bulk action results.
		add_action( 'admin_notices', array( $this, 'display_bulk_action_notices' ) );
	}

	/**
	 * Add bulk actions to orders list.
	 *
	 * @since 0.3.1
	 * @param array $actions Existing bulk actions.
	 * @return array Modified bulk actions.
	 */
	public function add_bulk_actions( array $actions ): array {
		// Only add if plugin is enabled.
		if ( ! get_option( 'dsic_enabled', false ) ) {
			return $actions;
		}

		$actions['dsic_request_verification'] = __( 'Request ID Verification', 'droix-stripe-id-check' );
		$actions['dsic_resend_verification']  = __( 'Resend Verification Email', 'droix-stripe-id-check' );
		$actions['dsic_cancel_verification']  = __( 'Cancel Verification', 'droix-stripe-id-check' );

		return $actions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @since 0.3.1
	 * @param string $redirect_url Redirect URL.
	 * @param string $action       Action being performed.
	 * @param array  $order_ids    Order IDs to process.
	 * @return string Modified redirect URL.
	 */
	public function handle_bulk_actions( string $redirect_url, string $action, array $order_ids ): string {
		// Check if this is one of our actions.
		if ( ! in_array( $action, array( 'dsic_request_verification', 'dsic_resend_verification', 'dsic_cancel_verification' ), true ) ) {
			return $redirect_url;
		}

		// Verify user capability.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $redirect_url;
		}

		$processed = 0;
		$skipped   = 0;
		$errors    = 0;

		$order_handler = new DSIC_Order_Handler();

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				$errors++;
				continue;
			}

			$status = $order->get_meta( '_dsic_verification_status' );

			switch ( $action ) {
				case 'dsic_request_verification':
					// Only request if not already requested or failed.
					if ( empty( $status ) || 'failed' === $status ) {
						$result = $order_handler->request_verification( $order_id );
						if ( is_wp_error( $result ) ) {
							$errors++;
							DSIC_Logger::error( 'Bulk request failed for order #' . $order_id . ': ' . $result->get_error_message() );
						} else {
							$processed++;
						}
					} else {
						$skipped++;
					}
					break;

				case 'dsic_resend_verification':
					// Only resend if pending.
					if ( 'pending' === $status ) {
						$result = $order_handler->resend_verification_email( $order_id );
						if ( is_wp_error( $result ) ) {
							$errors++;
							DSIC_Logger::error( 'Bulk resend failed for order #' . $order_id . ': ' . $result->get_error_message() );
						} else {
							$processed++;
						}
					} else {
						$skipped++;
					}
					break;

				case 'dsic_cancel_verification':
					// Only cancel if pending.
					if ( 'pending' === $status ) {
						$result = $order_handler->cancel_verification( $order_id );
						if ( is_wp_error( $result ) ) {
							$errors++;
							DSIC_Logger::error( 'Bulk cancel failed for order #' . $order_id . ': ' . $result->get_error_message() );
						} else {
							$processed++;
						}
					} else {
						$skipped++;
					}
					break;
			}
		}

		// Add results to redirect URL.
		$redirect_url = add_query_arg(
			array(
				'dsic_bulk_action'    => $action,
				'dsic_bulk_processed' => $processed,
				'dsic_bulk_skipped'   => $skipped,
				'dsic_bulk_errors'    => $errors,
			),
			$redirect_url
		);

		DSIC_Logger::info( "Bulk action {$action}: processed={$processed}, skipped={$skipped}, errors={$errors}" );

		return $redirect_url;
	}

	/**
	 * Display bulk action result notices.
	 *
	 * @since 0.3.1
	 * @return void
	 */
	public function display_bulk_action_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['dsic_bulk_action'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action    = sanitize_key( $_GET['dsic_bulk_action'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$processed = isset( $_GET['dsic_bulk_processed'] ) ? absint( $_GET['dsic_bulk_processed'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$skipped   = isset( $_GET['dsic_bulk_skipped'] ) ? absint( $_GET['dsic_bulk_skipped'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$errors    = isset( $_GET['dsic_bulk_errors'] ) ? absint( $_GET['dsic_bulk_errors'] ) : 0;

		$action_labels = array(
			'dsic_request_verification' => __( 'Verification requested', 'droix-stripe-id-check' ),
			'dsic_resend_verification'  => __( 'Verification email resent', 'droix-stripe-id-check' ),
			'dsic_cancel_verification'  => __( 'Verification cancelled', 'droix-stripe-id-check' ),
		);

		$action_label = $action_labels[ $action ] ?? __( 'Action completed', 'droix-stripe-id-check' );

		$messages = array();

		if ( $processed > 0 ) {
			$messages[] = sprintf(
				/* translators: 1: Action label, 2: Number of orders */
				_n(
					'%1$s for %2$d order.',
					'%1$s for %2$d orders.',
					$processed,
					'droix-stripe-id-check'
				),
				$action_label,
				$processed
			);
		}

		if ( $skipped > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: Number of orders */
				_n(
					'%d order skipped (not applicable).',
					'%d orders skipped (not applicable).',
					$skipped,
					'droix-stripe-id-check'
				),
				$skipped
			);
		}

		if ( $errors > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: Number of orders */
				_n(
					'%d order failed.',
					'%d orders failed.',
					$errors,
					'droix-stripe-id-check'
				),
				$errors
			);
		}

		if ( empty( $messages ) ) {
			return;
		}

		$notice_class = $errors > 0 ? 'notice-warning' : 'notice-success';
		?>
		<div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible">
			<p><strong><?php esc_html_e( 'ID Verification:', 'droix-stripe-id-check' ); ?></strong> <?php echo esc_html( implode( ' ', $messages ) ); ?></p>
		</div>
		<?php
	}
}
