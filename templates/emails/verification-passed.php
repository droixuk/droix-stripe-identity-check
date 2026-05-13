<?php
/**
 * Verification Passed Email Template (HTML).
 *
 * @package    DSIC
 * @subpackage DSIC/templates/emails
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
	<?php esc_html_e( 'Great news! Your identity verification has been successfully completed.', 'droix-stripe-id-check' ); ?>
</p>

<p>
	<?php
	printf(
		/* translators: %s: Order number */
		esc_html__( 'Your order #%s will now be processed and you will receive a shipping confirmation email once it has been dispatched.', 'droix-stripe-id-check' ),
		esc_html( $order->get_order_number() )
	);
	?>
</p>

<div style="background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 15px; margin: 20px 0;">
	<p style="margin: 0; color: #155724;">
		<strong><?php esc_html_e( 'Verification Status:', 'droix-stripe-id-check' ); ?></strong>
		<?php esc_html_e( 'Verified', 'droix-stripe-id-check' ); ?>
	</p>
</div>

<p>
	<?php esc_html_e( 'Thank you for completing this security step. We appreciate your patience and understanding.', 'droix-stripe-id-check' ); ?>
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
