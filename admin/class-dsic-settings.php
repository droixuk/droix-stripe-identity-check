<?php
/**
 * Settings management class.
 *
 * Handles plugin settings registration and AJAX handlers.
 *
 * @package    DSIC
 * @subpackage DSIC/admin
 * @since      0.0.1
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Settings
 *
 * @since 0.0.1
 */
class DSIC_Settings {

	/**
	 * Settings option group.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	private string $option_group = 'dsic_settings';

	/**
	 * Register plugin settings.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function register_settings(): void {
		// Set capability for this options page to match menu capability.
		add_filter( 'option_page_capability_' . $this->option_group, array( $this, 'get_option_page_capability' ) );
		// General settings.
		register_setting(
			$this->option_group,
			'dsic_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			$this->option_group,
			'dsic_test_mode',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		register_setting(
			$this->option_group,
			'dsic_debug_mode',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			$this->option_group,
			'dsic_crm_email',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
				'default'           => get_option( 'admin_email' ),
			)
		);

		register_setting(
			$this->option_group,
			'dsic_delete_data_on_uninstall',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		// Auto-verification settings.
		register_setting(
			$this->option_group,
			'dsic_auto_verify_different_address',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			$this->option_group,
			'dsic_auto_verify_checkout_message',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'default'           => $this->get_default_checkout_message(),
			)
		);

		register_setting(
			$this->option_group,
			'dsic_auto_verify_thankyou_message',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'default'           => $this->get_default_thankyou_message(),
			)
		);

		// API settings.
		register_setting(
			$this->option_group,
			'dsic_stripe_publishable_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			$this->option_group,
			'dsic_stripe_secret_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'default'           => '',
			)
		);

		register_setting(
			$this->option_group,
			'dsic_webhook_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'default'           => '',
			)
		);

		// Email template settings.
		$email_types = array( 'verification_request', 'verification_passed', 'verification_failed', 'data_redaction' );
		foreach ( $email_types as $type ) {
			register_setting(
				$this->option_group,
				'dsic_email_' . $type . '_enabled',
				array(
					'type'              => 'boolean',
					'sanitize_callback' => 'rest_sanitize_boolean',
					'default'           => false,
				)
			);

			register_setting(
				$this->option_group,
				'dsic_email_' . $type . '_subject',
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => $this->get_default_email_subject( $type ),
				)
			);

			register_setting(
				$this->option_group,
				'dsic_email_' . $type . '_heading',
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => $this->get_default_email_heading( $type ),
				)
			);

			register_setting(
				$this->option_group,
				'dsic_email_' . $type . '_body',
				array(
					'type'              => 'string',
					'sanitize_callback' => 'wp_kses_post',
					'default'           => $this->get_default_email_body( $type ),
				)
			);
		}

		// Radar fraud detection settings (v1.8.0+).
		register_setting(
			$this->option_group,
			'dsic_radar_check_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			$this->option_group,
			'dsic_radar_check_mode',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'risk_level',
			)
		);

		register_setting(
			$this->option_group,
			'dsic_radar_risk_level_threshold',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'elevated',
			)
		);

		register_setting(
			$this->option_group,
			'dsic_radar_risk_score_threshold',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 65,
			)
		);

		register_setting(
			$this->option_group,
			'dsic_radar_early_warnings_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			$this->option_group,
			'dsic_radar_minimum_order_amount',
			array(
				'type'              => 'number',
				'sanitize_callback' => array( $this, 'sanitize_minimum_order_amount' ),
				'default'           => 0,
			)
		);

		register_setting(
			$this->option_group,
			'dsic_radar_thankyou_message',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'default'           => $this->get_default_radar_thankyou_message(),
			)
		);

		register_setting(
			$this->option_group,
			'dsic_radar_test_secret_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'default'           => '',
			)
		);

		register_setting(
			$this->option_group,
			'dsic_radar_live_secret_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'default'           => '',
			)
		);

		register_setting(
			$this->option_group,
			'dsic_radar_test_webhook_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'default'           => '',
			)
		);

		register_setting(
			$this->option_group,
			'dsic_radar_live_webhook_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'default'           => '',
			)
		);

		// Data retention settings (v1.7.0+).
		register_setting(
			$this->option_group,
			'dsic_auto_redaction_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			$this->option_group,
			'dsic_redaction_days',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 30,
			)
		);

		register_setting(
			$this->option_group,
			'dsic_redaction_batch_size',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 20,
			)
		);

		register_setting(
			$this->option_group,
			'dsic_redaction_schedule_time',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '01:00',
			)
		);

		register_setting(
			$this->option_group,
			'dsic_redaction_notify_customer',
			array(
					'type'              => 'boolean',
					'sanitize_callback' => 'rest_sanitize_boolean',
					'default'           => false,
				)
			);

		// Slack notification settings (v1.9.3+).
		// Amount threshold settings (v1.10.0+).
		register_setting(
			$this->option_group,
			'dsic_amount_threshold_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			$this->option_group,
			'dsic_amount_threshold_value',
			array(
				'type'              => 'number',
				'sanitize_callback' => array( $this, 'sanitize_minimum_order_amount' ),
				'default'           => 0,
			)
		);

		register_setting(
			$this->option_group,
			'dsic_amount_threshold_currency',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		// Slack notification settings (v1.9.3+).
		register_setting(
			$this->option_group,
			'dsic_slack_webhook_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			)
		);

		register_setting(
			$this->option_group,
			'dsic_slack_notify_triggered',
			array(
					'type'              => 'boolean',
					'sanitize_callback' => 'rest_sanitize_boolean',
					'default'           => false,
				)
		);

		register_setting(
			$this->option_group,
			'dsic_slack_notify_passed',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			$this->option_group,
			'dsic_slack_notify_failed',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		register_setting(
			$this->option_group,
			'dsic_slack_notify_efw',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);
	}

	/**
	 * Get capability required for this options page.
	 *
	 * @since 0.3.7
	 * @return string Capability name.
	 */
	public function get_option_page_capability(): string {
		return 'manage_woocommerce';
	}

	/**
	 * Sanitize API key input.
	 *
	 * @since 0.0.1
	 * @param string $value The input value.
	 * @return string Sanitized value.
	 */
	/**
	 * Sanitize minimum order amount.
	 *
	 * @since 1.9.0
	 * @param mixed $value Input value.
	 * @return float Sanitized non-negative float.
	 */
	public function sanitize_minimum_order_amount( $value ): float {
		return max( 0, (float) $value );
	}

	public function sanitize_api_key( string $value ): string {
		// Remove any whitespace.
		$value = trim( $value );

		// Only allow alphanumeric characters and underscores (typical for API keys).
		$value = preg_replace( '/[^a-zA-Z0-9_]/', '', $value );

		return $value;
	}

