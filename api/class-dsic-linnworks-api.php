<?php
/**
 * Linnworks API Wrapper Class.
 *
 * Handles communication with Linnworks API for order management.
 *
 * @package    DSIC
 * @subpackage DSIC/api
 * @since      1.5.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Linnworks_API
 *
 * @since 1.5.0
 */
class DSIC_Linnworks_API {

	/**
	 * Linnworks API base URL.
	 *
	 * @since 1.5.0
	 * @var string
	 */
	const AUTH_BASE_URL = 'https://api.linnworks.net/api';

	/**
	 * Maximum retry attempts.
	 *
	 * @since 1.5.0
	 * @var int
	 */
	const MAX_RETRIES = 2;

	/**
	 * Application ID.
	 *
	 * @since 1.5.0
	 * @var string
	 */
	private string $application_id;

	/**
	 * Application Secret.
	 *
	 * @since 1.5.0
	 * @var string
	 */
	private string $application_secret;

	/**
	 * Token.
	 *
	 * @since 1.5.0
	 * @var string
	 */
	private string $token;

	/**
	 * Session token from authentication.
	 *
	 * @since 1.5.0
	 * @var string|null
	 */
	private ?string $session_token = null;

	/**
	 * API locality (region).
	 *
	 * @since 1.5.0
	 * @var string|null
	 */
	private ?string $locality = null;

	/**
	 * Last error message.
	 *
	 * @since 1.5.0
	 * @var string
	 */
	private string $last_error = '';

	/**
	 * Last response data.
	 *
	 * @since 1.5.0
	 * @var mixed
	 */
	private $last_response = null;

	/**
	 * Constructor.
	 *
	 * @since 1.5.0
	 * @param string $application_id     Application ID.
	 * @param string $application_secret Application Secret.
	 * @param string $token              Token.
	 */
	public function __construct( string $application_id = '', string $application_secret = '', string $token = '' ) {
		$this->application_id     = $application_id ?: get_option( 'dsic_linnworks_app_id', '' );
		$this->application_secret = $application_secret ?: get_option( 'dsic_linnworks_app_secret', '' );
		$this->token              = $token ?: get_option( 'dsic_linnworks_token', '' );
	}

	/**
	 * Check if Linnworks integration is configured.
	 *
	 * @since 1.5.0
	 * @return bool
	 */
	public function is_configured(): bool {
		return ! empty( $this->application_id )
			&& ! empty( $this->application_secret )
			&& ! empty( $this->token );
	}

	/**
	 * Check if integration is enabled.
	 *
	 * @since 1.5.0
	 * @return bool
	 */
	public static function is_enabled(): bool {
		// Settings page saves as '1'/'0', not 'yes'/'no'.
		return '1' === get_option( 'dsic_linnworks_enabled', '0' );
	}

	/**
	 * Authenticate with Linnworks API.
	 *
	 * @since 1.5.0
	 * @return bool True on success, false on failure.
	 */
	public function authenticate(): bool {
		if ( ! $this->is_configured() ) {
			$this->last_error = __( 'Linnworks credentials not configured.', 'droix-stripe-id-check' );
			DSIC_Logger::error( 'Linnworks: ' . $this->last_error );
			return false;
		}

		$url  = self::AUTH_BASE_URL . '/Auth/AuthorizeByApplication';
		$body = array(
			'ApplicationId'     => $this->application_id,
			'ApplicationSecret' => $this->application_secret,
			'Token'             => $this->token,
		);

		DSIC_Logger::debug( 'Linnworks: Authenticating...' );

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			DSIC_Logger::error( 'Linnworks Auth Error: ' . $this->last_error );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		$this->last_response = $body;

		if ( 200 !== $code ) {
			$this->last_error = $body['Message'] ?? __( 'Authentication failed.', 'droix-stripe-id-check' );
			DSIC_Logger::error( 'Linnworks Auth Error (' . $code . '): ' . $this->last_error );
			return false;
		}

		if ( empty( $body['Token'] ) || empty( $body['Server'] ) ) {
			$this->last_error = __( 'Invalid authentication response.', 'droix-stripe-id-check' );
			DSIC_Logger::error( 'Linnworks: ' . $this->last_error );
			return false;
		}

		$this->session_token = $body['Token'];

		// Extract locality from server URL (e.g., "eu-ext.linnworks.net" -> "eu").
		if ( preg_match( '/^(https?:\/\/)?([a-z]+)-ext\.linnworks\.net/i', $body['Server'], $matches ) ) {
			$this->locality = strtolower( $matches[2] );
		} else {
			$this->locality = 'eu'; // Default fallback.
		}

		DSIC_Logger::debug( 'Linnworks: Authenticated successfully. Locality: ' . $this->locality );

		return true;
	}

