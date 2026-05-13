<?php
/**
 * REST API Endpoints.
 *
 * Provides external REST API endpoints for Linnworks integration.
 *
 * @package    DSIC
 * @subpackage DSIC/api
 * @since      1.5.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_REST_API
 *
 * @since 1.5.0
 */
class DSIC_REST_API {

	/**
	 * REST namespace.
	 *
	 * @since 1.5.0
	 * @var string
	 */
	const NAMESPACE = 'dsic/v1';

	/**
	 * Initialize REST API routes.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function register_routes(): void {
		// Lock order endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/linnworks/lock',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_lock' ),
				'permission_callback' => array( __CLASS__, 'check_api_key' ),
				'args'                => self::get_order_args(),
			)
		);

		// Unlock order endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/linnworks/unlock',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_unlock' ),
				'permission_callback' => array( __CLASS__, 'check_api_key' ),
				'args'                => self::get_order_args(),
			)
		);

		// Status check endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/linnworks/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_status' ),
				'permission_callback' => array( __CLASS__, 'check_api_key' ),
			)
		);

		// Order status endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/linnworks/order/(?P<order_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_order_status' ),
				'permission_callback' => array( __CLASS__, 'check_api_key' ),
				'args'                => array(
					'order_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function( $value ) {
							return is_numeric( $value ) && $value > 0;
						},
					),
				),
			)
		);
	}

	/**
	 * Get common order endpoint arguments.
	 *
	 * @since 1.5.0
	 * @return array
	 */
	private static function get_order_args(): array {
		return array(
			'order_id' => array(
				'required'          => true,
				'type'              => 'integer',
				'description'       => __( 'WooCommerce order ID', 'droix-stripe-id-check' ),
				'sanitize_callback' => 'absint',
				'validate_callback' => function( $value ) {
					return is_numeric( $value ) && $value > 0;
				},
			),
		);
	}

