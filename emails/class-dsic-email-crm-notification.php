<?php
/**
 * CRM Notification Email.
 *
 * Sent to admin/CRM when verification status changes.
 *
 * @package    DSIC
 * @subpackage DSIC/emails
 * @since      0.0.1
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'DSIC_Email_CRM_Notification' ) ) :

	/**
	 * Class DSIC_Email_CRM_Notification
	 *
	 * @since 0.0.1
	 */
	class DSIC_Email_CRM_Notification extends WC_Email {

		/**
		 * Verification status.
		 *
		 * @since 0.0.1
		 * @var string
		 */
		public string $verification_status = '';

		/**
		 * Stripe session ID.
		 *
		 * @since 0.0.1
		 * @var string
		 */
		public string $session_id = '';

		/**
		 * Additional details.
		 *
		 * @since 0.0.1
		 * @var array
		 */
		public array $details = array();

		/**
		 * Constructor.
		 *
		 * @since 0.0.1
		 */
		public function __construct() {
			$this->id             = 'dsic_crm_notification';
			$this->customer_email = false;
			$this->title          = __( 'ID Verification CRM Notification', 'droix-stripe-id-check' );
			$this->description    = __( 'This email is sent to the CRM/admin when a verification status changes.', 'droix-stripe-id-check' );

			$this->template_html  = 'emails/crm-notification.php';
			$this->template_plain = 'emails/plain/crm-notification.php';
			$this->template_base  = DSIC_PLUGIN_DIR . 'templates/';

			$this->placeholders = array(
				'{order_date}'          => '',
				'{order_number}'        => '',
				'{verification_status}' => '',
			);

			// Triggers.
			add_action( 'dsic_crm_notification', array( $this, 'trigger' ), 10, 4 );

			// Parent constructor.
			parent::__construct();

			// Set recipient to CRM email from settings.
			$this->recipient = get_option( 'dsic_crm_email', get_option( 'admin_email' ) );
		}

		/**
		 * Get email subject.
		 *
		 * @since 0.0.1
		 * @return string
		 */
		public function get_default_subject(): string {
			return __( '[{verification_status}] ID Verification - Order #{order_number}', 'droix-stripe-id-check' );
		}

		/**
		 * Get email heading.
		 *
		 * @since 0.0.1
		 * @return string
		 */
		public function get_default_heading(): string {
			return __( 'ID Verification Status Update', 'droix-stripe-id-check' );
		}

		/**
		 * Trigger the email.
		 *
		 * @since 0.0.1
		 * @param int    $order_id    WooCommerce order ID.
		 * @param string $status      Verification status.
		 * @param string $session_id  Stripe session ID.
		 * @param array  $details     Additional details.
		 * @return void
		 */
		public function trigger( int $order_id, string $status, string $session_id = '', array $details = array() ): void {
			$this->setup_locale();

			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				$this->restore_locale();
				return;
			}

			$this->object                                  = $order;
			$this->verification_status                     = $status;
			$this->session_id                              = $session_id;
			$this->details                                 = $details;
			$this->placeholders['{order_date}']            = wc_format_datetime( $order->get_date_created() );
			$this->placeholders['{order_number}']          = $order->get_order_number();
			$this->placeholders['{verification_status}']   = ucfirst( $status );

			// Update recipient from settings (in case it changed).
			$this->recipient = get_option( 'dsic_crm_email', get_option( 'admin_email' ) );

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

				DSIC_Logger::info( 'CRM notification email sent to ' . $this->get_recipient() . ' for order #' . $order_id . ' (status: ' . $status . ')' );
			}

			$this->restore_locale();
		}

		/**
		 * Get content HTML.
		 *
		 * @since 0.0.1
		 * @return string
		 */
		public function get_content_html(): string {
			return wc_get_template_html(
				$this->template_html,
				array(
					'order'               => $this->object,
					'email_heading'       => $this->get_heading(),
					'verification_status' => $this->verification_status,
					'session_id'          => $this->session_id,
					'details'             => $this->details,
					'additional_content'  => $this->get_additional_content(),
					'sent_to_admin'       => true,
					'plain_text'          => false,
					'email'               => $this,
				),
				'',
				$this->template_base
			);
		}

		/**
		 * Get content plain.
		 *
		 * @since 0.0.1
		 * @return string
		 */
		public function get_content_plain(): string {
			return wc_get_template_html(
				$this->template_plain,
				array(
					'order'               => $this->object,
					'email_heading'       => $this->get_heading(),
					'verification_status' => $this->verification_status,
					'session_id'          => $this->session_id,
					'details'             => $this->details,
					'additional_content'  => $this->get_additional_content(),
					'sent_to_admin'       => true,
					'plain_text'          => true,
					'email'               => $this,
				),
				'',
				$this->template_base
			);
		}

		/**
		 * Default additional content.
		 *
		 * @since 0.0.1
		 * @return string
		 */
		public function get_default_additional_content(): string {
			return '';
		}

		/**
		 * Get Stripe dashboard URL for session.
		 *
		 * @since 0.0.1
		 * @return string
		 */
		public function get_stripe_dashboard_url(): string {
			if ( empty( $this->session_id ) ) {
				return '';
			}

			$test_mode = get_option( 'dsic_test_mode', true );
			$base_url  = $test_mode
				? 'https://dashboard.stripe.com/test/identity/verification-sessions/'
				: 'https://dashboard.stripe.com/identity/verification-sessions/';

			return $base_url . $this->session_id;
		}

		/**
		 * Initialize settings form fields.
		 *
		 * @since 0.0.1
		 * @return void
		 */
		public function init_form_fields(): void {
			/* translators: %s: list of placeholders */
			$placeholder_text = sprintf( __( 'Available placeholders: %s', 'droix-stripe-id-check' ), '<code>' . implode( '</code>, <code>', array_keys( $this->placeholders ) ) . '</code>' );

			$this->form_fields = array(
				'enabled'            => array(
					'title'   => __( 'Enable/Disable', 'droix-stripe-id-check' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable this email notification', 'droix-stripe-id-check' ),
					'default' => 'yes',
				),
				'recipient'          => array(
					'title'       => __( 'Recipient(s)', 'droix-stripe-id-check' ),
					'type'        => 'text',
					'description' => __( 'Enter recipients (comma separated) for this email. Leave blank to use CRM email from plugin settings.', 'droix-stripe-id-check' ),
					'placeholder' => get_option( 'dsic_crm_email', get_option( 'admin_email' ) ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'subject'            => array(
					'title'       => __( 'Subject', 'droix-stripe-id-check' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => $placeholder_text,
					'placeholder' => $this->get_default_subject(),
					'default'     => '',
				),
				'heading'            => array(
					'title'       => __( 'Email heading', 'droix-stripe-id-check' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => $placeholder_text,
					'placeholder' => $this->get_default_heading(),
					'default'     => '',
				),
				'additional_content' => array(
					'title'       => __( 'Additional content', 'droix-stripe-id-check' ),
					'description' => __( 'Text to appear below the main email content.', 'droix-stripe-id-check' ) . ' ' . $placeholder_text,
					'css'         => 'width:400px; height: 75px;',
					'placeholder' => __( 'N/A', 'droix-stripe-id-check' ),
					'type'        => 'textarea',
					'default'     => $this->get_default_additional_content(),
					'desc_tip'    => true,
				),
				'email_type'         => array(
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
