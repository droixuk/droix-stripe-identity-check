<?php
/**
 * Stripe API service class.
 *
 * Handles all communication with the Stripe Identity API.
 *
 * @package    DSIC
 * @subpackage DSIC/api
 * @since      0.0.1
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Stripe_API
 *
 * @since 0.0.1
 */
class DSIC_Stripe_API {

	/**
	 * Stripe API base URL.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	private const API_BASE_URL = 'https://api.stripe.com/v1';

	/**
	 * Stripe secret key.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	private string $secret_key;

	/**
	 * Whether in test mode.
	 *
	 * @since 1.2.0
	 * @var bool
	 */
	private bool $test_mode;

	/**
	 * Request timeout in seconds.
	 *
	 * @since 0.0.1
	 * @var int
	 */
	private int $timeout = 30;

	/**
	 * Constructor.
	 *
	 * Automatically selects the correct API key based on test mode setting.
	 *
	 * @since 0.0.1
	 * @param string|null $secret_key Optional. Stripe secret key. If not provided, uses saved setting based on mode.
	 */
	public function __construct( ?string $secret_key = null ) {
		$this->test_mode = (bool) get_option( 'dsic_test_mode', '1' );

		if ( null !== $secret_key ) {
			$this->secret_key = $secret_key;
		} else {
			// Use the appropriate key based on test mode.
			if ( $this->test_mode ) {
				$this->secret_key = get_option( 'dsic_test_secret_key', '' );
			} else {
				$this->secret_key = get_option( 'dsic_live_secret_key', '' );
			}
		}
	}

	/**
	 * Check if using test mode.
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	public function is_test_mode(): bool {
		return $this->test_mode;
	}

	/**
	 * Get the active publishable key.
	 *
	 * @since 1.2.0
	 * @return string
	 */
	public static function get_publishable_key(): string {
		$test_mode = (bool) get_option( 'dsic_test_mode', '1' );
		if ( $test_mode ) {
			return get_option( 'dsic_test_publishable_key', '' );
		}
		return get_option( 'dsic_live_publishable_key', '' );
	}

	/**
	 * Get the active webhook secret.
	 *
	 * @since 1.2.0
	 * @return string
	 */
	public static function get_webhook_secret(): string {
		$test_mode = (bool) get_option( 'dsic_test_mode', '1' );
		if ( $test_mode ) {
			return get_option( 'dsic_test_webhook_secret', '' );
		}
		return get_option( 'dsic_live_webhook_secret', '' );
	}

	/**
	 * Get default request headers.
	 *
	 * @since 0.0.1
	 * @return array
	 */
	private function get_headers(): array {
		return array(
			'Authorization'  => 'Bearer ' . $this->secret_key,
			'Content-Type'   => 'application/x-www-form-urlencoded',
			'Stripe-Version' => DSIC_STRIPE_API_VERSION,
		);
	}

	/**
	 * Make a GET request to the Stripe API.
	 *
	 * @since 0.0.1
	 * @param string $endpoint API endpoint.
	 * @param array  $params   Optional. Query parameters.
	 * @return array|WP_Error Response data or error.
	 */
	private function get( string $endpoint, array $params = array() ) {
		$url = self::API_BASE_URL . $endpoint;

		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		$response = wp_remote_get(
			$url,
			array(
				'headers' => $this->get_headers(),
				'timeout' => $this->timeout,
			)
		);

		return $this->handle_response( $response );
	}

	/**
	 * Make a POST request to the Stripe API.
	 *
	 * @since 0.0.1
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Request body data.
	 * @return array|WP_Error Response data or error.
	 */
	private function post( string $endpoint, array $data = array() ) {
		$response = wp_remote_post(
			self::API_BASE_URL . $endpoint,
			array(
				'headers' => $this->get_headers(),
				'body'    => $data,
				'timeout' => $this->timeout,
			)
		);

		return $this->handle_response( $response );
	}

	/**
	 * Handle API response.
	 *
	 * @since 0.0.1
	 * @param array|WP_Error $response API response.
	 * @return array|WP_Error Parsed response or error.
	 */
	private function handle_response( $response ) {
		if ( is_wp_error( $response ) ) {
			DSIC_Logger::error( 'Stripe API request error: ' . $response->get_error_message() );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			DSIC_Logger::error( 'Stripe API JSON decode error: ' . json_last_error_msg() );
			return new WP_Error( 'dsic_json_error', __( 'Failed to parse API response.', 'droix-stripe-id-check' ) );
		}

		// Handle error responses.
		if ( $code < 200 || $code >= 300 ) {
			$error_message = $data['error']['message'] ?? __( 'Unknown API error.', 'droix-stripe-id-check' );
			$error_type    = $data['error']['type'] ?? 'api_error';
			$error_code    = $data['error']['code'] ?? '';

			DSIC_Logger::error(
				sprintf(
					'Stripe API error (%d): %s [%s: %s]',
					$code,
					$error_message,
					$error_type,
					$error_code
				)
			);

			return new WP_Error(
				'dsic_stripe_error',
				$error_message,
				array(
					'http_code'  => $code,
					'error_type' => $error_type,
					'error_code' => $error_code,
				)
			);
		}

		return $data;
	}