	/**
	 * Get all plugin settings.
	 *
	 * @since 0.0.1
	 * @return array
	 */
	public function get_settings(): array {
		$test_mode = $this->get_bool_option( 'dsic_test_mode', true );

		// Return the active keys based on test mode.
		if ( $test_mode ) {
			$publishable_key = get_option( 'dsic_test_publishable_key', '' );
			$secret_key      = get_option( 'dsic_test_secret_key', '' );
			$webhook_secret  = get_option( 'dsic_test_webhook_secret', '' );
		} else {
			$publishable_key = get_option( 'dsic_live_publishable_key', '' );
			$secret_key      = get_option( 'dsic_live_secret_key', '' );
			$webhook_secret  = get_option( 'dsic_live_webhook_secret', '' );
		}

		return array(
			'enabled'             => $this->get_bool_option( 'dsic_enabled', true ),
			'test_mode'           => $test_mode,
			'debug_mode'          => $this->get_bool_option( 'dsic_debug_mode', false ),
			'crm_email'           => get_option( 'dsic_crm_email', get_option( 'admin_email' ) ),
			'publishable_key'     => $publishable_key,
			'secret_key'          => $secret_key,
			'webhook_secret'      => $webhook_secret,
			'delete_on_uninstall' => $this->get_bool_option( 'dsic_delete_data_on_uninstall', false ),
		);
	}

