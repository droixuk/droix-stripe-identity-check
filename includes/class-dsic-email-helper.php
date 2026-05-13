<?php
/**
 * Email Helper Class.
 *
 * Provides helper methods for building email content from database templates.
 *
 * @package    DSIC
 * @subpackage DSIC/includes
 * @since      1.4.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Email_Helper
 *
 * Static helper methods for email template processing.
 *
 * @since 1.4.0
 */
class DSIC_Email_Helper {

	/**
	 * Email type mapping from class ID to option name.
	 *
	 * @since 1.4.0
	 * @var array
	 */
	private static array $email_type_map = array(
		'dsic_verification_request' => 'verification_request',
		'dsic_verification_passed'  => 'verification_passed',
		'dsic_verification_failed'  => 'verification_failed',
		'dsic_data_redaction'       => 'data_redaction',
		'dsic_crm_notification'     => 'crm_notification',
	);

	/**
	 * Get email template from database.
	 *
	 * @since 1.4.0
	 * @param string $type Email type (e.g., 'verification_request').
	 * @return array Template with subject, heading, body, enabled keys.
	 */
	public static function get_template( string $type ): array {
		return array(
			'enabled' => self::get_bool_option( 'dsic_email_' . $type . '_enabled', true ),
			'subject' => get_option( 'dsic_email_' . $type . '_subject', self::get_default_subject( $type ) ),
			'heading' => get_option( 'dsic_email_' . $type . '_heading', self::get_default_heading( $type ) ),
			'body'    => get_option( 'dsic_email_' . $type . '_body', self::get_default_body( $type ) ),
		);
	}

	/**
	 * Get translated email template for a specific order.
	 *
	 * @since 1.4.0
	 * @param string       $type  Email type.
	 * @param WC_Order|int $order Order object or ID.
	 * @return array Template with translated strings.
	 */
	public static function get_translated_template( string $type, $order ): array {
		$template = self::get_template( $type );

		// If WPML/Polylang is not active, return as-is.
		if ( ! class_exists( 'DSIC_WPML' ) || ! DSIC_WPML::is_multilingual() ) {
			return $template;
		}

		// Get translated versions for each field.
		$template['subject'] = DSIC_WPML::get_translated_email_field( $type, 'subject', $order );
		$template['heading'] = DSIC_WPML::get_translated_email_field( $type, 'heading', $order );
		$template['body']    = DSIC_WPML::get_translated_email_field( $type, 'body', $order );

		// Use defaults if translations are empty.
		if ( empty( $template['subject'] ) ) {
			$template['subject'] = self::get_default_subject( $type );
		}
		if ( empty( $template['heading'] ) ) {
			$template['heading'] = self::get_default_heading( $type );
		}
		if ( empty( $template['body'] ) ) {
			$template['body'] = self::get_default_body( $type );
		}

		return $template;
	}

	/**
	 * Process email placeholders in content.
	 *
	 * @since 1.4.0
	 * @param string        $content Content with placeholders.
	 * @param WC_Order|null $order   Order object.
	 * @return string Processed content.
	 */
	public static function process_placeholders( string $content, ?WC_Order $order ): string {
		$replacements = array(
			'{site_title}'   => get_bloginfo( 'name' ),
			'{site_url}'     => home_url(),
			'{order_number}' => $order ? $order->get_order_number() : '12345',
			'{order_date}'   => $order ? wc_format_datetime( $order->get_date_created() ) : wp_date( get_option( 'date_format' ) ),
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $content );
	}

	/**
	 * Build full HTML email with WooCommerce styling.
	 *
	 * @since 1.4.0
	 * @param string $heading Email heading.
	 * @param string $body    Email body content.
	 * @return string Full HTML email.
	 */
	public static function build_html( string $heading, string $body ): string {
		ob_start();
		wc_get_template( 'emails/email-header.php', array( 'email_heading' => $heading ) );
		echo wp_kses_post( wpautop( $body ) );
		wc_get_template( 'emails/email-footer.php' );
		return ob_get_clean();
	}

