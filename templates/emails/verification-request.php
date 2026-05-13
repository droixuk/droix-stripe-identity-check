<?php
/**
 * Verification Request Email Template (HTML).
 *
 * @package    DSIC
 * @subpackage DSIC/templates/emails
 * @since      0.0.1
 *
 * @var WC_Order $order              Order object.
 * @var string   $email_heading      Email heading.
 * @var string   $verification_url   Verification URL.
 * @var string   $additional_content Additional content.
 * @var bool     $sent_to_admin      Whether sent to admin.
 * @var bool     $plain_text         Whether plain text.
 * @var WC_Email $email              Email object.
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
		esc_html__( 'To complete your order #%s, we need to verify your identity. This is a quick and secure process that helps protect both you and our business.', 'droix-stripe-id-check' ),
		esc_html( $order->get_order_number() )
	);
	?>
</p>

<p>
	<?php esc_html_e( 'What you will need:', 'droix-stripe-id-check' ); ?>
</p>

<ul>
	<li><?php esc_html_e( 'A valid government-issued ID (passport, driving license, or national ID card)', 'droix-stripe-id-check' ); ?></li>
	<li><?php esc_html_e( 'A device with a camera (smartphone or computer)', 'droix-stripe-id-check' ); ?></li>
	<li><?php esc_html_e( 'About 2-3 minutes of your time', 'droix-stripe-id-check' ); ?></li>
</ul>

<?php if ( ! empty( $verification_url ) ) : ?>
	<p style="margin: 30px 0; text-align: center;">
		<a href="<?php echo esc_url( $verification_url ); ?>"
		   style="background-color: #7f54b3; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">
			<?php esc_html_e( 'Verify My Identity', 'droix-stripe-id-check' ); ?>
		</a>
	</p>
	<p style="text-align: center; color: #666666; font-size: 12px;">
		<?php esc_html_e( 'Or copy and paste this link into your browser:', 'droix-stripe-id-check' ); ?><br>
		<a href="<?php echo esc_url( $verification_url ); ?>"><?php echo esc_url( $verification_url ); ?></a>
	</p>
<?php endif; ?>

<p>
	<?php esc_html_e( 'The verification process is powered by Stripe, a trusted payment provider. Your personal data is handled securely and in accordance with privacy regulations.', 'droix-stripe-id-check' ); ?>
</p>

<h2><?php esc_html_e( 'Order Details', 'droix-stripe-id-check' ); ?></h2>

<?php
/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::customer_details() Shows customer details.
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );
?>

<?php if ( $additional_content ) : ?>
	<p><?php echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); ?></p>
<?php endif; ?>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer.
 */
do_action( 'woocommerce_email_footer', $email );
