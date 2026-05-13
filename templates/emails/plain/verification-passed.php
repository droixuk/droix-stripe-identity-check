<?php
/**
 * Verification Passed Email Template (Plain Text).
 *
 * @package    DSIC
 * @subpackage DSIC/templates/emails/plain
 * @since      0.0.1
 *
 * @var WC_Order $order              Order object.
 * @var string   $email_heading      Email heading.
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

echo esc_html__( 'Great news! Your identity verification has been successfully completed.', 'droix-stripe-id-check' );
echo "\n\n";

printf(
	/* translators: %s: Order number */
	esc_html__( 'Your order #%s will now be processed and you will receive a shipping confirmation email once it has been dispatched.', 'droix-stripe-id-check' ),
	esc_html( $order->get_order_number() )
);
echo "\n\n";

echo esc_html__( 'Verification Status: VERIFIED', 'droix-stripe-id-check' );
echo "\n\n";

echo esc_html__( 'Thank you for completing this security step. We appreciate your patience and understanding.', 'droix-stripe-id-check' );
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