	/**
	 * Build complete email content for sending.
	 *
	 * Combines template retrieval, placeholder processing, shortcode execution,
	 * and HTML building into one method.
	 *
	 * @since 1.4.0
	 * @param string       $type                Email type.
	 * @param WC_Order     $order               Order object.
	 * @param string       $verification_url    Optional verification URL.
	 * @param string       $failure_reason      Optional failure reason.
	 * @param string       $verification_result Optional verification result.
	 * @return array Array with 'subject' and 'content' keys.
	 */
	public static function build_email( string $type, WC_Order $order, string $verification_url = '', string $failure_reason = '', string $verification_result = '' ): array {
		// Get translated template.
		$template = self::get_translated_template( $type, $order );

		// Process placeholders.
		$subject = self::process_placeholders( $template['subject'], $order );
		$heading = self::process_placeholders( $template['heading'], $order );
		$body    = self::process_placeholders( $template['body'], $order );

		// Process shortcodes.
		$body = DSIC_Shortcodes::process( $body, $order, $verification_url, $failure_reason, $verification_result );

		// Build full HTML.
		$content = self::build_html( $heading, $body );

		return array(
			'subject' => $subject,
			'heading' => $heading,
			'content' => $content,
		);
	}

	/**
	 * Strip HTML for plain text email version.
	 *
	 * @since 1.4.0
	 * @param string $html HTML content.
	 * @return string Plain text content.
	 */
	public static function html_to_plain( string $html ): string {
		// Convert <br> and </p> to newlines.
		$text = preg_replace( '/<br\s*\/?>/i', "\n", $html );
		$text = preg_replace( '/<\/p>/i', "\n\n", $text );

		// Convert links to text with URL.
		$text = preg_replace( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/i', '$2 ($1)', $text );

		// Strip remaining HTML.
		$text = wp_strip_all_tags( $text );

		// Clean up whitespace.
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );
		$text = trim( $text );

		return $text;
	}

	/**
	 * Get email type from WC_Email ID.
	 *
	 * @since 1.4.0
	 * @param string $email_id WC_Email ID (e.g., 'dsic_verification_request').
	 * @return string Email type for database options.
	 */
	public static function get_type_from_id( string $email_id ): string {
		return self::$email_type_map[ $email_id ] ?? '';
	}

	/**
	 * Get boolean option value.
	 *
	 * @since 1.4.0
	 * @param string $option  Option name.
	 * @param bool   $default Default value.
	 * @return bool
	 */
	private static function get_bool_option( string $option, bool $default = false ): bool {
		$value = get_option( $option, $default ? '1' : '0' );
		return in_array( $value, array( '1', 'yes', true, 1 ), true );
	}

	/**
	 * Get default email subject.
	 *
	 * @since 1.4.0
	 * @param string $type Email type.
	 * @return string Default subject.
	 */
	public static function get_default_subject( string $type ): string {
		$subjects = array(
			'verification_request' => __( 'ID Verification Required for Order #{order_number}', 'droix-stripe-id-check' ),
			'verification_passed'  => __( 'ID Verification Successful - Order #{order_number}', 'droix-stripe-id-check' ),
			'verification_failed'  => __( 'ID Verification Issue - Order #{order_number}', 'droix-stripe-id-check' ),
			'data_redaction'       => __( 'Your Verification Data Has Been Deleted - Order #{order_number}', 'droix-stripe-id-check' ),
			'crm_notification'     => __( '[Verification Update] Order #{order_number}', 'droix-stripe-id-check' ),
		);

		return $subjects[ $type ] ?? '';
	}

	/**
	 * Get default email heading.
	 *
	 * @since 1.4.0
	 * @param string $type Email type.
	 * @return string Default heading.
	 */
	public static function get_default_heading( string $type ): string {
		$headings = array(
			'verification_request' => __( 'Identity Verification Required', 'droix-stripe-id-check' ),
			'verification_passed'  => __( 'Verification Successful', 'droix-stripe-id-check' ),
			'verification_failed'  => __( 'Verification Could Not Be Completed', 'droix-stripe-id-check' ),
			'data_redaction'       => __( 'Data Deletion Confirmation', 'droix-stripe-id-check' ),
			'crm_notification'     => __( 'ID Verification Status Update', 'droix-stripe-id-check' ),
		);

		return $headings[ $type ] ?? '';
	}