	/**
	 * Get the API base URL for the authenticated session.
	 *
	 * @since 1.5.0
	 * @return string
	 */
	private function get_api_base_url(): string {
		return 'https://' . $this->locality . '-ext.linnworks.net/api';
	}

	/**
	 * Make an authenticated API request.
	 *
	 * @since 1.5.0
	 * @param string $endpoint API endpoint.
	 * @param array  $body     Request body.
	 * @param string $method   HTTP method.
	 * @return array|WP_Error Response data or WP_Error.
	 */
	private function request( string $endpoint, array $body = array(), string $method = 'POST' ) {
		if ( empty( $this->session_token ) ) {
			if ( ! $this->authenticate() ) {
				return new WP_Error( 'dsic_lw_auth_failed', $this->last_error );
			}
		}

		$url = $this->get_api_base_url() . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => $this->session_token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'timeout' => 30,
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		DSIC_Logger::debug( 'Linnworks API Request: ' . $method . ' ' . $endpoint );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->last_error    = $response->get_error_message();
			$this->last_response = null;
			DSIC_Logger::error( 'Linnworks API Error: ' . $this->last_error );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		$this->last_response = $body;

		// Handle 204 No Content (success for lock/unlock).
		if ( 204 === $code ) {
			return array( 'success' => true );
		}

		if ( $code >= 400 ) {
			$this->last_error = $body['Message'] ?? sprintf( __( 'API error (HTTP %d)', 'droix-stripe-id-check' ), $code );
			DSIC_Logger::error( 'Linnworks API Error (' . $code . '): ' . $this->last_error );
			return new WP_Error( 'dsic_lw_api_error', $this->last_error, array( 'status' => $code ) );
		}

		return $body;
	}

