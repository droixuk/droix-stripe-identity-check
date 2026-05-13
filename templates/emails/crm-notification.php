<?php
/**
 * CRM Notification Email Template (HTML).
 *
 * @package    DSIC
 * @subpackage DSIC/templates/emails
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

// Determine status styling.
$status_styles = array(
	'verified'       => array(
		'bg'    => '#d4edda',
		'border' => '#c3e6cb',
		'color' => '#155724',
		'label' => __( 'Verified', 'droix-stripe-id-check' ),
	),
	'failed'         => array(
		'bg'    => '#f8d7da',
		'border' => '#f5c6cb',
		'color' => '#721c24',
		'label' => __( 'Failed', 'droix-stripe-id-check' ),
	),
	'pending'        => array(
		'bg'    => '#fff3cd',
		'border' => '#ffeeba',
		'color' => '#856404',
		'label' => __( 'Pending', 'droix-stripe-id-check' ),
	),
	'auto_triggered' => array(
		'bg'    => '#cce5ff',
		'border' => '#b8daff',
		'color' => '#004085',
		'label' => __( 'Auto-Triggered', 'droix-stripe-id-check' ),
	),
);

$style = $status_styles[ $verification_status ] ?? $status_styles['pending'];

/*
 * @hooked WC_Emails::email_header() Output the email header.
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<?php if ( ! empty( $details['already_processed'] ) ) : ?>
<div style="background:#b91c1c;color:#fff;padding:16px 20px;border-radius:6px;margin-bottom:24px;">
	<strong style="font-size:16px;">!!! IMPORTANT — ORDER MAY ALREADY BE DISPATCHED !!!</strong><br><br>
	This early fraud warning was received <em>after</em> the order had already reached
	<strong><?php echo esc_html( ucfirst( $details['order_status'] ?? 'processing' ) ); ?></strong> status.
	The order may already be packed or dispatched. Please check your fulfilment system
	immediately and take action before the order ships (or recall it if it already has).
</div>
<?php endif; ?>

<p>
	<?php
	printf(
		/* translators: %s: Order number */
		esc_html__( 'ID verification status update for order #%s.', 'droix-stripe-id-check' ),
		esc_html( $order->get_order_number() )
	);
	?>
</p>

<div style="background-color: <?php echo esc_attr( $style['bg'] ); ?>; border: 1px solid <?php echo esc_attr( $style['border'] ); ?>; border-radius: 4px; padding: 15px; margin: 20px 0;">
	<p style="margin: 0; color: <?php echo esc_attr( $style['color'] ); ?>; font-size: 18px;">
		<strong><?php esc_html_e( 'Status:', 'droix-stripe-id-check' ); ?></strong>
		<?php echo esc_html( $style['label'] ); ?>
	</p>
</div>

<?php if ( 'auto_triggered' === $verification_status ) : ?>
	<div style="background-color: #e7f3ff; border: 1px solid #b8daff; border-radius: 4px; padding: 20px; margin: 20px 0;">
		<h3 style="margin: 0 0 10px; color: #004085;"><?php esc_html_e( 'Automatic Verification Triggered', 'droix-stripe-id-check' ); ?></h3>
		<p style="margin: 0 0 15px; color: #004085;">
			<?php
			if ( ! empty( $details['reason'] ) && 'different_shipping_address' === $details['reason'] ) {
				esc_html_e( 'This verification was automatically triggered because the customer selected a different shipping address from their billing address.', 'droix-stripe-id-check' );
			} else {
				esc_html_e( 'This verification was automatically triggered by the system.', 'droix-stripe-id-check' );
			}
			?>
		</p>
		<?php if ( ! empty( $details['order_edit_url'] ) ) : ?>
			<p style="margin: 0;">
				<a href="<?php echo esc_url( $details['order_edit_url'] ); ?>"
				   style="background-color: #004085; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">
					<?php esc_html_e( 'Review Order Now', 'droix-stripe-id-check' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div>
<?php endif; ?>

<h3><?php esc_html_e( 'Customer Information', 'droix-stripe-id-check' ); ?></h3>

<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #e5e5e5; margin-bottom: 20px;" border="1">
	<tr>
		<th style="text-align: left; background: #f8f8f8; width: 30%;"><?php esc_html_e( 'Name', 'droix-stripe-id-check' ); ?></th>
		<td><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></td>
	</tr>
	<tr>
		<th style="text-align: left; background: #f8f8f8;"><?php esc_html_e( 'Email', 'droix-stripe-id-check' ); ?></th>
		<td><a href="mailto:<?php echo esc_attr( $order->get_billing_email() ); ?>"><?php echo esc_html( $order->get_billing_email() ); ?></a></td>
	</tr>
	<tr>
		<th style="text-align: left; background: #f8f8f8;"><?php esc_html_e( 'Phone', 'droix-stripe-id-check' ); ?></th>
		<td><?php echo esc_html( $order->get_billing_phone() ); ?></td>
	</tr>
	<tr>
		<th style="text-align: left; background: #f8f8f8;"><?php esc_html_e( 'Order Total', 'droix-stripe-id-check' ); ?></th>
		<td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
	</tr>
	<tr>
		<th style="text-align: left; background: #f8f8f8;"><?php esc_html_e( 'Order Date', 'droix-stripe-id-check' ); ?></th>
		<td><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
	</tr>
</table>

<?php if ( ! empty( $session_id ) ) : ?>
	<h3><?php esc_html_e( 'Stripe Details', 'droix-stripe-id-check' ); ?></h3>
	<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #e5e5e5; margin-bottom: 20px;" border="1">
		<tr>
			<th style="text-align: left; background: #f8f8f8; width: 30%;"><?php esc_html_e( 'Session ID', 'droix-stripe-id-check' ); ?></th>
			<td><code><?php echo esc_html( $session_id ); ?></code></td>
		</tr>
		<tr>
			<th style="text-align: left; background: #f8f8f8;"><?php esc_html_e( 'View in Stripe', 'droix-stripe-id-check' ); ?></th>
			<td>
				<?php
				$stripe_url = $email->get_stripe_dashboard_url();
				if ( $stripe_url ) :
					?>
					<a href="<?php echo esc_url( $stripe_url ); ?>"><?php esc_html_e( 'Open Stripe Dashboard', 'droix-stripe-id-check' ); ?></a>
				<?php endif; ?>
			</td>
		</tr>
	</table>
<?php endif; ?>

<?php if ( ! empty( $details ) ) : ?>
	<h3><?php esc_html_e( 'Additional Details', 'droix-stripe-id-check' ); ?></h3>
	<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #e5e5e5; margin-bottom: 20px;" border="1">
		<?php foreach ( $details as $key => $value ) : ?>
			<tr>
				<th style="text-align: left; background: #f8f8f8; width: 30%;"><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></th>
				<td><?php echo esc_html( is_array( $value ) ? wp_json_encode( $value ) : $value ); ?></td>
			</tr>
		<?php endforeach; ?>
	</table>
<?php endif; ?>

<p style="margin-top: 20px;">
	<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"
	   style="background-color: #7f54b3; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">
		<?php esc_html_e( 'View Order in Admin', 'droix-stripe-id-check' ); ?>
	</a>
</p>

<?php if ( $additional_content ) : ?>
	<p><?php echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); ?></p>
<?php endif; ?>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer.
 */
do_action( 'woocommerce_email_footer', $email );