	/**
	 * Get default email body.
	 *
	 * @since 1.4.0
	 * @param string $type Email type.
	 * @return string Default body with shortcodes.
	 */
	public static function get_default_body( string $type ): string {
		switch ( $type ) {
			case 'verification_request':
				return '<p>' . __( 'Hi [dsic_customer_first_name],', 'droix-stripe-id-check' ) . '</p>
<p>' . __( 'Great news — your order #[dsic_order_number] is confirmed! 🎉', 'droix-stripe-id-check' ) . '</p>
<p>' . __( 'As mentioned on the order confirmation page, our payment provider has asked for a quick routine identity check before we dispatch. This is completely standard — it takes about 2 minutes and is powered securely by Stripe.', 'droix-stripe-id-check' ) . '</p>
<p><strong>' . __( 'What you\'ll need:', 'droix-stripe-id-check' ) . '</strong></p>
<ul>
<li>' . __( 'A valid government-issued ID (passport, driving licence, or national ID card)', 'droix-stripe-id-check' ) . '</li>
<li>' . __( 'A device with a camera', 'droix-stripe-id-check' ) . '</li>
<li>' . __( 'About 2 minutes', 'droix-stripe-id-check' ) . '</li>
</ul>
<p style="text-align: center;">[dsic_verification_link]</p>
<p style="text-align: center; font-size: 12px; color: #666;">' . __( 'Or copy and paste this link:', 'droix-stripe-id-check' ) . ' [dsic_verification_url]</p>
<p>' . __( 'Your verification data is handled securely by Stripe and automatically deleted after 30 days. We never store your ID documents on our servers. If you\'d like your data deleted sooner, just reply to this email and we\'ll action it within 24 hours.', 'droix-stripe-id-check' ) . '</p>
<p>' . __( 'Have a question or think this check was triggered by mistake? Simply reply to this email — our team will help you within 24 hours (or within 48 hours over the weekend).', 'droix-stripe-id-check' ) . '</p>';

			case 'verification_passed':
				return '<p>' . __( 'Hi [dsic_customer_first_name],', 'droix-stripe-id-check' ) . '</p>
<p>' . __( 'Great news! Your identity has been successfully verified for order #[dsic_order_number].', 'droix-stripe-id-check' ) . '</p>
<p>' . __( 'Your order is now being processed and will be shipped soon. You will receive a shipping confirmation email once your order is on its way.', 'droix-stripe-id-check' ) . '</p>
<p>' . __( 'Thank you for completing the verification process. Your personal data will be automatically deleted within 30 days in accordance with our privacy policy.', 'droix-stripe-id-check' ) . '</p>
<p>' . __( 'If you have any questions, please contact us.', 'droix-stripe-id-check' ) . '</p>';

			case 'verification_failed':
				return '<p>' . __( 'Hi [dsic_customer_first_name],', 'droix-stripe-id-check' ) . '</p>
<p>' . __( 'No worries — we weren\'t able to complete the identity verification for order #[dsic_order_number] this time, but this is usually easy to sort out.', 'droix-stripe-id-check' ) . '</p>
<p><strong>' . __( 'Reason:', 'droix-stripe-id-check' ) . '</strong> [dsic_failure_reason]</p>
<p>' . __( 'Please do not attempt the verification again just yet. Simply reply to this email with your concerns or questions and our team will help you within 24 hours (or within 48 hours over the weekend).', 'droix-stripe-id-check' ) . '</p>
<p>' . __( 'Your verification data will be automatically deleted within 30 days. If you\'d like it deleted sooner, just let us know and we\'ll action it within 24 hours.', 'droix-stripe-id-check' ) . '</p>';

			case 'data_redaction':
				return '<p>' . __( 'Hi [dsic_customer_first_name],', 'droix-stripe-id-check' ) . '</p>
<p>' . __( 'This email confirms that all personal identification data associated with order #[dsic_order_number] has been permanently deleted from our verification system.', 'droix-stripe-id-check' ) . '</p>
<p>' . __( 'This includes any photos of your ID document and selfie that were submitted during the verification process.', 'droix-stripe-id-check' ) . '</p>
<p>' . __( 'If you have any questions about this, please contact our support team.', 'droix-stripe-id-check' ) . '</p>';

			case 'crm_notification':
				return '<p>' . __( 'Verification status update for order #[dsic_order_number]', 'droix-stripe-id-check' ) . '</p>
<p><strong>' . __( 'Status:', 'droix-stripe-id-check' ) . '</strong> [dsic_verification_result]</p>
<p><strong>' . __( 'Customer:', 'droix-stripe-id-check' ) . '</strong> [dsic_customer_name] ([dsic_customer_email])</p>
<p><strong>' . __( 'Order Total:', 'droix-stripe-id-check' ) . '</strong> [dsic_order_total]</p>
<p><a href="[dsic_order_admin_url]">' . __( 'View Order', 'droix-stripe-id-check' ) . '</a></p>';

			default:
				return '';
		}
	}
}