	/**
	 * Get a boolean option value.
	 *
	 * Handles both string ('1', '0') and boolean (true, false) stored values.
	 *
	 * @since 1.1.0
	 * @param string $option  Option name.
	 * @param bool   $default Default value if option doesn't exist.
	 * @return bool
	 */
	private function get_bool_option( string $option, bool $default = false ): bool {
		$value = get_option( $option );

		// If option doesn't exist, return default.
		if ( false === $value ) {
			return $default;
		}

		// Handle string values '1', '0', 'yes', 'no', etc.
		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), array( '1', 'yes', 'true', 'on' ), true );
		}

		// Handle boolean values directly.
		return (bool) $value;
	}

	/**
	 * Check if API keys are configured for the current mode.
	 *
	 * @since 0.0.1
	 * @return bool
	 */
	public function has_api_keys(): bool {
		$test_mode = $this->get_bool_option( 'dsic_test_mode', true );

		if ( $test_mode ) {
			$secret_key = get_option( 'dsic_test_secret_key', '' );
		} else {
			$secret_key = get_option( 'dsic_live_secret_key', '' );
		}

		return ! empty( $secret_key );
	}

	/**
	 * Get detailed API configuration status.
	 *
	 * Returns an array with information about which keys are missing.
	 *
	 * @since 1.6.1
	 * @return array {
	 *     @type bool   $configured      Whether API is fully configured.
	 *     @type bool   $test_mode       Whether test mode is enabled.
	 *     @type string $mode_label      Human-readable mode label.
	 *     @type bool   $has_secret_key  Whether secret key is set.
	 *     @type bool   $has_webhook     Whether webhook secret is set.
	 *     @type array  $missing         List of missing configuration items.
	 * }
	 */
	public function get_api_status(): array {
		$test_mode = $this->get_bool_option( 'dsic_test_mode', true );

		if ( $test_mode ) {
			$secret_key     = get_option( 'dsic_test_secret_key', '' );
			$webhook_secret = get_option( 'dsic_test_webhook_secret', '' );
			$mode_label     = __( 'Test Mode', 'droix-stripe-id-check' );
		} else {
			$secret_key     = get_option( 'dsic_live_secret_key', '' );
			$webhook_secret = get_option( 'dsic_live_webhook_secret', '' );
			$mode_label     = __( 'Live Mode', 'droix-stripe-id-check' );
		}

		$has_secret  = ! empty( $secret_key );
		$has_webhook = ! empty( $webhook_secret );
		$missing     = array();

		if ( ! $has_secret ) {
			$missing[] = __( 'Secret Key', 'droix-stripe-id-check' );
		}
		if ( ! $has_webhook ) {
			$missing[] = __( 'Webhook Secret', 'droix-stripe-id-check' );
		}

		return array(
			'configured'     => $has_secret && $has_webhook,
			'test_mode'      => $test_mode,
			'mode_label'     => $mode_label,
			'has_secret_key' => $has_secret,
			'has_webhook'    => $has_webhook,
			'missing'        => $missing,
		);
	}

	/**
	 * AJAX handler for testing Stripe connection.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function ajax_test_connection(): void {
		// Verify nonce.
		check_ajax_referer( 'dsic_nonce', 'nonce' );

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized access.', 'droix-stripe-id-check' ) )
			);
		}

		// Get API keys from POST (allows testing before saving).
		$secret_key = isset( $_POST['secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['secret_key'] ) ) : '';

		// If no key provided in POST, use saved setting based on test mode.
		if ( empty( $secret_key ) ) {
			$test_mode = (bool) get_option( 'dsic_test_mode', '1' );
			if ( $test_mode ) {
				$secret_key = get_option( 'dsic_test_secret_key', '' );
			} else {
				$secret_key = get_option( 'dsic_live_secret_key', '' );
			}
		}

		if ( empty( $secret_key ) ) {
			wp_send_json_error(
				array( 'message' => __( 'API secret key is required.', 'droix-stripe-id-check' ) )
			);
		}

		// Test connection.
		$api    = new DSIC_Stripe_API( $secret_key );
		$result = $api->test_connection();

		if ( is_wp_error( $result ) ) {
			DSIC_Logger::error( 'Connection test failed: ' . $result->get_error_message() );
			wp_send_json_error(
				array( 'message' => $result->get_error_message() )
			);
		}

		DSIC_Logger::info( 'Connection test successful. Account: ' . ( $result['account_name'] ?? 'Unknown' ) );

		wp_send_json_success(
			array(
				'message'      => __( 'Connection successful!', 'droix-stripe-id-check' ),
				'account_name' => $result['account_name'] ?? '',
				'account_id'   => $result['account_id'] ?? '',
			)
		);
	}

	/**
	 * AJAX handler for testing Radar API connection.
	 *
	 * Tests using the Radar-specific key if configured, otherwise falls back
	 * to the Identity API key. Returns a clear message indicating which key is used.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function ajax_test_radar_connection(): void {
		// Verify nonce.
		check_ajax_referer( 'dsic_nonce', 'nonce' );

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized access.', 'droix-stripe-id-check' ) )
			);
		}

		$test_mode = (bool) get_option( 'dsic_test_mode', '1' );

		// Resolve which key to use: Radar-specific first, then Identity fallback.
		if ( $test_mode ) {
			$radar_key    = get_option( 'dsic_radar_test_secret_key', '' );
			$identity_key = get_option( 'dsic_test_secret_key', '' );
		} else {
			$radar_key    = get_option( 'dsic_radar_live_secret_key', '' );
			$identity_key = get_option( 'dsic_live_secret_key', '' );
		}

		$using_fallback = empty( $radar_key );
		$secret_key     = $using_fallback ? $identity_key : $radar_key;

		if ( empty( $secret_key ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No API key configured. Please set your Stripe Identity API keys first.', 'droix-stripe-id-check' ) )
			);
		}

		// Test connection.
		$api    = new DSIC_Stripe_API( $secret_key );
		$result = $api->test_connection();

		if ( is_wp_error( $result ) ) {
			DSIC_Logger::error( 'Radar connection test failed: ' . $result->get_error_message() );
			wp_send_json_error(
				array( 'message' => $result->get_error_message() )
			);
		}

		$account_name = $result['account_name'] ?? __( 'Unknown', 'droix-stripe-id-check' );
		$mode_label   = $test_mode ? __( 'test', 'droix-stripe-id-check' ) : __( 'live', 'droix-stripe-id-check' );

		if ( $using_fallback ) {
			$message = sprintf(
				/* translators: 1: Account name, 2: Mode label (test/live) */
				__( 'Connected! Using your Identity %2$s key for Radar (account: %1$s). No separate Radar key needed.', 'droix-stripe-id-check' ),
				$account_name,
				$mode_label
			);
		} else {
			$message = sprintf(
				/* translators: 1: Account name, 2: Mode label (test/live) */
				__( 'Connected! Using dedicated Radar %2$s key (account: %1$s).', 'droix-stripe-id-check' ),
				$account_name,
				$mode_label
			);
		}

		DSIC_Logger::info( 'Radar connection test successful. Using ' . ( $using_fallback ? 'fallback Identity' : 'dedicated Radar' ) . ' key.' );

		wp_send_json_success(
			array(
				'message'        => $message,
				'using_fallback' => $using_fallback,
				'account_name'   => $account_name,
			)
		);
	}

	/**
	 * AJAX handler for testing Slack webhook connection.
	 *
	 * @since 1.9.3
	 * @return void
	 */
	public function ajax_test_slack_connection(): void {
		check_ajax_referer( 'dsic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'droix-stripe-id-check' ) ) );
		}

		$webhook_url = get_option( 'dsic_slack_webhook_url', '' );

		if ( empty( $webhook_url ) ) {
			wp_send_json_error( array( 'message' => __( 'No webhook URL configured.', 'droix-stripe-id-check' ) ) );
		}

		$payload = array(
			'blocks' => array(
				array(
					'type' => 'section',
					'text' => array(
						'type' => 'mrkdwn',
						'text' => ':white_check_mark: *Stripe ID Check* — Slack notifications are working correctly.',
					),
				),
			),
		);

		$response = wp_remote_post(
			$webhook_url,
			array(
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'        => wp_json_encode( $payload ),
				'timeout'     => 10,
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 === (int) $code ) {
			wp_send_json_success( array( 'message' => __( 'Test message sent successfully! Check your Slack channel.', 'droix-stripe-id-check' ) ) );
		} else {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: HTTP status code */
						__( 'Slack returned HTTP %d. Check your webhook URL.', 'droix-stripe-id-check' ),
						$code
					),
				)
			);
		}
	}

	/**
	 * AJAX handler for clearing logs.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function ajax_clear_logs(): void {
		// Verify nonce.
		check_ajax_referer( 'dsic_nonce', 'nonce' );

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized access.', 'droix-stripe-id-check' ) )
			);
		}

		$success = DSIC_Logger::clear_all_logs();

		if ( $success ) {
			wp_send_json_success(
				array( 'message' => __( 'Logs cleared successfully.', 'droix-stripe-id-check' ) )
			);
		} else {
			wp_send_json_error(
				array( 'message' => __( 'Failed to clear logs.', 'droix-stripe-id-check' ) )
			);
		}
	}

	/**
	 * Mask API key for display.
	 *
	 * @since 0.0.1
	 * @param string $key The API key.
	 * @return string Masked key.
	 */
	public function mask_api_key( string $key ): string {
		if ( empty( $key ) ) {
			return '';
		}

		$length = strlen( $key );
		if ( $length <= 8 ) {
			return str_repeat( '*', $length );
		}

		return substr( $key, 0, 4 ) . str_repeat( '*', $length - 8 ) . substr( $key, -4 );
	}

	/**
	 * Get Radar fraud detection settings.
	 *
	 * @since 1.8.0
	 * @return array Radar settings.
	 */
	public function get_radar_settings(): array {
		return array(
			'enabled'                => $this->get_bool_option( 'dsic_radar_check_enabled', false ),
			'mode'                   => get_option( 'dsic_radar_check_mode', 'risk_level' ),
			'risk_level_threshold'   => get_option( 'dsic_radar_risk_level_threshold', 'elevated' ),
			'risk_score_threshold'   => (int) get_option( 'dsic_radar_risk_score_threshold', 65 ),
			'early_warnings_enabled' => $this->get_bool_option( 'dsic_radar_early_warnings_enabled', false ),
		);
	}

	/**
	 * Get default checkout warning message for auto-verification.
	 *
	 * @since 1.6.0
	 * @return string Default checkout message.
	 */
	public function get_default_checkout_message(): string {
		return __( "Heads up — shipping to a different address may trigger a quick identity check by our payment provider. If it does, you'll receive a short email with easy next steps. It only takes about 2 minutes.", 'droix-stripe-id-check' );
	}

	/**
	 * Get default thank you page message for auto-verification.
	 *
	 * @since 1.6.0
	 * @return string Default thank you message.
	 */
	public function get_default_thankyou_message(): string {
		return __( "Nice one — your order is in! 🎉 There's just a quick step before we can get it moving. Because you're shipping to a different address, our payment provider has flagged it for a routine identity check — completely standard these days. We've just sent you an email with everything you need (takes about 2 minutes). Please check your junk/spam folder if it hasn't arrived within 5 minutes.", 'droix-stripe-id-check' );
	}

	/**
	 * Get default Radar thank you page message.
	 *
	 * @since 1.9.0
	 * @return string Default Radar thank you message.
	 */
	public function get_default_radar_thankyou_message(): string {
		return __( "Nice one — your order is in! 🎉 There's just a tiny hurdle before we hand it to the shipping team. Our payment provider (bless them — they get nervous when they spot unusual payment patterns) has asked us to do a quick routine identity check. This is completely standard — it just means some unwanted guests occasionally try to use other people's cards online, and our payment provider keeps an eye out for that. You are clearly not one of them, but the check takes 2 minutes and keeps everyone safe. We've just sent you an email with all the details and next steps. Please check your junk/spam folder if it hasn't arrived within 5 minutes.", 'droix-stripe-id-check' );
	}

	/**
	 * Get auto-verification settings.
	 *
	 * @since 1.6.0
	 * @return array Auto-verification settings.
	 */
	public function get_auto_verify_settings(): array {
		return array(
			'enabled'          => $this->get_bool_option( 'dsic_auto_verify_different_address', false ),
			'checkout_message' => get_option( 'dsic_auto_verify_checkout_message', $this->get_default_checkout_message() ),
			'thankyou_message' => get_option( 'dsic_auto_verify_thankyou_message', $this->get_default_thankyou_message() ),
		);
	}

	/**
	 * Get default email subject.
	 *
	 * @since 0.3.2
	 * @param string $type Email type.
	 * @return string Default subject.
	 */
	public function get_default_email_subject( string $type ): string {
		$subjects = array(
			'verification_request' => '🎉 Your order is confirmed — one quick step needed (Order #{order_number})',
			'verification_passed'  => '✅ ID Verified - Order #{order_number} Confirmed!',
			'verification_failed'  => '⚠️ Action Required: ID Verification Issue - Order #{order_number}',
			'data_redaction'       => '🗑️ Your Verification Data Has Been Deleted - Order #{order_number}',
		);

		return $subjects[ $type ] ?? '';
	}

	/**
	 * Get default email heading.
	 *
	 * @since 0.3.2
	 * @param string $type Email type.
	 * @return string Default heading.
	 */
	public function get_default_email_heading( string $type ): string {
		$headings = array(
			'verification_request' => 'Your order is in — just a 2-minute check',
			'verification_passed'  => '✅ Identity Verified Successfully!',
			'verification_failed'  => '⚠️ Verification Needs Attention',
			'data_redaction'       => '🗑️ Your Data Has Been Deleted',
		);

		return $headings[ $type ] ?? '';
	}

	/**
	 * Get default email body.
	 *
	 * @since 0.3.2
	 * @param string $type Email type.
	 * @return string Default body content.
	 */
	public function get_default_email_body( string $type ): string {
		$bodies = array(
			'verification_request' => $this->get_verification_request_template(),
			'verification_passed'  => $this->get_verification_passed_template(),
			'verification_failed'  => $this->get_verification_failed_template(),
			'data_redaction'       => $this->get_data_redaction_template(),
		);

		return $bodies[ $type ] ?? '';
	}

	/**
	 * Get verification request email template.
	 *
	 * @since 0.3.7
	 * @return string HTML template.
	 */
	private function get_verification_request_template(): string {
		return '<table style="margin: 0; padding: 0; background-color: #f5f7fa;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 40px 20px;" align="center">
<table style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);" role="presentation" border="0" width="600" cellspacing="0" cellpadding="0">
<tbody>
<!-- Action Required Badge -->
<tr>
<td style="padding: 32px 40px 0;">
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding-bottom: 24px;" align="center">
<table style="background-color: #dcfce7; border-radius: 50px;" role="presentation" border="0" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 8px 20px;"><span style="color: #15803d; font-size: 14px; font-weight: 600; letter-spacing: 0.5px;">🎉 Order Confirmed — One Quick Step</span></td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
<!-- Main Content -->
<tr>
<td style="padding: 0 40px 32px;">
<h1 style="margin: 0 0 16px; font-size: 24px; font-weight: 600; color: #0f172a; line-height: 1.3;">Hi [dsic_customer_first_name],</h1>
<p style="margin: 0 0 24px; font-size: 16px; line-height: 1.6; color: #475569;">Great news — your order <strong style="color: #0f172a;">#{order_number}</strong> is confirmed and in our system! 🎉</p>
<p style="margin: 0 0 24px; font-size: 16px; line-height: 1.6; color: #475569;">As mentioned on the order confirmation page, our payment provider has flagged it for a quick routine identity check. This is completely standard — these days, payment providers keep an eye out for people attempting to use cards that aren\'t theirs, and occasionally that means asking a legitimate customer (that\'s you!) to confirm who they are.</p>
<p style="margin: 0 0 24px; font-size: 16px; line-height: 1.6; color: #475569;">It takes about 2 minutes, it\'s secure, and once done your order is on its way.</p>

