<?php
/**
 * Shortcodes class for email templates.
 *
 * Provides shortcodes that can be used in email content.
 *
 * @package    DSIC
 * @subpackage DSIC/includes
 * @since      0.0.1
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Shortcodes
 *
 * @since 0.0.1
 */
class DSIC_Shortcodes {

	/**
	 * Current order being processed.
	 *
	 * @since 0.0.1
	 * @var WC_Order|null
	 */
	private static ?WC_Order $current_order = null;

	/**
	 * Current verification URL.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	private static string $verification_url = '';

	/**
	 * Current failure reason (for failed verification emails).
	 *
	 * @since 1.4.0
	 * @var string
	 */
	private static string $failure_reason = '';

	/**
	 * Current verification result status (for CRM emails).
	 *
	 * @since 1.4.0
	 * @var string
	 */
	private static string $verification_result = '';

	/**
	 * Initialize shortcodes.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public static function init(): void {
		// Customer shortcodes.
		add_shortcode( 'dsic_customer_name', array( __CLASS__, 'customer_name' ) );
		add_shortcode( 'dsic_customer_first_name', array( __CLASS__, 'customer_first_name' ) );
		add_shortcode( 'dsic_customer_last_name', array( __CLASS__, 'customer_last_name' ) );
		add_shortcode( 'dsic_customer_email', array( __CLASS__, 'customer_email' ) );

		// Address shortcodes.
		add_shortcode( 'dsic_billing_address', array( __CLASS__, 'billing_address' ) );
		add_shortcode( 'dsic_shipping_address', array( __CLASS__, 'shipping_address' ) );

		// Order shortcodes.
		add_shortcode( 'dsic_order_number', array( __CLASS__, 'order_number' ) );
		add_shortcode( 'dsic_order_date', array( __CLASS__, 'order_date' ) );
		add_shortcode( 'dsic_order_total', array( __CLASS__, 'order_total' ) );
		add_shortcode( 'dsic_order_items', array( __CLASS__, 'order_items' ) );
		add_shortcode( 'dsic_order_admin_url', array( __CLASS__, 'order_admin_url' ) );

		// Verification shortcodes.
		add_shortcode( 'dsic_verification_link', array( __CLASS__, 'verification_link' ) );
		add_shortcode( 'dsic_verification_url', array( __CLASS__, 'verification_url' ) );
		add_shortcode( 'dsic_verification_status', array( __CLASS__, 'verification_status' ) );
		add_shortcode( 'dsic_failure_reason', array( __CLASS__, 'failure_reason' ) );
		add_shortcode( 'dsic_verification_result', array( __CLASS__, 'verification_result' ) );
		add_shortcode( 'dsic_data_deletion_date', array( __CLASS__, 'data_deletion_date' ) );

		// Site shortcodes.
		add_shortcode( 'dsic_site_name', array( __CLASS__, 'site_name' ) );
		add_shortcode( 'dsic_site_url', array( __CLASS__, 'site_url' ) );
		add_shortcode( 'dsic_support_email', array( __CLASS__, 'support_email' ) );
	}

	/**
	 * Set the current order context.
	 *
	 * @since 0.0.1
	 * @since 1.4.0 Added failure_reason and verification_result parameters.
	 * @param WC_Order|int $order               Order object or ID.
	 * @param string       $verification_url    Optional verification URL.
	 * @param string       $failure_reason      Optional failure reason for failed emails.
	 * @param string       $verification_result Optional verification result for CRM emails.
	 * @return void
	 */
	public static function set_order_context( $order, string $verification_url = '', string $failure_reason = '', string $verification_result = '' ): void {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		self::$current_order       = $order instanceof WC_Order ? $order : null;
		self::$verification_url    = $verification_url;
		self::$failure_reason      = $failure_reason;
		self::$verification_result = $verification_result;
	}

	/**
	 * Clear the order context.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public static function clear_order_context(): void {
		self::$current_order       = null;
		self::$verification_url    = '';
		self::$failure_reason      = '';
		self::$verification_result = '';
	}

	/**
	 * Process shortcodes in content with order context.
	 *
	 * @since 0.0.1
	 * @since 1.4.0 Added failure_reason and verification_result parameters.
	 * @param string       $content             Content to process.
	 * @param WC_Order|int $order               Order object or ID.
	 * @param string       $verification_url    Optional verification URL.
	 * @param string       $failure_reason      Optional failure reason for failed emails.
	 * @param string       $verification_result Optional verification result for CRM emails.
	 * @return string Processed content.
	 */
	public static function process( string $content, $order, string $verification_url = '', string $failure_reason = '', string $verification_result = '' ): string {
		self::set_order_context( $order, $verification_url, $failure_reason, $verification_result );
		$processed = do_shortcode( $content );
		self::clear_order_context();

		return $processed;
	}

