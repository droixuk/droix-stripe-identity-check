<?php
/**
 * Data Redaction Email Template (Plain Text).
 *
 * @package    DSIC
 * @subpackage DSIC/templates/emails/plain
 * @since      0.5.6
 *
 * @var WC_Order $order              Order object.
 * @var string   $email_heading      Email heading.
 * @var string   $additional_content Additional content.
 * @var bool     $sent_to_admin      Whether sent to admin.
 * @var bool     $plain_text         Whether plain text.
 * @var WC_Email $email              Email object.
 * @var string   $redaction_status   Status: 'requested' or 'completed'.
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
	esc_html__( 'This is to confirm that your ID verification data for order #%s has been deleted from Stripe\'s systems.', 'droix-stripe-id-check' ),
	esc_html( $order->get_order_number() )
);
echo "\n\n";

echo esc_html__( 'WHAT WAS DELETED:', 'droix-stripe-id-check' );
echo "\n";
echo "- " . esc_html__( 'ID document images (passport, driving licence, or ID card)', 'droix-stripe-id-check' ) . "\n";
echo "- " . esc_html__( 'Selfie photo used for verification', 'droix-stripe-id-check' ) . "\n";
echo "- " . esc_html__( 'Any extracted personal information from the documents', 'droix-stripe-id-check' ) . "\n";
echo "\n";

echo esc_html__( 'This action was taken to protect your privacy. Your order information remains on file with us, but all sensitive identity verification data has been permanently removed from Stripe.', 'droix-stripe-id-check' );
echo "\n\n";

echo esc_html__( 'NOTE: We never stored your ID documents on our own servers. All verification was handled directly by Stripe, a trusted payment processor.', 'droix-stripe-id-check' );
echo "\n\n";

echo esc_html__( 'For more information about how Stripe handles your data, visit:', 'droix-stripe-id-check' );
echo "\nhttps://support.stripe.com/questions/managing-your-id-verification-information\n\n";

if ( $additional_content ) {
	echo "----------------------------------------\n";
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n----------------------------------------\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