<!-- Order Info Card -->
<table style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 24px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 24px;">
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 8px 0; font-size: 15px; color: #475569;">Order Number</td>
<td style="padding: 8px 0; font-size: 15px; color: #0f172a; font-weight: 600;" align="right">#{order_number}</td>
</tr>
<tr>
<td style="padding: 8px 0; font-size: 15px; color: #475569;">Order Date</td>
<td style="padding: 8px 0; font-size: 15px; color: #0f172a; font-weight: 600;" align="right">{order_date}</td>
</tr>
<tr>
<td style="padding: 8px 0; font-size: 15px; color: #475569;">Status</td>
<td style="padding: 8px 0; font-size: 15px; color: #d97706; font-weight: 600;" align="right">⏳ Awaiting Verification</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>

<!-- What You Need Card -->
<table style="margin-bottom: 24px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 20px; background-color: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 4px;">
<div style="font-size: 14px; font-weight: 600; color: #1e40af; margin-bottom: 12px;">📋 What you\'ll need:</div>
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #475569;">✓ A valid government-issued ID (passport, driver\'s license, or national ID)</td>
</tr>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #475569;">✓ Good lighting for taking photos</td>
</tr>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #475569;">✓ About 2 minutes of your time</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>

<!-- Important Tips Card -->
<table style="margin-bottom: 24px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 20px; background-color: #fefce8; border-left: 4px solid #eab308; border-radius: 4px;">
<div style="font-size: 14px; font-weight: 600; color: #854d0e; margin-bottom: 12px;">👓 Important tip:</div>
<div style="font-size: 14px; color: #713f12; line-height: 1.5;">If your ID photo shows you <strong>without glasses</strong>, please <strong>remove your glasses</strong> when taking the selfie photo. This helps ensure your face matches your ID and improves verification success.</div>
</td>
</tr>
</tbody>
</table>

<!-- Progress Indicator -->
<table style="margin-bottom: 32px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="width: 33.33%; padding: 0 8px;" align="center">
<div style="width: 40px; height: 40px; background-color: #22c55e; border-radius: 50%; margin: 0 auto 8px; line-height: 40px; color: #ffffff; font-weight: bold; text-align: center;">✓</div>
<div style="font-size: 12px; color: #22c55e; font-weight: 600;">Order Placed</div></td>
<td style="width: 33.33%; padding: 0 8px;" align="center">
<div style="width: 40px; height: 40px; background-color: #f59e0b; border-radius: 50%; margin: 0 auto 8px; line-height: 40px; color: #ffffff; font-weight: bold; text-align: center;">2</div>
<div style="font-size: 12px; color: #f59e0b; font-weight: 600;">Verify ID</div></td>
<td style="width: 33.33%; padding: 0 8px;" align="center">
<div style="width: 40px; height: 40px; background-color: #e2e8f0; border-radius: 50%; margin: 0 auto 8px; line-height: 40px; color: #64748b; font-weight: bold; text-align: center;">3</div>
<div style="font-size: 12px; color: #64748b;">Processing</div></td>
</tr>
</tbody>
</table>

<!-- CTA Button -->
<table style="margin-bottom: 24px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td align="center">[dsic_verification_link]</td>
</tr>
</tbody>
</table>

<p style="margin: 0 0 24px; font-size: 14px; line-height: 1.6; color: #64748b; text-align: center;">The verification process is quick, secure, and powered by Stripe.</p>

<!-- Why Verify Card -->
<table style="margin-bottom: 24px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 16px; background-color: #fafafa; border: 1px solid #e5e5e5; border-radius: 4px;">
<div style="font-size: 13px; font-weight: 600; color: #525252; margin-bottom: 8px;">🛡️ Why do we require ID verification?</div>
<div style="font-size: 13px; color: #737373; line-height: 1.5;">We take fraud prevention seriously. This verification helps protect both you and us from unauthorized transactions, ensuring a safe shopping experience for everyone.</div>
</td>
</tr>
</tbody>
</table>