	/**
	 * Get customer full name.
	 *
	 * @since 0.0.1
	 * @return string
	 */
	public static function customer_name(): string {
		if ( ! self::$current_order ) {
			return '';
		}

		return self::$current_order->get_formatted_billing_full_name();
	}

	/**
	 * Get customer first name.
	 *
	 * @since 0.0.1
	 * @return string
	 */
	public static function customer_first_name(): string {
		if ( ! self::$current_order ) {
			return '';
		}

		return self::$current_order->get_billing_first_name();
	}

	/**
	 * Get customer last name.
	 *
	 * @since 0.0.1
	 * @return string
	 */
	public static function customer_last_name(): string {
		if ( ! self::$current_order ) {
			return '';
		}

		return self::$current_order->get_billing_last_name();
	}

	/**
	 * Get customer email.
	 *
	 * @since 0.0.1
	 * @return string
	 */
	public static function customer_email(): string {
		if ( ! self::$current_order ) {
			return '';
		}

		return self::$current_order->get_billing_email();
	}

	/**
	 * Get formatted billing address.
	 *
	 * @since 0.0.1
	 * @return string
	 */
	public static function billing_address(): string {
		if ( ! self::$current_order ) {
			return '';
		}

		return self::$current_order->get_formatted_billing_address();
	}

	/**
	 * Get formatted shipping address.
	 *
	 * @since 0.0.1
	 * @return string
	 */
	public static function shipping_address(): string {
		if ( ! self::$current_order ) {
			return '';
		}

		return self::$current_order->get_formatted_shipping_address();
	}

	/**
	 * Get order number.
	 *
	 * @since 0.0.1
	 * @return string
	 */
	public static function order_number(): string {
		if ( ! self::$current_order ) {
			return '';
		}

		return self::$current_order->get_order_number();
	}

	/**
	 * Get order date.
	 *
	 * @since 0.0.1
	 * @return string
	 */
	public static function order_date(): string {
		if ( ! self::$current_order ) {
			return '';
		}

		return wc_format_datetime( self::$current_order->get_date_created() );
	}

	/**
	 * Get formatted order total.
	 *
	 * @since 0.0.1
	 * @return string
	 */
	public static function order_total(): string {
		if ( ! self::$current_order ) {
			return '';
		}

		return self::$current_order->get_formatted_order_total();
	}

