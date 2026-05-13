<?php
/**
 * Data Redaction Email Template (HTML).
 *
 * @package    DSIC
 * @subpackage DSIC/templates/emails
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

/*
 * @hooked WC_Emails::email_header() Output the email header.
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
	<?php
	printf(
		/* translators: %s: Customer first name */
		esc_html__( 'Hi %s,', 'droix-stripe-id-check' ),
		esc_html( $order->get_billing_first_name() )
	);
	?>
</p>

<p>
	<?php
	printf(
		/* translators: %s: Order number */
		esc_html__( 'This is to confirm that your ID verification data for order #%s has been deleted from Stripe\'s systems.', 'droix-stripe-id-check' ),
		esc_html( $order->get_order_number() )
	);
	?>
</p>

<div style="background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 15px; margin: 20px 0;">
	<p style="margin: 0 0 10px; color: #155724;">
		<strong><?php esc_html_e( 'What was deleted:', 'droix-stripe-id-check' ); ?></strong>
	</p>
	<ul style="margin: 0; padding-left: 20px; color: #155724;">
		<li><?php esc_html_e( 'ID document images (passport, driving licence, or ID card)', 'droix-stripe-id-check' ); ?></li>
		<li><?php esc_html_e( 'Selfie photo used for verification', 'droix-stripe-id-check' ); ?></li>
		<li><?php esc_html_e( 'Any extracted personal information from the documents', 'droix-stripe-id-check' ); ?></li>
	</ul>
</div>

<p>
	<?php esc_html_e( 'This action was taken to protect your privacy. Your order information remains on file with us, but all sensitive identity verification data has been permanently removed from Stripe.', 'droix-stripe-id-check' ); ?>
</p>

<div style="background-color: #e7f3ff; border: 1px solid #b8daff; border-radius: 4px; padding: 15px; margin: 20px 0;">
	<p style="margin: 0; color: #004085;">
		<strong><?php esc_html_e( 'Note:', 'droix-stripe-id-check' ); ?></strong>
		<?php esc_html_e( 'We never stored your ID documents on our own servers. All verification was handled directly by Stripe, a trusted payment processor. For more information about how Stripe handles your data, visit:', 'droix-stripe-id-check' ); ?>
		<a href="https://support.stripe.com/questions/managing-your-id-verification-information" style="color: #004085;"><?php esc_html_e( 'Stripe\'s Privacy Information', 'droix-stripe-id-check' ); ?></a>
	</p>
</div>

<?php if ( $additional_content ) : ?>
	<p><?php echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); ?></p>
<?php endif; ?>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer.
 */
do_action( 'woocommerce_email_footer', $email );