	/**
	 * Check API key for authentication.
	 *
	 * @since 1.5.0
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public static function check_api_key( WP_REST_Request $request ) {
		// Check if Linnworks integration is enabled.
		if ( ! DSIC_Linnworks_API::is_enabled() ) {
			return new WP_Error(
				'dsic_integration_disabled',
				__( 'Linnworks integration is not enabled.', 'droix-stripe-id-check' ),
				array( 'status' => 403 )
			);
		}

		$client_ip = self::get_client_ip();
		if ( self::is_rate_limited( $client_ip ) ) {
			return new WP_Error(
				'dsic_api_key_rate_limited',
				__( 'Too many invalid API key attempts. Try again later.', 'droix-stripe-id-check' ),
				array( 'status' => 429 )
			);
		}

		// Get API key from header only. Avoid query/body tokens that can leak via logs.
		$provided_key = sanitize_text_field( wp_unslash( $request->get_header( 'X-DSIC-API-Key' ) ) );

		if ( empty( $provided_key ) ) {
			return new WP_Error(
				'dsic_missing_api_key',
				__( 'API key is required. Provide it via the X-DSIC-API-Key header.', 'droix-stripe-id-check' ),
				array( 'status' => 401 )
			);
		}

		$stored_hash = get_option( 'dsic_linnworks_api_key_hash', '' );
		$legacy_key  = get_option( 'dsic_linnworks_api_key', '' );

		if ( empty( $stored_hash ) && empty( $legacy_key ) ) {
			return new WP_Error(
				'dsic_api_key_not_configured',
				__( 'API key not configured in plugin settings.', 'droix-stripe-id-check' ),
				array( 'status' => 500 )
			);
		}

		$is_valid = false;
		if ( ! empty( $stored_hash ) ) {
			$is_valid = hash_equals( $stored_hash, self::hash_api_key( $provided_key ) );
		} elseif ( ! empty( $legacy_key ) ) {
			$is_valid = hash_equals( $legacy_key, $provided_key );
			if ( $is_valid ) {
				update_option( 'dsic_linnworks_api_key_hash', self::hash_api_key( $provided_key ) );
				delete_option( 'dsic_linnworks_api_key' );
			}
		}

		if ( ! $is_valid ) {
			self::record_failed_attempt( $client_ip );
			DSIC_Logger::warning( 'REST API: Invalid API key provided from IP: ' . $client_ip );
			return new WP_Error(
				'dsic_invalid_api_key',
				__( 'Invalid API key.', 'droix-stripe-id-check' ),
				array( 'status' => 401 )
			);
		}

		self::clear_failed_attempts( $client_ip );
		return true;
	}

	/**
	 * Handle lock order request.
	 *
	 * @since 1.5.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_lock( WP_REST_Request $request ) {
		$order_id = $request->get_param( 'order_id' );

		// Verify the order exists in WooCommerce.
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error(
				'dsic_order_not_found',
				sprintf( __( 'WooCommerce order #%d not found.', 'droix-stripe-id-check' ), $order_id ),
				array( 'status' => 404 )
			);
		}

		DSIC_Logger::info( "REST API: Lock request received for order #{$order_id}" );

		$api    = new DSIC_Linnworks_API();
		$result = $api->lock_order_by_wc_id( $order_id, 'api' );

		if ( $result['success'] ) {
			return new WP_REST_Response(
				array(
					'success'          => true,
					'message'          => sprintf( __( 'Order #%d locked successfully.', 'droix-stripe-id-check' ), $order_id ),
					'wc_order_id'      => $order_id,
					'linnworks_id'     => $result['linnworks_id'],
					'timestamp'        => gmdate( 'c' ),
				),
				200
			);
		}

		return new WP_Error(
			'dsic_lock_failed',
			$result['error'] ?? __( 'Failed to lock order.', 'droix-stripe-id-check' ),
			array(
				'status'      => 500,
				'wc_order_id' => $order_id,
				'attempts'    => $result['attempts'] ?? 0,
			)
		);
	}

	/**
	 * Handle unlock order request.
	 *
	 * @since 1.5.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_unlock( WP_REST_Request $request ) {
		$order_id = $request->get_param( 'order_id' );
		$reason   = $request->get_param( 'reason' ) ?? '';

		// Verify the order exists in WooCommerce.
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error(
				'dsic_order_not_found',
				sprintf( __( 'WooCommerce order #%d not found.', 'droix-stripe-id-check' ), $order_id ),
				array( 'status' => 404 )
			);
		}

		DSIC_Logger::info( "REST API: Unlock request received for order #{$order_id}" );

		$api    = new DSIC_Linnworks_API();
		$result = $api->unlock_order_by_wc_id( $order_id, 'api', $reason );

		if ( $result['success'] ) {
			return new WP_REST_Response(
				array(
					'success'          => true,
					'message'          => sprintf( __( 'Order #%d unlocked successfully.', 'droix-stripe-id-check' ), $order_id ),
					'wc_order_id'      => $order_id,
					'linnworks_id'     => $result['linnworks_id'],
					'timestamp'        => gmdate( 'c' ),
				),
				200
			);
		}

		return new WP_Error(
			'dsic_unlock_failed',
			$result['error'] ?? __( 'Failed to unlock order.', 'droix-stripe-id-check' ),
			array(
				'status'      => 500,
				'wc_order_id' => $order_id,
				'attempts'    => $result['attempts'] ?? 0,
			)
		);
	}

	/**
	 * Handle integration status request.
	 *
	 * @since 1.5.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function handle_status( WP_REST_Request $request ) {
		$api  = new DSIC_Linnworks_API();
		$test = $api->test_connection();

		$stats = DSIC_Linnworks_Logger::get_stats( 'today' );

		return new WP_REST_Response(
			array(
				'enabled'       => DSIC_Linnworks_API::is_enabled(),
				'configured'    => $api->is_configured(),
				'connected'     => $test['success'],
				'connection'    => $test,
				'stats_today'   => $stats,
				'timestamp'     => gmdate( 'c' ),
			),
			200
		);
	}

	/**
	 * Handle order status request.
	 *
	 * @since 1.5.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_order_status( WP_REST_Request $request ) {
		$order_id = $request->get_param( 'order_id' );

		// Get log entries for this order.
		$logs = DSIC_Linnworks_Logger::get_logs(
			array(
				'wc_order_id' => $order_id,
				'per_page'    => 10,
				'orderby'     => 'created_at',
				'order'       => 'DESC',
			)
		);

		// Get current verification status from WC order.
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error(
				'dsic_order_not_found',
				sprintf( __( 'WooCommerce order #%d not found.', 'droix-stripe-id-check' ), $order_id ),
				array( 'status' => 404 )
			);
		}

		$verification_status = $order->get_meta( '_dsic_verification_status' );
		$last_action = ! empty( $logs['items'] ) ? $logs['items'][0] : null;

		return new WP_REST_Response(
			array(
				'wc_order_id'         => $order_id,
				'verification_status' => $verification_status ?: 'none',
				'last_linnworks_action' => $last_action ? array(
					'action'    => $last_action['action'],
					'status'    => $last_action['status'],
					'timestamp' => $last_action['created_at'],
					'lw_order_id' => $last_action['lw_order_id'],
				) : null,
				'history_count'       => $logs['total'],
				'timestamp'           => gmdate( 'c' ),
			),
			200
		);
	}

	/**
	 * Get client IP address.
	 *
	 * @since 1.5.0
	 * @return string
	 */
	private static function get_client_ip(): string {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated list (X-Forwarded-For).
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				return $ip;
			}
		}

		return 'unknown';
	}

	/**
	 * Generate a secure API key.
	 *
	 * @since 1.5.0
	 * @return string
	 */
	public static function generate_api_key(): string {
		return 'dsic_' . bin2hex( random_bytes( 24 ) );
	}

	/**
	 * Hash a REST API key for storage.
	 *
	 * @since 1.10.2
	 * @param string $api_key Plain API key.
	 * @return string
	 */
	public static function hash_api_key( string $api_key ): string {
		return hash_hmac( 'sha256', $api_key, wp_salt( 'auth' ) );
	}

	/**
	 * Get the transient key used for REST auth throttling.
	 *
	 * @since 1.10.2
	 * @param string $client_ip Client IP address.
	 * @return string
	 */
	private static function get_rate_limit_key( string $client_ip ): string {
		return 'dsic_rest_auth_' . md5( $client_ip );
	}

	/**
	 * Check whether an IP has too many invalid API key attempts.
	 *
	 * @since 1.10.2
	 * @param string $client_ip Client IP address.
	 * @return bool
	 */
	private static function is_rate_limited( string $client_ip ): bool {
		return (int) get_transient( self::get_rate_limit_key( $client_ip ) ) >= 10;
	}

	/**
	 * Record a failed API key attempt.
	 *
	 * @since 1.10.2
	 * @param string $client_ip Client IP address.
	 * @return void
	 */
	private static function record_failed_attempt( string $client_ip ): void {
		$key      = self::get_rate_limit_key( $client_ip );
		$attempts = (int) get_transient( $key );
		set_transient( $key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Clear failed API key attempts after successful auth.
	 *
	 * @since 1.10.2
	 * @param string $client_ip Client IP address.
	 * @return void
	 */
	private static function clear_failed_attempts( string $client_ip ): void {
		delete_transient( self::get_rate_limit_key( $client_ip ) );
	}

	/**
	 * Get the REST API base URL.
	 *
	 * @since 1.5.0
	 * @return string
	 */
	public static function get_api_url(): string {
		return rest_url( self::NAMESPACE . '/linnworks/' );
	}
}