<!-- Privacy Notice Card -->
<table style="margin-bottom: 24px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 16px; background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px;">
<div style="font-size: 13px; font-weight: 600; color: #166534; margin-bottom: 8px;">🔒 Your privacy is protected</div>
<div style="font-size: 13px; color: #15803d; line-height: 1.5;">Your verification data is handled securely by Stripe and is <strong>automatically deleted after 30 days</strong>. We never store your ID documents on our servers. <a href="https://support.stripe.com/questions/managing-your-id-verification-information" style="color: #166534; text-decoration: underline;" target="_blank">Learn more about how Stripe handles your data</a>.</div>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
<!-- Footer -->
<tr>
<td style="background-color: #f8fafc; padding: 32px 40px; border-top: 1px solid #e2e8f0;">
<p style="margin: 0 0 16px; font-size: 15px; color: #0f172a; text-align: center;">Have a question or think this check was flagged by mistake?</p>
<p style="margin: 0 0 24px; font-size: 15px; color: #475569; text-align: center; line-height: 1.6;">Simply <strong>reply to this email</strong> — our team will happily help you within 24 hours (or 48 hours over the weekend). No need to fill in any forms or start a new conversation.</p>
<p style="margin: 0; font-size: 11px; color: #94a3b8; text-align: center; line-height: 1.5;">© ' . gmdate( 'Y' ) . ' [dsic_site_name]. All rights reserved.<br>This is an automated email regarding your order.</p>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>';
	}

	/**
	 * Get verification passed email template.
	 *
	 * @since 0.3.7
	 * @return string HTML template.
	 */
	private function get_verification_passed_template(): string {
		return '<table style="margin: 0; padding: 0; background-color: #f5f7fa;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 40px 20px;" align="center">
<table style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);" role="presentation" border="0" width="600" cellspacing="0" cellpadding="0">
<tbody>
<!-- Success Badge -->
<tr>
<td style="padding: 32px 40px 0;">
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding-bottom: 24px;" align="center">
<table style="background-color: #dcfce7; border-radius: 50px;" role="presentation" border="0" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 8px 20px;"><span style="color: #15803d; font-size: 14px; font-weight: 600; letter-spacing: 0.5px;">✓ IDENTITY VERIFIED</span></td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
<!-- Main Content -->
<tr>
<td style="padding: 0 40px 32px;">
<h1 style="margin: 0 0 16px; font-size: 24px; font-weight: 600; color: #0f172a; line-height: 1.3;">Great news, [dsic_customer_first_name]!</h1>
<p style="margin: 0 0 24px; font-size: 16px; line-height: 1.6; color: #475569;">Your identity has been <strong style="color: #15803d;">successfully verified</strong> for order <strong style="color: #0f172a;">#{order_number}</strong>. Your order is now being processed!</p>

<!-- Order Info Card -->
<table style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 24px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 24px;">
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 8px 0; font-size: 15px; color: #475569;">Order Number</td>
<td style="padding: 8px 0; font-size: 15px; color: #0f172a; font-weight: 600;" align="right">#{order_number}</td>
</tr>
<tr>
<td style="padding: 8px 0; font-size: 15px; color: #475569;">Order Date</td>
<td style="padding: 8px 0; font-size: 15px; color: #0f172a; font-weight: 600;" align="right">{order_date}</td>
</tr>
<tr>
<td style="padding: 8px 0; font-size: 15px; color: #475569;">Verification Status</td>
<td style="padding: 8px 0; font-size: 15px; color: #15803d; font-weight: 600;" align="right">✓ Verified</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>

<!-- What Happens Next Card -->
<table style="margin-bottom: 24px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 20px; background-color: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 4px;">
<div style="font-size: 14px; font-weight: 600; color: #1e40af; margin-bottom: 12px;">📦 What happens next?</div>
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #475569;">1. Your order is now being processed</td>
</tr>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #475569;">2. We\'ll prepare your items for dispatch</td>
</tr>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #475569;">3. You\'ll receive a shipping confirmation with tracking info</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>

<!-- Progress Indicator -->
<table style="margin-bottom: 32px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="width: 33.33%; padding: 0 8px;" align="center">
<div style="width: 40px; height: 40px; background-color: #22c55e; border-radius: 50%; margin: 0 auto 8px; line-height: 40px; color: #ffffff; font-weight: bold; text-align: center;">✓</div>
<div style="font-size: 12px; color: #22c55e; font-weight: 600;">Order Placed</div></td>
<td style="width: 33.33%; padding: 0 8px;" align="center">
<div style="width: 40px; height: 40px; background-color: #22c55e; border-radius: 50%; margin: 0 auto 8px; line-height: 40px; color: #ffffff; font-weight: bold; text-align: center;">✓</div>
<div style="font-size: 12px; color: #22c55e; font-weight: 600;">ID Verified</div></td>
<td style="width: 33.33%; padding: 0 8px;" align="center">
<div style="width: 40px; height: 40px; background-color: #3b82f6; border-radius: 50%; margin: 0 auto 8px; line-height: 40px; color: #ffffff; font-weight: bold; text-align: center;">3</div>
<div style="font-size: 12px; color: #3b82f6; font-weight: 600;">Processing</div></td>
</tr>
</tbody>
</table>

<p style="margin: 0 0 24px; font-size: 15px; line-height: 1.6; color: #475569; text-align: center;">Thank you for your patience during the verification process.<br>We appreciate your business! 🎉</p>

<!-- Data Deletion Notice -->
<table style="margin-bottom: 24px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 16px; background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px;">
<div style="font-size: 13px; font-weight: 600; color: #166534; margin-bottom: 8px;">🔒 Your Privacy</div>
<div style="font-size: 13px; color: #15803d; line-height: 1.5;">Your verification data (ID images and selfie) will be <strong>automatically deleted from Stripe within 30 days</strong>. We do not store this data on our servers.</div>
<div style="font-size: 13px; color: #15803d; line-height: 1.5; margin-top: 8px;">If you\'d like your data deleted sooner, please <a href="mailto:[dsic_support_email]?subject=Order%20#{order_number}%20-%20Data%20Deletion%20Request" style="color: #166534; text-decoration: underline;">contact our support team</a> and we\'ll action your request within 24 hours.</div>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
<!-- Footer -->
<tr>
<td style="background-color: #f8fafc; padding: 32px 40px; border-top: 1px solid #e2e8f0;">
<p style="margin: 0 0 16px; font-size: 15px; color: #0f172a; text-align: center;">Thank you for shopping with <strong>[dsic_site_name]</strong>!</p>
<p style="margin: 0 0 24px; font-size: 14px; color: #64748b; text-align: center; line-height: 1.6;">Questions about your order?
<a style="color: #3b82f6; text-decoration: underline;" href="mailto:[dsic_support_email]?subject=Order%20#{order_number}%20-%20Question">Contact our support team</a></p>
<p style="margin: 0; font-size: 11px; color: #94a3b8; text-align: center; line-height: 1.5;">© ' . gmdate( 'Y' ) . ' [dsic_site_name]. All rights reserved.<br>This is an automated email regarding your order.</p>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>';
	}

	/**
	 * Get verification failed email template.
	 *
	 * @since 0.3.7
	 * @return string HTML template.
	 */
	private function get_verification_failed_template(): string {
		return '<table style="margin: 0; padding: 0; background-color: #f5f7fa;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 40px 20px;" align="center">
<table style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);" role="presentation" border="0" width="600" cellspacing="0" cellpadding="0">
<tbody>
<!-- Warning Badge -->
<tr>
<td style="padding: 32px 40px 0;">
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding-bottom: 24px;" align="center">
<table style="background-color: #fef3c7; border-radius: 50px;" role="presentation" border="0" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 8px 20px;"><span style="color: #d97706; font-size: 14px; font-weight: 600; letter-spacing: 0.5px;">Let\'s Sort This Out</span></td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
<!-- Main Content -->
<tr>
<td style="padding: 0 40px 32px;">
<h1 style="margin: 0 0 16px; font-size: 24px; font-weight: 600; color: #0f172a; line-height: 1.3;">Hi [dsic_customer_first_name],</h1>
<p style="margin: 0 0 24px; font-size: 16px; line-height: 1.6; color: #475569;">We were unable to complete the identity verification for your order <strong style="color: #0f172a;">#{order_number}</strong>. Don\'t worry – this can happen for various reasons and is usually easy to resolve.</p>

