<?php
/**
 * Verification Request Email.
 *
 * Sent to customers when admin requests identity verification.
 *
 * @package    DSIC
 * @subpackage DSIC/emails
 * @since      0.0.1
 * @since      1.4.0 Updated to use database templates instead of PHP template files.
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'DSIC_Email_Verification_Request' ) ) :

	/**
	 * Class DSIC_Email_Verification_Request
	 *
	 * @since 0.0.1
	 */
	class DSIC_Email_Verification_Request extends WC_Email {

		/**
		 * Verification URL for this email.
		 *
		 * @since 0.0.1
		 * @var string
		 */
		public string $verification_url = '';

		/**
		 * Constructor.
		 *
		 * @since 0.0.1
		 */
		public function __construct() {
			$this->id             = 'dsic_verification_request';
			$this->customer_email = true;
			$this->title          = __( 'ID Verification Request', 'droix-stripe-id-check' );
			$this->description    = __( 'This email is sent to customers when identity verification is requested for their order.', 'droix-stripe-id-check' );

			$this->placeholders = array(
				'{order_date}'   => '',
				'{order_number}' => '',
			);

			// Triggers.
			add_action( 'dsic_verification_requested', array( $this, 'trigger' ), 10, 2 );

			// Parent constructor.
			parent::__construct();
		}

		/**
		 * Get email subject.
		 *
		 * @since 0.0.1
		 * @return string
		 */
		public function get_default_subject(): string {
			return DSIC_Email_Helper::get_default_subject( 'verification_request' );
		}

		/**
		 * Get email heading.
		 *
		 * @since 0.0.1
		 * @return string
		 */
		public function get_default_heading(): string {
			return DSIC_Email_Helper::get_default_heading( 'verification_request' );
		}

		/**
		 * Trigger the email.
		 *
		 * @since 0.0.1
		 * @param int    $order_id         WooCommerce order ID.
		 * @param string $verification_url URL for customer to verify.
		 * @return void
		 */
		public function trigger( int $order_id, string $verification_url = '' ): void {
			$this->setup_locale();

			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				$this->restore_locale();
				return;
			}

			$this->object                         = $order;
			$this->recipient                      = $order->get_billing_email();
			$this->verification_url               = $verification_url;
			$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
			$this->placeholders['{order_number}'] = $order->get_order_number();

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

				DSIC_Logger::info( 'Verification request email sent to ' . $this->get_recipient() . ' for order #' . $order_id );
			}

			$this->restore_locale();
		}

		/**
		 * Get email subject from database template.
		 *
		 * @since 1.4.0
		 * @return string
		 */
		public function get_subject(): string {
			$template = DSIC_Email_Helper::get_translated_template( 'verification_request', $this->object );
			$subject  = DSIC_Email_Helper::process_placeholders( $template['subject'], $this->object );
			return $this->format_string( $subject );
		}

		/**
		 * Get email heading from database template.
		 *
		 * @since 1.4.0
		 * @return string
		 */
		public function get_heading(): string {
			$template = DSIC_Email_Helper::get_translated_template( 'verification_request', $this->object );
			$heading  = DSIC_Email_Helper::process_placeholders( $template['heading'], $this->object );
			return $this->format_string( $heading );
		}

		/**
		 * Get content HTML from database template.
		 *
		 * @since 0.0.1
		 * @since 1.4.0 Updated to use database template.
		 * @return string
		 */
		public function get_content_html(): string {
			$email = DSIC_Email_Helper::build_email(
				'verification_request',
				$this->object,
				$this->verification_url
			);
			return $email['content'];
		}

		/**
		 * Get content plain from database template.
		 *
		 * @since 0.0.1
		 * @since 1.4.0 Updated to use database template.
		 * @return string
		 */
		public function get_content_plain(): string {
			$template = DSIC_Email_Helper::get_translated_template( 'verification_request', $this->object );
			$body     = DSIC_Email_Helper::process_placeholders( $template['body'], $this->object );
			$body     = DSIC_Shortcodes::process( $body, $this->object, $this->verification_url );
			return DSIC_Email_Helper::html_to_plain( $body );
		}

		/**
		 * Default additional content.
		 *
		 * @since 0.0.1
		 * @return string
		 */
		public function get_default_additional_content(): string {
			return __( 'If you have any questions about this verification request, please contact us.', 'droix-stripe-id-check' );
		}

		/**
		 * Initialize settings form fields.
		 *
		 * Note: These settings are shown in WooCommerce > Settings > Emails.
		 * The main template editing is done in plugin settings.
		 *
		 * @since 0.0.1
		 * @return void
		 */
		public function init_form_fields(): void {
			/* translators: %s: list of placeholders */
			$placeholder_text = sprintf( __( 'Available placeholders: %s', 'droix-stripe-id-check' ), '<code>' . implode( '</code>, <code>', array_keys( $this->placeholders ) ) . '</code>' );

			$this->form_fields = array(
				'enabled'    => array(
					'title'   => __( 'Enable/Disable', 'droix-stripe-id-check' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable this email notification', 'droix-stripe-id-check' ),
					'default' => 'yes',
				),
				'email_type' => array(
					'title'       => __( 'Email type', 'droix-stripe-id-check' ),
					'type'        => 'select',
					'description' => __( 'Choose which format of email to send.', 'droix-stripe-id-check' ),
					'default'     => 'html',
					'class'       => 'email_type wc-enhanced-select',
					'options'     => $this->get_email_type_options(),
					'desc_tip'    => true,
				),
			);
		}
	}

endif;
