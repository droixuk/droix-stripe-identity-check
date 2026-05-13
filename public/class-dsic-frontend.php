<?php
/**
 * Frontend functionality class.
 *
 * Handles customer-facing features including order verification display.
 *
 * @package    DSIC
 * @subpackage DSIC/public
 * @since      0.4.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Frontend
 *
 * @since 0.4.0
 */
class DSIC_Frontend {

	/**
	 * Initialize hooks.
	 *
	 * @since 0.4.0
	 */
	public function __construct() {
		// Only load on frontend.
		if ( is_admin() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'woocommerce_view_order', array( $this, 'display_verification_section' ), 5 );

		// Guest order support via order-received page.
		add_action( 'woocommerce_thankyou', array( $this, 'display_verification_section_thankyou' ), 5 );
	}

	/**
	 * Enqueue frontend styles.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function enqueue_styles(): void {
		// Only load on relevant pages.
		if ( ! $this->is_verification_page() ) {
			return;
		}

		wp_enqueue_style(
			'dsic-frontend',
			DSIC_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			DSIC_VERSION
		);
	}

	/**
	 * Check if current page should show verification.
	 *
	 * @since 0.4.0
	 * @return bool
	 */
	private function is_verification_page(): bool {
		return is_wc_endpoint_url( 'view-order' ) || is_wc_endpoint_url( 'order-received' );
	}

	/**
	 * Display verification section on order view page.
	 *
	 * @since 0.4.0
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function display_verification_section( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Only show if verification was requested.
		$status = $order->get_meta( '_dsic_verification_status' );

		if ( empty( $status ) ) {
			return;
		}

		$this->render_verification_template( $order );
	}

	/**
	 * Display verification on thank you page (for guests).
	 *
	 * @since 0.4.0
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function display_verification_section_thankyou( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Only show if verification was requested.
		$status = $order->get_meta( '_dsic_verification_status' );

		if ( empty( $status ) ) {
			return;
		}

		$this->render_verification_template( $order );
	}

	/**
	 * Render verification template.
	 *
	 * @since 0.4.0
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	private function render_verification_template( WC_Order $order ): void {
		$status        = $order->get_meta( '_dsic_verification_status' );
		$requested_at  = $order->get_meta( '_dsic_verification_requested' );
		$completed_at  = $order->get_meta( '_dsic_verification_completed' );
		$error_message = $order->get_meta( '_dsic_verification_error_msg' );
		$token         = $order->get_meta( '_dsic_verification_token' );

		// Build verification URL.
		$verification_url = '';
		if ( 'pending' === $status && $token ) {
			$verification_url = add_query_arg(
				array(
					'wc-api'   => 'dsic_initiate_verification',
					'order_id' => $order->get_id(),
					'token'    => $token,
				),
				home_url( '/' )
			);
		}

		// Load template.
		wc_get_template(
			'myaccount/verification-status.php',
			array(
				'order'            => $order,
				'status'           => $status,
				'requested_at'     => $requested_at,
				'completed_at'     => $completed_at,
				'error_message'    => $error_message,
				'verification_url' => $verification_url,
			),
			'',
			DSIC_PLUGIN_DIR . 'templates/'
		);
	}

	/**
	 * Get customer return URL after verification.
	 *
	 * @since 0.4.0
	 * @param WC_Order $order Order object.
	 * @return string Return URL.
	 */
	public static function get_customer_return_url( WC_Order $order ): string {
		if ( $order->get_user_id() ) {
			// Logged-in customer: redirect to order view page.
			return $order->get_view_order_url();
		}

		// Guest: redirect to order received page with key.
		return add_query_arg(
			array(
				'key' => $order->get_order_key(),
			),
			$order->get_checkout_order_received_url()
		);
	}
}