<!-- Order Info Card -->
<table style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 24px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 24px;">
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 8px 0; font-size: 15px; color: #475569;">Order Number</td>
<td style="padding: 8px 0; font-size: 15px; color: #0f172a; font-weight: 600;" align="right">#{order_number}</td>
</tr>
<tr>
<td style="padding: 8px 0; font-size: 15px; color: #475569;">Order Date</td>
<td style="padding: 8px 0; font-size: 15px; color: #0f172a; font-weight: 600;" align="right">{order_date}</td>
</tr>
<tr>
<td style="padding: 8px 0; font-size: 15px; color: #475569;">Verification Status</td>
<td style="padding: 8px 0; font-size: 15px; color: #dc2626; font-weight: 600;" align="right">✗ Needs Attention</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>

<!-- Common Reasons Card -->
<table style="margin-bottom: 24px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 20px; background-color: #fef2f2; border-left: 4px solid #dc2626; border-radius: 4px;">
<div style="font-size: 14px; font-weight: 600; color: #991b1b; margin-bottom: 12px;">Common reasons this can happen:</div>
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #7f1d1d;">• The document image was unclear or partially obscured</td>
</tr>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #7f1d1d;">• The selfie photo didn\'t match the document photo</td>
</tr>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #7f1d1d;">• The document has expired or is not a supported type</td>
</tr>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #7f1d1d;">• The verification session timed out</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>

<!-- Important: Do Not Retry Card -->
<table style="margin-bottom: 24px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 20px; background-color: #fefce8; border-left: 4px solid #eab308; border-radius: 4px;">
<div style="font-size: 14px; font-weight: 600; color: #854d0e; margin-bottom: 12px;">⚠️ Please don\'t attempt the verification again just yet</div>
<div style="font-size: 14px; color: #713f12; line-height: 1.6;">No worries — this can happen for a number of reasons and is usually easy to sort out. Simply <strong>reply to this email</strong> with any concerns or questions and our team will help you within 24 hours (or within 48 hours over the weekend). We\'ll guide you through the next steps personally.</div>
</td>
</tr>
</tbody>
</table>

<!-- What Happens Next Card -->
<table style="margin-bottom: 24px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 20px; background-color: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 4px;">
<div style="font-size: 14px; font-weight: 600; color: #1e40af; margin-bottom: 12px;">📧 What happens next:</div>
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #475569;">1. Our team will review the verification details</td>
</tr>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #475569;">2. We\'ll identify what caused the issue</td>
</tr>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #475569;">3. We\'ll contact you with specific instructions or alternative options</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>

<!-- Progress Indicator -->
<table style="margin-bottom: 32px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="width: 33.33%; padding: 0 8px;" align="center">
<div style="width: 40px; height: 40px; background-color: #22c55e; border-radius: 50%; margin: 0 auto 8px; line-height: 40px; color: #ffffff; font-weight: bold; text-align: center;">✓</div>
<div style="font-size: 12px; color: #22c55e; font-weight: 600;">Order Placed</div></td>
<td style="width: 33.33%; padding: 0 8px;" align="center">
<div style="width: 40px; height: 40px; background-color: #dc2626; border-radius: 50%; margin: 0 auto 8px; line-height: 40px; color: #ffffff; font-weight: bold; text-align: center;">!</div>
<div style="font-size: 12px; color: #dc2626; font-weight: 600;">Verify ID</div></td>
<td style="width: 33.33%; padding: 0 8px;" align="center">
<div style="width: 40px; height: 40px; background-color: #e2e8f0; border-radius: 50%; margin: 0 auto 8px; line-height: 40px; color: #64748b; font-weight: bold; text-align: center;">3</div>
<div style="font-size: 12px; color: #64748b;">Processing</div></td>
</tr>
</tbody>
</table>

<p style="margin: 0 0 24px; font-size: 14px; line-height: 1.6; color: #64748b; text-align: center;">We apologize for any inconvenience. Our verification process helps protect all customers from fraud.</p>

<!-- Data Deletion Notice -->
<table style="margin-bottom: 24px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 16px; background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px;">
<div style="font-size: 13px; font-weight: 600; color: #166534; margin-bottom: 8px;">🔒 Your Privacy</div>
<div style="font-size: 13px; color: #15803d; line-height: 1.5;">Your verification data (ID images and selfie) will be <strong>automatically deleted from Stripe within 30 days</strong>. We do not store this data on our servers.</div>
<div style="font-size: 13px; color: #15803d; line-height: 1.5; margin-top: 8px;">If you\'d like your data deleted sooner, please <a href="mailto:[dsic_support_email]?subject=Order%20#{order_number}%20-%20Data%20Deletion%20Request" style="color: #166534; text-decoration: underline;">contact our support team</a> and we\'ll action your request within 24 hours.</div>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
<!-- Footer -->
<tr>
<td style="background-color: #f8fafc; padding: 32px 40px; border-top: 1px solid #e2e8f0;">
<p style="margin: 0 0 16px; font-size: 15px; color: #0f172a; text-align: center;">Have a question or need help sorting this out?</p>
<p style="margin: 0 0 24px; font-size: 15px; color: #475569; text-align: center; line-height: 1.6;">Simply <strong>reply to this email</strong> — our team will help you within 24 hours (or 48 hours over the weekend).</p>
<p style="margin: 0; font-size: 11px; color: #94a3b8; text-align: center; line-height: 1.5;">© ' . gmdate( 'Y' ) . ' [dsic_site_name]. All rights reserved.<br>This is an automated email regarding your order.</p>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>';
	}

	/**
	 * Get data redaction email template.
	 *
	 * @since 0.5.6
	 * @return string HTML template.
	 */
	private function get_data_redaction_template(): string {
		return '<table style="margin: 0; padding: 0; background-color: #f5f7fa;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 40px 20px;" align="center">
<table style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);" role="presentation" border="0" width="600" cellspacing="0" cellpadding="0">
<tbody>
<!-- Data Deleted Badge -->
<tr>
<td style="padding: 32px 40px 0;">
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding-bottom: 24px;" align="center">
<table style="background-color: #f0fdf4; border-radius: 50px;" role="presentation" border="0" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 8px 20px;"><span style="color: #166534; font-size: 14px; font-weight: 600; letter-spacing: 0.5px;">🗑️ DATA DELETED</span></td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
<!-- Main Content -->
<tr>
<td style="padding: 0 40px 32px;">
<h1 style="margin: 0 0 16px; font-size: 24px; font-weight: 600; color: #0f172a; line-height: 1.3;">Hi [dsic_customer_first_name],</h1>
<p style="margin: 0 0 24px; font-size: 16px; line-height: 1.6; color: #475569;">This email confirms that the identity verification data associated with your order <strong style="color: #0f172a;">#{order_number}</strong> has been <strong style="color: #166534;">permanently deleted</strong> from Stripe\'s systems.</p>