	/**
	 * Get order items as HTML table.
	 *
	 * @since 0.0.1
	 * @return string
	 */
	public static function order_items(): string {
		if ( ! self::$current_order ) {
			return '';
		}

		$items = self::$current_order->get_items();

		if ( empty( $items ) ) {
			return '';
		}

		$html = '<table style="width: 100%; border-collapse: collapse;">';
		$html .= '<thead><tr>';
		$html .= '<th style="text-align: left; border-bottom: 1px solid #ddd; padding: 8px;">' . esc_html__( 'Product', 'droix-stripe-id-check' ) . '</th>';
		$html .= '<th style="text-align: center; border-bottom: 1px solid #ddd; padding: 8px;">' . esc_html__( 'Qty', 'droix-stripe-id-check' ) . '</th>';
		$html .= '<th style="text-align: right; border-bottom: 1px solid #ddd; padding: 8px;">' . esc_html__( 'Price', 'droix-stripe-id-check' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $items as $item ) {
			$html .= '<tr>';
			$html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html( $item->get_name() ) . '</td>';
			$html .= '<td style="text-align: center; padding: 8px; border-bottom: 1px solid #eee;">' . esc_html( $item->get_quantity() ) . '</td>';
			$html .= '<td style="text-align: right; padding: 8px; border-bottom: 1px solid #eee;">' . wp_kses_post( wc_price( $item->get_total() ) ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table>';

		return $html;
	}

	/**
	 * Get order admin URL.
	 *
	 * @since 0.0.1
	 * @return string
	 */
	public static function order_admin_url(): string {
		if ( ! self::$current_order ) {
			return '';
		}

		return self::$current_order->get_edit_order_url();
	}

	/**
	 * Get verification link as HTML.
	 *
	 * @since 0.0.1
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function verification_link( array $atts = array() ): string {
		if ( empty( self::$verification_url ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'text' => __( 'Verify My Identity', 'droix-stripe-id-check' ),
			),
			$atts
		);

		return sprintf(
			'<a href="%s" style="background-color: #7f54b3; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">%s</a>',
			esc_url( self::$verification_url ),
			esc_html( $atts['text'] )
		);
	}

	/**
	 * Get verification status.
	 *
	 * @since 0.0.1
	 * @return string
	 */
	public static function verification_status(): string {
		if ( ! self::$current_order ) {
			return '';
		}

		$status = self::$current_order->get_meta( '_dsic_verification_status' );

		if ( empty( $status ) ) {
			return __( 'Not Requested', 'droix-stripe-id-check' );
		}

		$statuses = array(
			'pending'  => __( 'Pending', 'droix-stripe-id-check' ),
			'verified' => __( 'Verified', 'droix-stripe-id-check' ),
			'failed'   => __( 'Failed', 'droix-stripe-id-check' ),
		);

		return $statuses[ $status ] ?? ucfirst( $status );
	}

	/**
	 * Get site name.
	 *
	 * @since 0.0.1
	 * @return string
	 */
	public static function site_name(): string {
		return get_bloginfo( 'name' );
	}

	/**
	 * Get site URL.
	 *
	 * @since 0.0.1
	 * @return string
	 */
	public static function site_url(): string {
		return home_url();
	}

	/**
	 * Get support email address.
	 *
	 * @since 1.4.0
	 * @return string
	 */
	public static function support_email(): string {
		return get_option( 'dsic_crm_email', get_option( 'admin_email' ) );
	}

	/**
	 * Get verification URL (raw, not as button).
	 *
	 * @since 1.4.0
	 * @return string
	 */
	public static function verification_url(): string {
		return self::$verification_url;
	}

	/**
	 * Get failure reason for failed verifications.
	 *
	 * @since 1.4.0
	 * @return string
	 */
	public static function failure_reason(): string {
		return self::$failure_reason;
	}

	/**
	 * Get verification result status (verified/failed).
	 *
	 * @since 1.4.0
	 * @return string
	 */
	public static function verification_result(): string {
		return self::$verification_result;
	}

	/**
	 * Get data deletion date (30 days from verification completion).
	 *
	 * Displays the date when customer's verification data will be automatically
	 * deleted from Stripe (30 days after successful verification).
	 *
	 * @since 1.6.0
	 * @return string Formatted deletion date.
	 */
	public static function data_deletion_date(): string {
		if ( ! self::$current_order ) {
			return '';
		}

		// Get verification completion date.
		$completed_date = self::$current_order->get_meta( '_dsic_verification_completed' );

		// If verification is completed, calculate 30 days from completion.
		if ( ! empty( $completed_date ) ) {
			$deletion_timestamp = strtotime( $completed_date ) + ( 30 * DAY_IN_SECONDS );
		} else {
			// If not completed yet, calculate 30 days from now (for request emails).
			$deletion_timestamp = time() + ( 30 * DAY_IN_SECONDS );
		}

		// Format the date using WordPress date format.
		return wp_date( get_option( 'date_format' ), $deletion_timestamp );
	}

	/**
	 * Get list of available shortcodes with descriptions.
	 *
	 * @since 0.0.1
	 * @since 1.4.0 Added new shortcodes for email templates.
	 * @return array
	 */
	public static function get_available_shortcodes(): array {
		return array(
			'[dsic_customer_name]'        => __( 'Customer full name', 'droix-stripe-id-check' ),
			'[dsic_customer_first_name]'  => __( 'Customer first name', 'droix-stripe-id-check' ),
			'[dsic_customer_last_name]'   => __( 'Customer last name', 'droix-stripe-id-check' ),
			'[dsic_customer_email]'       => __( 'Customer email address', 'droix-stripe-id-check' ),
			'[dsic_billing_address]'      => __( 'Formatted billing address', 'droix-stripe-id-check' ),
			'[dsic_shipping_address]'     => __( 'Formatted shipping address', 'droix-stripe-id-check' ),
			'[dsic_order_number]'         => __( 'Order number', 'droix-stripe-id-check' ),
			'[dsic_order_date]'           => __( 'Order date', 'droix-stripe-id-check' ),
			'[dsic_order_total]'          => __( 'Formatted order total', 'droix-stripe-id-check' ),
			'[dsic_order_items]'          => __( 'Order items table', 'droix-stripe-id-check' ),
			'[dsic_order_admin_url]'      => __( 'Link to order in admin', 'droix-stripe-id-check' ),
			'[dsic_verification_link]'    => __( 'Verification button/link', 'droix-stripe-id-check' ),
			'[dsic_verification_url]'     => __( 'Verification URL (raw)', 'droix-stripe-id-check' ),
			'[dsic_verification_status]'  => __( 'Current verification status', 'droix-stripe-id-check' ),
			'[dsic_verification_result]'  => __( 'Verification result (verified/failed)', 'droix-stripe-id-check' ),
			'[dsic_failure_reason]'       => __( 'Failure reason (for failed emails)', 'droix-stripe-id-check' ),
			'[dsic_data_deletion_date]'   => __( 'Date when verification data will be deleted (30 days after verification)', 'droix-stripe-id-check' ),
			'[dsic_site_name]'            => __( 'Site name', 'droix-stripe-id-check' ),
			'[dsic_site_url]'             => __( 'Site URL', 'droix-stripe-id-check' ),
			'[dsic_support_email]'        => __( 'Support email address', 'droix-stripe-id-check' ),
		);
	}
}
