<?php
/**
 * Data Redaction Email.
 *
 * Sent to customers when their verification data is being deleted from Stripe.
 *
 * @package    DSIC
 * @subpackage DSIC/emails
 * @since      0.5.6
 * @since      1.4.0 Updated to use database templates instead of PHP template files.
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'DSIC_Email_Data_Redaction' ) ) :

	/**
	 * Class DSIC_Email_Data_Redaction
	 *
	 * @since 0.5.6
	 */
	class DSIC_Email_Data_Redaction extends WC_Email {

		/**
		 * Redaction status.
		 *
		 * @since 0.5.6
		 * @var string
		 */
		public string $redaction_status = 'requested';

		/**
		 * Constructor.
		 *
		 * @since 0.5.6
		 */
		public function __construct() {
			$this->id             = 'dsic_data_redaction';
			$this->customer_email = true;
			$this->title          = __( 'Data Deletion Notification', 'droix-stripe-id-check' );
			$this->description    = __( 'This email is sent to customers when their verification data is being deleted from Stripe.', 'droix-stripe-id-check' );

			$this->placeholders = array(
				'{order_date}'   => '',
				'{order_number}' => '',
			);

			// Triggers.
			add_action( 'dsic_data_redaction_requested', array( $this, 'trigger' ), 10, 1 );
			add_action( 'dsic_data_redaction_completed', array( $this, 'trigger_completed' ), 10, 1 );

			// Parent constructor.
			parent::__construct();
		}

		/**
		 * Get email subject.
		 *
		 * @since 0.5.6
		 * @return string
		 */
		public function get_default_subject(): string {
			return DSIC_Email_Helper::get_default_subject( 'data_redaction' );
		}

		/**
		 * Get email heading.
		 *
		 * @since 0.5.6
		 * @return string
		 */
		public function get_default_heading(): string {
			return DSIC_Email_Helper::get_default_heading( 'data_redaction' );
		}

		/**
		 * Trigger the email when redaction is requested.
		 *
		 * @since 0.5.6
		 * @param int $order_id WooCommerce order ID.
		 * @return void
		 */
		public function trigger( int $order_id ): void {
			$this->send_email( $order_id, 'requested' );
		}

		/**
		 * Trigger the email when redaction is completed.
		 *
		 * @since 0.5.6
		 * @param int $order_id WooCommerce order ID.
		 * @return void
		 */
		public function trigger_completed( int $order_id ): void {
			$this->send_email( $order_id, 'completed' );
		}

		/**
		 * Send the email.
		 *
		 * @since 0.5.6
		 * @param int    $order_id WooCommerce order ID.
		 * @param string $status   Status: 'requested' or 'completed'.
		 * @return void
		 */
		private function send_email( int $order_id, string $status ): void {
			$this->setup_locale();

			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				$this->restore_locale();
				return;
			}

			$this->object                         = $order;
			$this->recipient                      = $order->get_billing_email();
			$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
			$this->placeholders['{order_number}'] = $order->get_order_number();

			// Store status for template.
			$this->redaction_status = $status;

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

				DSIC_Logger::info( 'Data redaction email (' . $status . ') sent to ' . $this->get_recipient() . ' for order #' . $order_id );
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
			$template = DSIC_Email_Helper::get_translated_template( 'data_redaction', $this->object );
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
			$template = DSIC_Email_Helper::get_translated_template( 'data_redaction', $this->object );
			$heading  = DSIC_Email_Helper::process_placeholders( $template['heading'], $this->object );
			return $this->format_string( $heading );
		}

		/**
		 * Get content HTML from database template.
		 *
		 * @since 0.5.6
		 * @since 1.4.0 Updated to use database template.
		 * @return string
		 */
		public function get_content_html(): string {
			$email = DSIC_Email_Helper::build_email(
				'data_redaction',
				$this->object
			);
			return $email['content'];
		}

		/**
		 * Get content plain from database template.
		 *
		 * @since 0.5.6
		 * @since 1.4.0 Updated to use database template.
		 * @return string
		 */
		public function get_content_plain(): string {
			$template = DSIC_Email_Helper::get_translated_template( 'data_redaction', $this->object );
			$body     = DSIC_Email_Helper::process_placeholders( $template['body'], $this->object );
			$body     = DSIC_Shortcodes::process( $body, $this->object );
			return DSIC_Email_Helper::html_to_plain( $body );
		}

		/**
		 * Default additional content.
		 *
		 * @since 0.5.6
		 * @return string
		 */
		public function get_default_additional_content(): string {
			return __( 'If you have any questions about this, please contact our support team.', 'droix-stripe-id-check' );
		}

		/**
		 * Initialize settings form fields.
		 *
		 * Note: These settings are shown in WooCommerce > Settings > Emails.
		 * The main template editing is done in plugin settings.
		 *
		 * @since 0.5.6
		 * @return void
		 */
		public function init_form_fields(): void {
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
