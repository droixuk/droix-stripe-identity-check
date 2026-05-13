<?php
/**
 * CRM Notification Email Template (Plain Text).
 *
 * @package    DSIC
 * @subpackage DSIC/templates/emails/plain
 * @since      0.0.1
 *
 * @var WC_Order $order               Order object.
 * @var string   $email_heading       Email heading.
 * @var string   $verification_status Verification status.
 * @var string   $session_id          Stripe session ID.
 * @var array    $details             Additional details.
 * @var string   $additional_content  Additional content.
 * @var bool     $sent_to_admin       Whether sent to admin.
 * @var bool     $plain_text          Whether plain text.
 * @var WC_Email $email               Email object.
 */

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

printf(
	/* translators: %s: Order number */
	esc_html__( 'ID verification status update for order #%s.', 'droix-stripe-id-check' ),
	esc_html( $order->get_order_number() )
);
echo "\n\n";

echo esc_html__( 'Status:', 'droix-stripe-id-check' ) . ' ' . esc_html( strtoupper( $verification_status ) );
echo "\n\n";

if ( 'auto_triggered' === $verification_status ) {
	echo "========================================\n";
	echo esc_html__( 'AUTOMATIC VERIFICATION TRIGGERED', 'droix-stripe-id-check' );
	echo "\n========================================\n\n";

	if ( ! empty( $details['reason'] ) && 'different_shipping_address' === $details['reason'] ) {
		echo esc_html__( 'Reason: Customer selected a different shipping address from their billing address.', 'droix-stripe-id-check' );
	} else {
		echo esc_html__( 'Reason: Automatically triggered by the system.', 'droix-stripe-id-check' );
	}
	echo "\n\n";

	if ( ! empty( $details['order_edit_url'] ) ) {
		echo esc_html__( 'Review Order:', 'droix-stripe-id-check' ) . ' ' . esc_url( $details['order_edit_url'] );
		echo "\n\n";
	}
}

echo "----------------------------------------\n";
echo esc_html__( 'CUSTOMER INFORMATION', 'droix-stripe-id-check' );
echo "\n----------------------------------------\n\n";

echo esc_html__( 'Name:', 'droix-stripe-id-check' ) . ' ' . esc_html( $order->get_formatted_billing_full_name() ) . "\n";
echo esc_html__( 'Email:', 'droix-stripe-id-check' ) . ' ' . esc_html( $order->get_billing_email() ) . "\n";
echo esc_html__( 'Phone:', 'droix-stripe-id-check' ) . ' ' . esc_html( $order->get_billing_phone() ) . "\n";
echo esc_html__( 'Order Total:', 'droix-stripe-id-check' ) . ' ' . wp_strip_all_tags( $order->get_formatted_order_total() ) . "\n";
echo esc_html__( 'Order Date:', 'droix-stripe-id-check' ) . ' ' . esc_html( wc_format_datetime( $order->get_date_created() ) ) . "\n";
echo "\n";

if ( ! empty( $session_id ) ) {
	echo "----------------------------------------\n";
	echo esc_html__( 'STRIPE DETAILS', 'droix-stripe-id-check' );
	echo "\n----------------------------------------\n\n";

	echo esc_html__( 'Session ID:', 'droix-stripe-id-check' ) . ' ' . esc_html( $session_id ) . "\n";

	$stripe_url = $email->get_stripe_dashboard_url();
	if ( $stripe_url ) {
		echo esc_html__( 'View in Stripe:', 'droix-stripe-id-check' ) . ' ' . esc_url( $stripe_url ) . "\n";
	}
	echo "\n";
}

if ( ! empty( $details ) ) {
	echo "----------------------------------------\n";
	echo esc_html__( 'ADDITIONAL DETAILS', 'droix-stripe-id-check' );
	echo "\n----------------------------------------\n\n";

	foreach ( $details as $key => $value ) {
		$label = ucwords( str_replace( '_', ' ', $key ) );
		$value_str = is_array( $value ) ? wp_json_encode( $value ) : $value;
		echo esc_html( $label ) . ': ' . esc_html( $value_str ) . "\n";
	}
	echo "\n";
}

echo "----------------------------------------\n";
echo esc_html__( 'View Order in Admin:', 'droix-stripe-id-check' ) . ' ' . esc_url( $order->get_edit_order_url() );
echo "\n----------------------------------------\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