<!-- Order Info Card -->
<table style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 24px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 24px;">
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 8px 0; font-size: 15px; color: #475569;">Order Number</td>
<td style="padding: 8px 0; font-size: 15px; color: #0f172a; font-weight: 600;" align="right">#{order_number}</td>
</tr>
<tr>
<td style="padding: 8px 0; font-size: 15px; color: #475569;">Order Date</td>
<td style="padding: 8px 0; font-size: 15px; color: #0f172a; font-weight: 600;" align="right">{order_date}</td>
</tr>
<tr>
<td style="padding: 8px 0; font-size: 15px; color: #475569;">Data Status</td>
<td style="padding: 8px 0; font-size: 15px; color: #166534; font-weight: 600;" align="right">✓ Permanently Deleted</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>

<!-- What Was Deleted Card -->
<table style="margin-bottom: 24px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 20px; background-color: #f0fdf4; border-left: 4px solid #22c55e; border-radius: 4px;">
<div style="font-size: 14px; font-weight: 600; color: #166534; margin-bottom: 12px;">🗑️ Data that has been deleted:</div>
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #15803d;">✓ Government-issued ID document images</td>
</tr>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #15803d;">✓ Selfie photographs</td>
</tr>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #15803d;">✓ Extracted personal information from documents</td>
</tr>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #15803d;">✓ Biometric data used for matching</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>

<!-- What We Retain Card -->
<table style="margin-bottom: 24px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 20px; background-color: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 4px;">
<div style="font-size: 14px; font-weight: 600; color: #1e40af; margin-bottom: 12px;">📋 What we retain for order records:</div>
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #475569;">• Verification status (pass/fail)</td>
</tr>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #475569;">• Timestamp of verification</td>
</tr>
<tr>
<td style="padding: 6px 0; font-size: 14px; color: #475569;">• Order reference information</td>
</tr>
</tbody>
</table>
<div style="font-size: 13px; color: #64748b; margin-top: 8px; font-style: italic;">This minimal information is retained for fraud prevention and order management purposes only.</div>
</td>
</tr>
</tbody>
</table>

<p style="margin: 0 0 24px; font-size: 14px; line-height: 1.6; color: #64748b; text-align: center;">Thank you for your trust in us. Your privacy is important to us.</p>

