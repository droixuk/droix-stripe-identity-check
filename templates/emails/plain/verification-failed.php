<?php
/**
 * Verification Failed Email Template (Plain Text).
 *
 * @package    DSIC
 * @subpackage DSIC/templates/emails/plain
 * @since      0.0.1
 *
 * @var WC_Order $order              Order object.
 * @var string   $email_heading      Email heading.
 * @var string   $failure_reason     Reason for failure.
 * @var string   $additional_content Additional content.
 * @var bool     $sent_to_admin      Whether sent to admin.
 * @var bool     $plain_text         Whether plain text.
 * @var WC_Email $email              Email object.
 */

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

printf(
	/* translators: %s: Customer first name */
	esc_html__( 'Hi %s,', 'droix-stripe-id-check' ),
	esc_html( $order->get_billing_first_name() )
);
echo "\n\n";

printf(
	/* translators: %s: Order number */
	esc_html__( 'Unfortunately, we were unable to verify your identity for order #%s.', 'droix-stripe-id-check' ),
	esc_html( $order->get_order_number() )
);
echo "\n\n";

if ( ! empty( $failure_reason ) ) {
	echo esc_html__( 'Reason:', 'droix-stripe-id-check' ) . ' ' . esc_html( $failure_reason );
	echo "\n\n";
}

echo esc_html__( 'This can happen for several reasons:', 'droix-stripe-id-check' ) . "\n";
echo "- " . esc_html__( 'The document image was not clear enough', 'droix-stripe-id-check' ) . "\n";
echo "- " . esc_html__( 'The selfie did not match the document photo', 'droix-stripe-id-check' ) . "\n";
echo "- " . esc_html__( 'The document type was not supported', 'droix-stripe-id-check' ) . "\n";
echo "- " . esc_html__( 'The verification process was not completed', 'droix-stripe-id-check' ) . "\n\n";

echo esc_html__( 'Please contact us and we will help you resolve this issue. You may be asked to try the verification again or provide alternative documentation.', 'droix-stripe-id-check' );
echo "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__( 'ORDER DETAILS', 'droix-stripe-id-check' );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n";

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::customer_details() Shows customer details.
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n";

if ( $additional_content ) {
	echo "----------------------------------------\n";
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n----------------------------------------\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