	/**
	 * Search for an order by reference number.
	 *
	 * Excludes cloned orders (SubSource: API_CLONE) and prefers exact reference matches.
	 *
	 * @since 1.5.0
	 * @param string $reference Order reference (WC Order ID).
	 * @return array|WP_Error Order data or error.
	 */
	public function search_order( string $reference ) {
		$response = $this->request(
			'/OpenOrders/SearchOrders',
			array( 'SearchTerm' => $reference )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Check if we found orders.
		if ( ! empty( $response['OpenOrders'] ) ) {
			// Filter and find the correct order (exclude API_CLONE, prefer exact match).
			$matching_order = $this->find_matching_order( $response['OpenOrders'], $reference );

			if ( $matching_order ) {
				return array(
					'found'           => true,
					'is_open'         => true,
					'linnworks_id'    => $matching_order['OrderIds'][0] ?? null,
					'reference'       => $reference,
					'status'          => 'open',
					'raw_data'        => $matching_order,
				);
			}
		}

		// Try processed orders if not in open orders.
		$processed_response = $this->request(
			'/ProcessedOrders/SearchProcessedOrders',
			array(
				'SearchTerm'     => $reference,
				'PageNumber'     => 1,
				'ResultsPerPage' => 10,
			)
		);

		if ( ! is_wp_error( $processed_response ) && ! empty( $processed_response['ProcessedOrders']['Data'] ) ) {
			// Filter and find the correct order (exclude API_CLONE, prefer exact match).
			$matching_order = $this->find_matching_order( $processed_response['ProcessedOrders']['Data'], $reference, true );

			if ( $matching_order ) {
				return array(
					'found'           => true,
					'is_open'         => false,
					'linnworks_id'    => $matching_order['pkOrderId'] ?? $matching_order['OrderId'] ?? null,
					'reference'       => $reference,
					'status'          => 'processed',
					'raw_data'        => $matching_order,
				);
			}
		}

		$this->last_error = sprintf( __( 'Order not found in Linnworks: %s', 'droix-stripe-id-check' ), $reference );
		return new WP_Error( 'dsic_lw_order_not_found', $this->last_error );
	}

	/**
	 * Find matching order from search results.
	 *
	 * Excludes cloned orders (SubSource: API_CLONE) and prefers exact reference matches.
	 *
	 * @since 1.5.6
	 * @param array  $orders      Array of orders from search.
	 * @param string $reference   The reference to match.
	 * @param bool   $is_processed Whether these are processed orders (different structure).
	 * @return array|null Matching order or null.
	 */
	private function find_matching_order( array $orders, string $reference, bool $is_processed = false ): ?array {
		$exact_match  = null;
		$partial_match = null;

		foreach ( $orders as $order ) {
			// Get subsource - skip API_CLONE orders.
			$subsource = $order['SubSource'] ?? '';
			if ( 'API_CLONE' === $subsource ) {
				DSIC_Logger::debug( 'Linnworks: Skipping cloned order (SubSource: API_CLONE)' );
				continue;
			}

			// Get reference number to compare.
			$order_ref = $is_processed
				? ( $order['ReferenceNumber'] ?? $order['NumOrderId'] ?? '' )
				: ( $order['NumOrderId'] ?? '' );

			// Check for exact match.
			if ( (string) $order_ref === $reference ) {
				$exact_match = $order;
				DSIC_Logger::debug( 'Linnworks: Found exact match for reference ' . $reference );
				break; // Exact match found, no need to continue.
			}

			// Store first non-clone order as fallback.
			if ( null === $partial_match ) {
				$partial_match = $order;
			}
		}

		// Prefer exact match, fall back to first non-clone order.
		if ( $exact_match ) {
			return $exact_match;
		}

		if ( $partial_match ) {
			DSIC_Logger::debug( 'Linnworks: No exact match, using first non-clone order' );
			return $partial_match;
		}

		DSIC_Logger::debug( 'Linnworks: No valid orders found (all were clones or no match)' );
		return null;
	}

	/**
	 * Lock an order.
	 *
	 * @since 1.5.0
	 * @param string $linnworks_order_id Linnworks order UUID.
	 * @param bool   $add_note           Whether to add a note.
	 * @param int    $wc_order_id        WooCommerce order ID for enhanced note.
	 * @return array|WP_Error Result or error.
	 */
	public function lock_order( string $linnworks_order_id, bool $add_note = true, int $wc_order_id = 0 ) {
		$response = $this->request(
			'/Orders/LockOrder',
			array(
				'orderIds'  => array( $linnworks_order_id ),
				'lockOrder' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Add enhanced note if requested.
		if ( $add_note ) {
			$note_parts = array(
				'[LOCKED] ID verification pending',
				'Date: ' . wp_date( 'Y-m-d H:i:s' ),
			);

			if ( $wc_order_id > 0 ) {
				$note_parts[] = 'WC Order: #' . $wc_order_id;
				$order = wc_get_order( $wc_order_id );
				if ( $order ) {
					$note_parts[] = 'Customer: ' . $order->get_billing_email();

					// Add WC order edit URL.
					$order_url = $order->get_edit_order_url();
					if ( ! empty( $order_url ) ) {
						$note_parts[] = 'WC URL: ' . $order_url;
					}

					// Add Stripe session URL if available.
					$session_id = $order->get_meta( '_dsic_verification_session_id' );
					if ( ! empty( $session_id ) ) {
						$test_mode = get_option( 'dsic_test_mode', true );
						$stripe_url = $test_mode
							? 'https://dashboard.stripe.com/test/identity/verification-sessions/' . $session_id
							: 'https://dashboard.stripe.com/identity/verification-sessions/' . $session_id;
						$note_parts[] = 'Stripe: ' . $stripe_url;
					}
				}
			}

			$current_user = wp_get_current_user();
			if ( $current_user && $current_user->ID > 0 ) {
				$note_parts[] = 'Requested by: ' . $current_user->display_name;
			}

			$note_parts[] = '(Stripe ID Check Plugin)';

			$this->add_order_note( $linnworks_order_id, implode( ' | ', $note_parts ) );
		}

		return array(
			'success' => true,
			'action'  => 'lock',
			'order_id' => $linnworks_order_id,
		);
	}

	/**
	 * Unlock an order.
	 *
	 * @since 1.5.0
	 * @param string $linnworks_order_id Linnworks order UUID.
	 * @param bool   $add_note           Whether to add a note.
	 * @param string $reason             Unlock reason.
	 * @param int    $wc_order_id        WooCommerce order ID for enhanced note.
	 * @return array|WP_Error Result or error.
	 */
	public function unlock_order( string $linnworks_order_id, bool $add_note = true, string $reason = '', int $wc_order_id = 0 ) {
		$response = $this->request(
			'/Orders/LockOrder',
			array(
				'orderIds'  => array( $linnworks_order_id ),
				'lockOrder' => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Add enhanced note if requested.
		if ( $add_note ) {
			$note_parts = array(
				'[UNLOCKED] ' . ( ! empty( $reason ) ? $reason : 'ID verification passed' ),
				'Date: ' . wp_date( 'Y-m-d H:i:s' ),
			);

			if ( $wc_order_id > 0 ) {
				$note_parts[] = 'WC Order: #' . $wc_order_id;
				$order = wc_get_order( $wc_order_id );
				if ( $order ) {
					$note_parts[] = 'Customer: ' . $order->get_billing_email();

					// Add WC order edit URL.
					$order_url = $order->get_edit_order_url();
					if ( ! empty( $order_url ) ) {
						$note_parts[] = 'WC URL: ' . $order_url;
					}

					// Add Stripe session URL if available.
					$session_id = $order->get_meta( '_dsic_verification_session_id' );
					if ( ! empty( $session_id ) ) {
						$test_mode = get_option( 'dsic_test_mode', true );
						$stripe_url = $test_mode
							? 'https://dashboard.stripe.com/test/identity/verification-sessions/' . $session_id
							: 'https://dashboard.stripe.com/identity/verification-sessions/' . $session_id;
						$note_parts[] = 'Stripe: ' . $stripe_url;
					}
				}
			}

			$note_parts[] = '(Stripe ID Check Plugin)';

			$this->add_order_note( $linnworks_order_id, implode( ' | ', $note_parts ) );
		}

		return array(
			'success'  => true,
			'action'   => 'unlock',
			'order_id' => $linnworks_order_id,
		);
	}

	/**
	 * Get existing notes for an order.
	 *
	 * @since 1.5.5
	 * @param string $linnworks_order_id Linnworks order UUID.
	 * @return array Array of notes or empty array on failure.
	 */
	public function get_order_notes( string $linnworks_order_id ): array {
		$response = $this->request(
			'/Orders/GetOrderNotes',
			array(
				'orderId' => $linnworks_order_id,
			)
		);

		if ( is_wp_error( $response ) ) {
			DSIC_Logger::debug( 'Linnworks: Failed to get existing notes: ' . $response->get_error_message() );
			return array();
		}

		// Response should be an array of notes directly.
		if ( is_array( $response ) ) {
			return $response;
		}

		return array();
	}

	/**
	 * Add a note to an order (appends to existing notes).
	 *
	 * @since 1.5.0
	 * @param string $linnworks_order_id Linnworks order UUID.
	 * @param string $note               Note text.
	 * @return array|WP_Error Result or error.
	 */
	public function add_order_note( string $linnworks_order_id, string $note ) {
		// Fetch existing notes first to preserve them.
		$existing_notes = $this->get_order_notes( $linnworks_order_id );

		// Add new note to the array.
		$new_note = array(
			'CreatedBy' => 'Stripe ID Check',
			'NoteDate'  => gmdate( 'c' ),
			'Note'      => $note,
		);

		// Append new note to existing notes.
		$all_notes = $existing_notes;
		$all_notes[] = $new_note;

		DSIC_Logger::debug( 'Linnworks: Adding note to order. Existing notes: ' . count( $existing_notes ) . ', Total: ' . count( $all_notes ) );

		return $this->request(
			'/Orders/SetOrderNotes',
			array(
				'orderId'    => $linnworks_order_id,
				'orderNotes' => $all_notes,
			)
		);
	}

	/**
	 * Lock order by WooCommerce order ID.
	 *
	 * @since 1.5.0
	 * @param int    $wc_order_id    WooCommerce order ID.
	 * @param string $trigger_source How this was triggered (manual, auto, api).
	 * @return array Result with success status and details.
	 */
	public function lock_order_by_wc_id( int $wc_order_id, string $trigger_source = 'auto' ): array {
		$start_time = microtime( true );

		// Start log entry.
		$log_id = DSIC_Linnworks_Logger::log(
			array(
				'wc_order_id'    => $wc_order_id,
				'action'         => 'lock',
				'trigger_source' => $trigger_source,
				'status'         => 'pending',
			)
		);

		$result = $this->process_order_action( $wc_order_id, 'lock' );

		$response_time = round( ( microtime( true ) - $start_time ) * 1000 );

		// Update log.
		if ( $log_id ) {
			DSIC_Linnworks_Logger::update(
				$log_id,
				array(
					'lw_order_id'      => $result['linnworks_id'] ?? null,
					'status'           => $result['success'] ? 'success' : 'failed',
					'error_message'    => $result['error'] ?? null,
					'response_time_ms' => $response_time,
					'response_data'    => $result,
				)
			);
		}

		return $result;
	}

	/**
	 * Unlock order by WooCommerce order ID.
	 *
	 * @since 1.5.0
	 * @param int    $wc_order_id    WooCommerce order ID.
	 * @param string $trigger_source How this was triggered (manual, auto, api).
	 * @param string $reason         Reason for unlock.
	 * @return array Result with success status and details.
	 */
	public function unlock_order_by_wc_id( int $wc_order_id, string $trigger_source = 'auto', string $reason = '' ): array {
		$start_time = microtime( true );

		// Start log entry.
		$log_id = DSIC_Linnworks_Logger::log(
			array(
				'wc_order_id'    => $wc_order_id,
				'action'         => 'unlock',
				'trigger_source' => $trigger_source,
				'status'         => 'pending',
			)
		);

		$result = $this->process_order_action( $wc_order_id, 'unlock', $reason );

		$response_time = round( ( microtime( true ) - $start_time ) * 1000 );

		// Update log.
		if ( $log_id ) {
			DSIC_Linnworks_Logger::update(
				$log_id,
				array(
					'lw_order_id'      => $result['linnworks_id'] ?? null,
					'status'           => $result['success'] ? 'success' : 'failed',
					'error_message'    => $result['error'] ?? null,
					'response_time_ms' => $response_time,
					'response_data'    => $result,
				)
			);
		}

		return $result;
	}

	/**
	 * Process an order action (lock/unlock) with retry logic.
	 *
	 * @since 1.5.0
	 * @param int    $wc_order_id WooCommerce order ID.
	 * @param string $action      Action: 'lock' or 'unlock'.
	 * @param string $reason      Reason (for unlock).
	 * @return array Result array.
	 */
	private function process_order_action( int $wc_order_id, string $action, string $reason = '' ): array {
		$reference = (string) $wc_order_id;
		$attempts  = 0;
		$last_error = '';

		while ( $attempts < self::MAX_RETRIES ) {
			$attempts++;

			// Authenticate fresh for each attempt.
			$this->session_token = null;
			$this->locality      = null;

			if ( ! $this->authenticate() ) {
				$last_error = $this->last_error;
				DSIC_Logger::warning( "Linnworks: Auth attempt {$attempts} failed: {$last_error}" );
				continue;
			}

			// Search for order.
			$order_result = $this->search_order( $reference );

			if ( is_wp_error( $order_result ) ) {
				$last_error = $order_result->get_error_message();
				DSIC_Logger::warning( "Linnworks: Search attempt {$attempts} failed: {$last_error}" );
				continue;
			}

			if ( empty( $order_result['linnworks_id'] ) ) {
				$last_error = __( 'Order found but no Linnworks ID returned.', 'droix-stripe-id-check' );
				DSIC_Logger::warning( "Linnworks: {$last_error}" );
				continue;
			}

			$linnworks_id = $order_result['linnworks_id'];

			// Perform the action (pass WC order ID for enhanced notes).
			if ( 'lock' === $action ) {
				$action_result = $this->lock_order( $linnworks_id, true, $wc_order_id );
			} else {
				$action_result = $this->unlock_order( $linnworks_id, true, $reason, $wc_order_id );
			}

			if ( is_wp_error( $action_result ) ) {
				$last_error = $action_result->get_error_message();
				DSIC_Logger::warning( "Linnworks: {$action} attempt {$attempts} failed: {$last_error}" );
				continue;
			}

			// Success!
			DSIC_Logger::info( "Linnworks: Order {$wc_order_id} {$action}ed successfully (LW ID: {$linnworks_id})" );

			return array(
				'success'      => true,
				'wc_order_id'  => $wc_order_id,
				'linnworks_id' => $linnworks_id,
				'action'       => $action,
				'attempts'     => $attempts,
			);
		}

		// All retries failed.
		DSIC_Logger::error( "Linnworks: Failed to {$action} order {$wc_order_id} after {$attempts} attempts. Last error: {$last_error}" );

		return array(
			'success'      => false,
			'wc_order_id'  => $wc_order_id,
			'linnworks_id' => null,
			'action'       => $action,
			'error'        => $last_error,
			'attempts'     => $attempts,
		);
	}

	/**
	 * Test the connection to Linnworks.
	 *
	 * @since 1.5.0
	 * @return array Test result.
	 */
	public function test_connection(): array {
		$start_time = microtime( true );

		if ( ! $this->is_configured() ) {
			return array(
				'success' => false,
				'message' => __( 'Linnworks credentials not configured.', 'droix-stripe-id-check' ),
			);
		}

		$auth_result = $this->authenticate();
		$response_time = round( ( microtime( true ) - $start_time ) * 1000 );

		if ( ! $auth_result ) {
			return array(
				'success'       => false,
				'message'       => $this->last_error,
				'response_time' => $response_time,
			);
		}

		return array(
			'success'       => true,
			'message'       => sprintf(
				/* translators: %s: locality/region */
				__( 'Connected successfully to Linnworks (%s region).', 'droix-stripe-id-check' ),
				strtoupper( $this->locality )
			),
			'locality'      => $this->locality,
			'response_time' => $response_time,
		);
	}

	/**
	 * Get last error message.
	 *
	 * @since 1.5.0
	 * @return string
	 */
	public function get_last_error(): string {
		return $this->last_error;
	}

	/**
	 * Get last response data.
	 *
	 * @since 1.5.0
	 * @return mixed
	 */
	public function get_last_response() {
		return $this->last_response;
	}
}