<!-- Privacy Info Card -->
<table style="margin-bottom: 24px;" role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 16px; background-color: #fafafa; border: 1px solid #e5e5e5; border-radius: 4px;">
<div style="font-size: 13px; font-weight: 600; color: #525252; margin-bottom: 8px;">🔒 About Data Deletion</div>
<div style="font-size: 13px; color: #737373; line-height: 1.5;">This deletion was processed through Stripe\'s secure systems. Once deleted, this data cannot be recovered. <a href="https://support.stripe.com/questions/managing-your-id-verification-information" style="color: #3b82f6; text-decoration: underline;" target="_blank">Learn more about Stripe\'s privacy practices</a>.</div>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
<!-- Footer -->
<tr>
<td style="background-color: #f8fafc; padding: 32px 40px; border-top: 1px solid #e2e8f0;">
<p style="margin: 0 0 16px; font-size: 15px; color: #0f172a; text-align: center;">Questions? We\'re here to help.</p>
<p style="margin: 0 0 24px; font-size: 14px; color: #64748b; text-align: center; line-height: 1.6;">
<a style="color: #3b82f6; text-decoration: underline;" href="mailto:[dsic_support_email]?subject=Order%20#{order_number}%20-%20Data%20Deletion%20Question">Contact our support team</a></p>
<p style="margin: 0; font-size: 11px; color: #94a3b8; text-align: center; line-height: 1.5;">© ' . gmdate( 'Y' ) . ' [dsic_site_name]. All rights reserved.<br>This is an automated email regarding your order.</p>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>';
	}

	/**
	 * Get email template settings for a specific type.
	 *
	 * @since 0.3.2
	 * @param string $type Email type.
	 * @return array Template settings.
	 */
	public function get_email_template( string $type ): array {
		return array(
			'enabled' => $this->get_bool_option( 'dsic_email_' . $type . '_enabled', true ),
			'subject' => get_option( 'dsic_email_' . $type . '_subject', $this->get_default_email_subject( $type ) ),
			'heading' => get_option( 'dsic_email_' . $type . '_heading', $this->get_default_email_heading( $type ) ),
			'body'    => get_option( 'dsic_email_' . $type . '_body', $this->get_default_email_body( $type ) ),
		);
	}

	/**
	 * Get translated email template settings for a specific order.
	 *
	 * Returns email template with strings translated to the order's language.
	 * Falls back to default language if no translation exists.
	 *
	 * @since 1.0.1
	 * @param string       $type  Email type.
	 * @param WC_Order|int $order Order object or ID for language detection.
	 * @return array Template settings with translated strings.
	 */
	public function get_translated_email_template( string $type, $order ): array {
		$template = $this->get_email_template( $type );

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
			$template['subject'] = $this->get_default_email_subject( $type );
		}
		if ( empty( $template['heading'] ) ) {
			$template['heading'] = $this->get_default_email_heading( $type );
		}
		if ( empty( $template['body'] ) ) {
			$template['body'] = $this->get_default_email_body( $type );
		}

		return $template;
	}

	/**
	 * Captured mail error for test email feedback.
	 *
	 * @since 1.3.0
	 * @var string|null
	 */
	private ?string $mail_error = null;

	/**
	 * Capture wp_mail errors for detailed feedback.
	 *
	 * @since 1.3.0
	 * @param WP_Error $error The WP_Error object containing mail error details.
	 * @return void
	 */
	public function capture_mail_error( WP_Error $error ): void {
		$this->mail_error = $error->get_error_message();
	}

	/**
	 * AJAX handler for sending test email.
	 *
	 * @since 0.3.2
	 * @return void
	 */
	public function ajax_send_test_email(): void {
		// Verify nonce.
		check_ajax_referer( 'dsic_nonce', 'nonce' );

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized access.', 'droix-stripe-id-check' ) )
			);
		}

		$email_type = isset( $_POST['email_type'] ) ? sanitize_key( $_POST['email_type'] ) : '';
		$recipient  = isset( $_POST['recipient'] ) ? sanitize_email( wp_unslash( $_POST['recipient'] ) ) : '';

		if ( empty( $email_type ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Email type is required.', 'droix-stripe-id-check' ) )
			);
		}

		if ( empty( $recipient ) ) {
			$recipient = wp_get_current_user()->user_email;
		}

		// Validate email address.
		if ( ! is_email( $recipient ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid email address provided.', 'droix-stripe-id-check' ) )
			);
		}

		// Get sample order for test data.
		$sample_order = $this->get_sample_order();

		// Get email template.
		$template = $this->get_email_template( $email_type );

		// Check if template is enabled.
		if ( empty( $template['body'] ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Email template body is empty. Please configure the template first.', 'droix-stripe-id-check' ) )
			);
		}

		// Process shortcodes and placeholders.
		$subject = $this->process_email_placeholders( $template['subject'], $sample_order );
		$heading = $this->process_email_placeholders( $template['heading'], $sample_order );
		$body    = $this->process_email_placeholders( $template['body'], $sample_order );

		// Process shortcodes.
		if ( $sample_order ) {
			DSIC_Shortcodes::set_order_context( $sample_order, home_url( '/test-verification-link/' ) );
			$body = do_shortcode( $body );
			DSIC_Shortcodes::clear_order_context();
		}

		// Build email content.
		$message = $this->build_email_html( $heading, $body );

		// Reset any previous error.
		$this->mail_error = null;

		// Hook into wp_mail_failed to capture detailed errors.
		add_action( 'wp_mail_failed', array( $this, 'capture_mail_error' ) );

		// Send email.
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$sent    = wp_mail( $recipient, '[TEST] ' . $subject, $message, $headers );

		// Remove the error capture hook.
		remove_action( 'wp_mail_failed', array( $this, 'capture_mail_error' ) );

		if ( $sent ) {
			DSIC_Logger::info( 'Test email sent successfully to ' . $recipient . ' (type: ' . $email_type . ')' );
			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %s: recipient email address */
						__( '✓ Test email sent successfully to %s', 'droix-stripe-id-check' ),
						$recipient
					),
				)
			);
		} else {
			// Build detailed error message.
			$error_details = $this->mail_error
				? $this->mail_error
				: __( 'Unknown error. Check your server email configuration.', 'droix-stripe-id-check' );

			DSIC_Logger::error( 'Failed to send test email to ' . $recipient . '. Error: ' . $error_details );

			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: error details */
						__( 'Failed to send test email: %s', 'droix-stripe-id-check' ),
						$error_details
					),
				)
			);
		}
	}

	/**
	 * Get a sample order for test emails.
	 *
	 * @since 0.3.2
	 * @return WC_Order|null Sample order or null.
	 */
	private function get_sample_order(): ?WC_Order {
		$orders = wc_get_orders(
			array(
				'limit'   => 1,
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);

		return ! empty( $orders ) ? $orders[0] : null;
	}

	/**
	 * Process email placeholders.
	 *
	 * @since 0.3.2
	 * @param string        $content Content with placeholders.
	 * @param WC_Order|null $order   Order object.
	 * @return string Processed content.
	 */
	private function process_email_placeholders( string $content, ?WC_Order $order ): string {
		$replacements = array(
			'{site_title}'   => get_bloginfo( 'name' ),
			'{site_url}'     => home_url(),
			'{order_number}' => $order ? $order->get_order_number() : '12345',
			'{order_date}'   => $order ? wc_format_datetime( $order->get_date_created() ) : wp_date( get_option( 'date_format' ) ),
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $content );
	}

	/**
	 * Build email HTML with WooCommerce styling.
	 *
	 * @since 0.3.2
	 * @param string $heading Email heading.
	 * @param string $body    Email body content.
	 * @return string Full HTML email.
	 */
	private function build_email_html( string $heading, string $body ): string {
		ob_start();
		wc_get_template( 'emails/email-header.php', array( 'email_heading' => $heading ) );
		echo wp_kses_post( wpautop( $body ) );
		wc_get_template( 'emails/email-footer.php' );
		return ob_get_clean();
	}

	/**
	 * AJAX handler for resetting email template to default.
	 *
	 * @since 0.3.2
	 * @return void
	 */
	public function ajax_reset_email_template(): void {
		// Verify nonce.
		check_ajax_referer( 'dsic_nonce', 'nonce' );

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized access.', 'droix-stripe-id-check' ) )
			);
		}

		$email_type = isset( $_POST['email_type'] ) ? sanitize_key( $_POST['email_type'] ) : '';

		if ( empty( $email_type ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Email type is required.', 'droix-stripe-id-check' ) )
			);
		}

		// Reset to defaults.
		update_option( 'dsic_email_' . $email_type . '_subject', $this->get_default_email_subject( $email_type ) );
		update_option( 'dsic_email_' . $email_type . '_heading', $this->get_default_email_heading( $email_type ) );
		update_option( 'dsic_email_' . $email_type . '_body', $this->get_default_email_body( $email_type ) );

		// Delete WooCommerce's own email settings option so WC falls back to the class defaults.
		// WC stores subject/heading overrides in woocommerce_dsic_{type}_settings when edited via
		// WooCommerce → Settings → Emails. These must be cleared on reset or WC shows stale values.
		delete_option( 'woocommerce_dsic_' . $email_type . '_settings' );

		DSIC_Logger::info( 'Email template reset to default: ' . $email_type );

		wp_send_json_success(
			array(
				'message' => __( 'Template reset to default.', 'droix-stripe-id-check' ),
				'subject' => $this->get_default_email_subject( $email_type ),
				'heading' => $this->get_default_email_heading( $email_type ),
				'body'    => $this->get_default_email_body( $email_type ),
			)
		);
	}

	/**
	 * AJAX handler for resetting ALL email templates to defaults.
	 *
	 * @since 0.3.7
	 * @return void
	 */
	public function ajax_reset_all_email_templates(): void {
		// Verify nonce.
		check_ajax_referer( 'dsic_nonce', 'nonce' );

		// Check capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized access.', 'droix-stripe-id-check' ) )
			);
		}

		$email_types = array( 'verification_request', 'verification_passed', 'verification_failed', 'data_redaction' );
		$templates   = array();

		foreach ( $email_types as $type ) {
			// Reset to defaults - use '1' string for consistency with form handling.
			update_option( 'dsic_email_' . $type . '_enabled', '1' );
			update_option( 'dsic_email_' . $type . '_subject', $this->get_default_email_subject( $type ) );
			update_option( 'dsic_email_' . $type . '_heading', $this->get_default_email_heading( $type ) );
			update_option( 'dsic_email_' . $type . '_body', $this->get_default_email_body( $type ) );

			// Delete WooCommerce's own email settings option so WC falls back to the class defaults.
			delete_option( 'woocommerce_dsic_' . $type . '_settings' );

			$templates[ $type ] = array(
				'enabled' => '1',
				'subject' => $this->get_default_email_subject( $type ),
				'heading' => $this->get_default_email_heading( $type ),
				'body'    => $this->get_default_email_body( $type ),
			);
		}

		DSIC_Logger::info( 'All email templates reset to defaults' );

		wp_send_json_success(
			array(
				'message'   => __( 'All templates have been reset to defaults.', 'droix-stripe-id-check' ),
				'templates' => $templates,
			)
		);
	}

	/**
	 * Ensure email templates have default values.
	 *
	 * Called on settings page load to populate empty templates.
	 *
	 * @since 0.3.7
	 * @return void
	 */
	public function ensure_email_template_defaults(): void {
		$email_types = array( 'verification_request', 'verification_passed', 'verification_failed', 'data_redaction' );

		foreach ( $email_types as $type ) {
			// Check if body is empty (most important field) or if option doesn't exist (false).
			$body = get_option( 'dsic_email_' . $type . '_body' );

			if ( false === $body || empty( $body ) ) {
				// Populate all fields for this template only if they don't exist.
				if ( false === get_option( 'dsic_email_' . $type . '_enabled' ) ) {
					add_option( 'dsic_email_' . $type . '_enabled', '1', '', false );
				}
				if ( false === get_option( 'dsic_email_' . $type . '_subject' ) ) {
					add_option( 'dsic_email_' . $type . '_subject', $this->get_default_email_subject( $type ), '', false );
				}
				if ( false === get_option( 'dsic_email_' . $type . '_heading' ) ) {
					add_option( 'dsic_email_' . $type . '_heading', $this->get_default_email_heading( $type ), '', false );
				}
				if ( false === $body ) {
					add_option( 'dsic_email_' . $type . '_body', $this->get_default_email_body( $type ), '', false );
				}
			}
		}
	}

}
