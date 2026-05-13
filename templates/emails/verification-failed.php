<?php
/**
 * Verification Failed Email Template (HTML).
 *
 * @package    DSIC
 * @subpackage DSIC/templates/emails
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
		esc_html__( 'Unfortunately, we were unable to verify your identity for order #%s.', 'droix-stripe-id-check' ),
		esc_html( $order->get_order_number() )
	);
	?>
</p>

<?php if ( ! empty( $failure_reason ) ) : ?>
	<div style="background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 15px; margin: 20px 0;">
		<p style="margin: 0; color: #721c24;">
			<strong><?php esc_html_e( 'Reason:', 'droix-stripe-id-check' ); ?></strong>
			<?php echo esc_html( $failure_reason ); ?>
		</p>
	</div>
<?php endif; ?>

<p>
	<?php esc_html_e( 'This can happen for several reasons:', 'droix-stripe-id-check' ); ?>
</p>

<ul>
	<li><?php esc_html_e( 'The document image was not clear enough', 'droix-stripe-id-check' ); ?></li>
	<li><?php esc_html_e( 'The selfie did not match the document photo', 'droix-stripe-id-check' ); ?></li>
	<li><?php esc_html_e( 'The document type was not supported', 'droix-stripe-id-check' ); ?></li>
	<li><?php esc_html_e( 'The verification process was not completed', 'droix-stripe-id-check' ); ?></li>
</ul>

<p>
	<?php esc_html_e( 'Please contact us and we will help you resolve this issue. You may be asked to try the verification again or provide alternative documentation.', 'droix-stripe-id-check' ); ?>
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