	/**
	 * Test the API connection.
	 *
	 * @since 0.0.1
	 * @return array|WP_Error Account info on success, error on failure.
	 */
	public function test_connection() {
		if ( empty( $this->secret_key ) ) {
			return new WP_Error( 'dsic_no_key', __( 'API secret key is not configured.', 'droix-stripe-id-check' ) );
		}

		$result = $this->get( '/account' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'      => true,
			'account_id'   => $result['id'] ?? '',
			'account_name' => $result['settings']['dashboard']['display_name'] ?? $result['business_profile']['name'] ?? '',
			'country'      => $result['country'] ?? '',
			'email'        => $result['email'] ?? '',
		);
	}

	/**
	 * Create a verification session.
	 *
	 * @since 0.0.1
	 * @param int   $order_id  WooCommerce order ID.
	 * @param array $metadata  Additional metadata.
	 * @return array|WP_Error Session data or error.
	 */
	public function create_verification_session( int $order_id, array $metadata = array() ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'dsic_invalid_order', __( 'Invalid order ID.', 'droix-stripe-id-check' ) );
		}

		// Build return URL.
		$return_url = add_query_arg(
			array(
				'wc-api'   => 'dsic_verification_return',
				'order_id' => $order_id,
			),
			home_url( '/' )
		);

		// Build metadata.
		$session_metadata = array_merge(
			array(
				'order_id'       => $order_id,
				'customer_id'    => $order->get_customer_id(),
				'customer_email' => $order->get_billing_email(),
				'source'         => 'droix-stripe-id-check',
				'site_url'       => home_url(),
			),
			$metadata
		);

		// Get verification options from settings.
		$require_selfie       = get_option( 'dsic_require_selfie', '1' ) === '1';
		$require_id_number    = get_option( 'dsic_require_id_number', '0' ) === '1';
		$require_live_capture = get_option( 'dsic_require_live_capture', '1' ) === '1';
		$prefill_phone        = get_option( 'dsic_prefill_phone', '1' ) === '1';
		$allowed_doc_types    = get_option( 'dsic_allowed_document_types', array( 'driving_license', 'id_card', 'passport' ) );

		// Build request data.
		$data = array(
			'type'       => 'document',
			'return_url' => $return_url,
		);

		// Note: Stripe Identity verification sessions have a fixed expiry of 90 days.
		// The expires_after_seconds parameter is not supported by the Identity API.
		// It's only available for Checkout Sessions and Payment Links.

		// Add selfie requirement.
		if ( $require_selfie ) {
			$data['options[document][require_matching_selfie]'] = 'true';
		}

		// Add ID number requirement.
		if ( $require_id_number ) {
			$data['options[document][require_id_number]'] = 'true';
		}

		// Add live capture requirement.
		if ( $require_live_capture ) {
			$data['options[document][require_live_capture]'] = 'true';
		}

		// Add allowed document types.
		// Note: Stripe API uses 'allowed_types' not 'allowed_document_types'.
		$doc_index = 0;
		foreach ( $allowed_doc_types as $doc_type ) {
			$data[ 'options[document][allowed_types][' . $doc_index . ']' ] = $doc_type;
			$doc_index++;
		}

		// Add provided email for improved verification outcomes.
		// Note: options[email] and options[phone] are NOT valid Stripe Identity API parameters.
		// Instead, we use provided_details to pre-fill customer contact info.
		$customer_email = $order->get_billing_email();
		if ( ! empty( $customer_email ) ) {
			$data['provided_details[email]'] = $customer_email;
		}

		// Add provided phone for improved verification outcomes.
		// Stripe requires E.164 format (e.g., +447477023030).
		// Only send if setting enabled and phone appears to be in valid format (starts with +).
		if ( $prefill_phone ) {
			$customer_phone = $order->get_billing_phone();
			if ( ! empty( $customer_phone ) && strpos( $customer_phone, '+' ) === 0 ) {
				$data['provided_details[phone]'] = $customer_phone;
			}
		}

		// Add metadata.
		foreach ( $session_metadata as $key => $value ) {
			$data[ 'metadata[' . $key . ']' ] = $value;
		}

		DSIC_Logger::info( 'Creating verification session for order #' . $order_id . ' with options: selfie=' . ( $require_selfie ? 'yes' : 'no' ) . ', id_number=' . ( $require_id_number ? 'yes' : 'no' ) . ', live_capture=' . ( $require_live_capture ? 'yes' : 'no' ) . ', prefill_phone=' . ( $prefill_phone ? 'yes' : 'no' ) );

		$result = $this->post( '/identity/verification_sessions', $data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		DSIC_Logger::info(
			sprintf(
				'Verification session created: %s for order #%d',
				$result['id'] ?? 'unknown',
				$order_id
			)
		);

		return array(
			'session_id'  => $result['id'] ?? '',
			'url'         => $result['url'] ?? '',
			'status'      => $result['status'] ?? '',
			'client_secret' => $result['client_secret'] ?? '',
			'created'     => $result['created'] ?? 0,
			'livemode'    => $result['livemode'] ?? false,
		);
	}

	/**
	 * Get a verification session.
	 *
	 * @since 0.0.1
	 * @param string $session_id Stripe session ID.
	 * @return array|WP_Error Session data or error.
	 */
	public function get_verification_session( string $session_id ) {
		if ( empty( $session_id ) ) {
			return new WP_Error( 'dsic_no_session', __( 'Session ID is required.', 'droix-stripe-id-check' ) );
		}

		$result = $this->get( '/identity/verification_sessions/' . $session_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'session_id'       => $result['id'] ?? '',
			'status'           => $result['status'] ?? '',
			'type'             => $result['type'] ?? '',
			'url'              => $result['url'] ?? '',
			'created'          => $result['created'] ?? 0,
			'livemode'         => $result['livemode'] ?? false,
			'metadata'         => $result['metadata'] ?? array(),
			'last_error'       => $result['last_error'] ?? null,
			'verified_outputs' => $result['verified_outputs'] ?? null,
		);
	}

	/**
	 * Redact a verification session (GDPR compliance).
	 *
	 * @since 0.0.1
	 * @param string $session_id Stripe session ID.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function redact_verification_session( string $session_id ) {
		if ( empty( $session_id ) ) {
			return new WP_Error( 'dsic_no_session', __( 'Session ID is required.', 'droix-stripe-id-check' ) );
		}

		DSIC_Logger::info( 'Redacting verification session: ' . $session_id );

		$result = $this->post( '/identity/verification_sessions/' . $session_id . '/redact' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Cancel a verification session.
	 *
	 * @since 0.0.1
	 * @param string $session_id Stripe session ID.
	 * @return array|WP_Error Session data or error.
	 */
	public function cancel_verification_session( string $session_id ) {
		if ( empty( $session_id ) ) {
			return new WP_Error( 'dsic_no_session', __( 'Session ID is required.', 'droix-stripe-id-check' ) );
		}

		DSIC_Logger::info( 'Canceling verification session: ' . $session_id );

		$result = $this->post( '/identity/verification_sessions/' . $session_id . '/cancel' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'session_id' => $result['id'] ?? '',
			'status'     => $result['status'] ?? '',
		);
	}

	/**
	 * List verification sessions.
	 *
	 * @since 0.0.1
	 * @param array $params Query parameters.
	 * @return array|WP_Error List of sessions or error.
	 */
	public function list_verification_sessions( array $params = array() ) {
		$defaults = array(
			'limit' => 10,
		);

		$params = array_merge( $defaults, $params );

		$result = $this->get( '/identity/verification_sessions', $params );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'sessions' => $result['data'] ?? array(),
			'has_more' => $result['has_more'] ?? false,
		);
	}

	/**
	 * Check if a session URL is still valid (not expired).
	 *
	 * @since 0.0.1
	 * @param string $session_id Stripe session ID.
	 * @return bool True if valid, false if expired or error.
	 */
	public function is_session_valid( string $session_id ): bool {
		$session = $this->get_verification_session( $session_id );

		if ( is_wp_error( $session ) ) {
			return false;
		}

		// Session is valid if status is 'requires_input' (pending verification).
		return 'requires_input' === $session['status'];
	}

	/**
	 * Get the active Radar secret key.
	 *
	 * Returns the Radar-specific key if configured, otherwise falls back
	 * to the Identity secret key.
	 *
	 * @since 1.8.0
	 * @return string
	 */
	/**
	 * Get the Radar webhook signing secret.
	 *
	 * Returns a Radar-specific webhook secret if configured (needed when Radar
	 * runs on a separate Stripe account from Identity). Falls back to the main
	 * Identity webhook secret if no Radar-specific secret is set.
	 *
	 * @since 1.9.6
	 * @return string
	 */
	public static function get_radar_webhook_secret(): string {
		$test_mode = (bool) get_option( 'dsic_test_mode', '1' );

		if ( $test_mode ) {
			$radar_secret = get_option( 'dsic_radar_test_webhook_secret', '' );
			if ( ! empty( $radar_secret ) ) {
				return $radar_secret;
			}
			return get_option( 'dsic_test_webhook_secret', '' );
		}

		$radar_secret = get_option( 'dsic_radar_live_webhook_secret', '' );
		if ( ! empty( $radar_secret ) ) {
			return $radar_secret;
		}
		return get_option( 'dsic_live_webhook_secret', '' );
	}

	public static function get_radar_secret_key(): string {
		$test_mode = (bool) get_option( 'dsic_test_mode', '1' );

		if ( $test_mode ) {
			$radar_key = get_option( 'dsic_radar_test_secret_key', '' );
			if ( ! empty( $radar_key ) ) {
				return $radar_key;
			}
			return get_option( 'dsic_test_secret_key', '' );
		}

		$radar_key = get_option( 'dsic_radar_live_secret_key', '' );
		if ( ! empty( $radar_key ) ) {
			return $radar_key;
		}
		return get_option( 'dsic_live_secret_key', '' );
	}

	/**
	 * Retrieve a charge from Stripe.
	 *
	 * @since 1.8.0
	 * @param string      $charge_id  Stripe charge ID (ch_xxx).
	 * @param string|null $secret_key Optional API key override.
	 * @return array|WP_Error Charge data or error.
	 */
	public function get_charge( string $charge_id, ?string $secret_key = null ) {
		if ( empty( $charge_id ) ) {
			return new WP_Error( 'dsic_no_charge', __( 'Charge ID is required.', 'droix-stripe-id-check' ) );
		}

		// Use Radar key if no override provided.
		if ( null !== $secret_key ) {
			$original_key       = $this->secret_key;
			$this->secret_key   = $secret_key;
		} else {
			$radar_key = self::get_radar_secret_key();
			if ( ! empty( $radar_key ) && $radar_key !== $this->secret_key ) {
				$original_key     = $this->secret_key;
				$this->secret_key = $radar_key;
			}
		}

		$result = $this->get( '/charges/' . $charge_id );

		// Restore original key if changed.
		if ( isset( $original_key ) ) {
			$this->secret_key = $original_key;
		}

		return $result;
	}

	/**
	 * Retrieve the latest charge for a payment intent.
	 *
	 * @since 1.8.0
	 * @param string      $pi_id      Stripe PaymentIntent ID (pi_xxx).
	 * @param string|null $secret_key Optional API key override.
	 * @return array|WP_Error Charge data or error.
	 */
	public function get_payment_intent_charge( string $pi_id, ?string $secret_key = null ) {
		if ( empty( $pi_id ) ) {
			return new WP_Error( 'dsic_no_pi', __( 'PaymentIntent ID is required.', 'droix-stripe-id-check' ) );
		}

		// Use Radar key if no override provided.
		$radar_key = null !== $secret_key ? $secret_key : self::get_radar_secret_key();
		if ( ! empty( $radar_key ) && $radar_key !== $this->secret_key ) {
			$original_key     = $this->secret_key;
			$this->secret_key = $radar_key;
		}

		$result = $this->get( '/payment_intents/' . $pi_id );

		// Restore original key if changed.
		if ( isset( $original_key ) ) {
			$this->secret_key = $original_key;
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Extract the latest charge ID.
		$charge_id = $result['latest_charge'] ?? '';

		if ( empty( $charge_id ) ) {
			return new WP_Error( 'dsic_no_charge', __( 'No charge found for this PaymentIntent.', 'droix-stripe-id-check' ) );
		}

		return $this->get_charge( $charge_id, $radar_key ?? null );
	}

	/**
	 * Get verification status constants.
	 *
	 * @since 0.0.1
	 * @return array
	 */
	public static function get_statuses(): array {
		return array(
			'requires_input' => __( 'Pending', 'droix-stripe-id-check' ),
			'processing'     => __( 'Processing', 'droix-stripe-id-check' ),
			'verified'       => __( 'Verified', 'droix-stripe-id-check' ),
			'canceled'       => __( 'Canceled', 'droix-stripe-id-check' ),
		);
	}
}
