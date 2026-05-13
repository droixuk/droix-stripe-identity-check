<?php
/**
 * Slack notification class.
 *
 * Sends Block Kit messages to a configured Slack webhook for key
 * ID verification events: triggered, passed, failed, and EFW received.
 *
 * @package    DSIC
 * @subpackage DSIC/includes
 * @since      1.9.3
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Slack
 *
 * @since 1.9.3
 */
class DSIC_Slack {

	/**
	 * Initialize Slack notification hooks.
	 *
	 * @since 1.9.3
	 * @return void
	 */
	public static function init(): void {
		add_action( 'dsic_verification_requested', array( __CLASS__, 'on_verification_requested' ), 10, 2 );
		add_action( 'dsic_verification_passed', array( __CLASS__, 'on_verification_passed' ), 10, 1 );
		add_action( 'dsic_verification_failed', array( __CLASS__, 'on_verification_failed' ), 10, 2 );
	}

	/**
	 * Check whether Slack notifications are enabled (webhook URL configured).
	 *
	 * @since 1.9.3
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return ! empty( get_option( 'dsic_slack_webhook_url', '' ) );
	}

	/**
	 * Check whether a specific event notification is enabled.
	 *
	 * @since 1.9.3
	 * @param string $event Event slug: triggered|passed|failed|efw.
	 * @return bool
	 */
	public static function is_event_enabled( string $event ): bool {
			$defaults = array(
				'triggered' => '0',
				'passed'    => '0',
				'failed'    => '0',
				'efw'       => '0',
			);
		$default = $defaults[ $event ] ?? '0';
		return (bool) get_option( 'dsic_slack_notify_' . $event, $default );
	}

	/**
	 * Send a payload to the configured Slack webhook.
	 *
	 * @since 1.9.3
	 * @param array $payload Slack Block Kit payload.
	 * @return void
	 */
	public static function send( array $payload ): void {
		$webhook_url = get_option( 'dsic_slack_webhook_url', '' );
		if ( empty( $webhook_url ) ) {
			return;
		}

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
			DSIC_Logger::error( 'Slack notification failed: ' . $response->get_error_message() );
		} else {
			$code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== (int) $code ) {
				DSIC_Logger::warning( 'Slack notification returned HTTP ' . $code );
			}
		}
	}

	/**
	 * Hook: dsic_verification_requested.
	 *
	 * @since 1.9.3
	 * @param int    $order_id         Order ID.
	 * @param string $verification_url Verification URL (unused here).
	 * @return void
	 */
	public static function on_verification_requested( int $order_id, string $verification_url ): void {
		if ( ! self::is_enabled() || ! self::is_event_enabled( 'triggered' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$session_id = $order->get_meta( '_dsic_verification_session_id' );
		$reason     = self::infer_trigger_reason( $order );

		self::notify_triggered( $order, $reason, (string) $session_id );
	}

	/**
	 * Hook: dsic_verification_passed.
	 *
	 * @since 1.9.3
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function on_verification_passed( int $order_id ): void {
		if ( ! self::is_enabled() || ! self::is_event_enabled( 'passed' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$session_id = $order->get_meta( '_dsic_verification_session_id' );
		self::notify_passed( $order, (string) $session_id );
	}

	/**
	 * Hook: dsic_verification_failed.
	 *
	 * @since 1.9.3
	 * @param int    $order_id     Order ID.
	 * @param string $error_reason Failure reason.
	 * @return void
	 */
	public static function on_verification_failed( int $order_id, string $error_reason ): void {
		if ( ! self::is_enabled() || ! self::is_event_enabled( 'failed' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$session_id = $order->get_meta( '_dsic_verification_session_id' );
		self::notify_failed( $order, $error_reason, (string) $session_id );
	}

	/**
	 * Send a "verification triggered" Slack notification.
	 *
	 * @since 1.9.3
	 * @param WC_Order $order      Order object.
	 * @param string   $reason     Human-readable trigger reason.
	 * @param string   $session_id Stripe verification session ID.
	 * @return void
	 */
	public static function notify_triggered( WC_Order $order, string $reason, string $session_id ): void {
		$blocks = self::build_order_blocks(
			':mag: ID Check Triggered',
			$order,
			array(
				array(
					'type' => 'mrkdwn',
					'text' => '*Trigger Reason*',
				),
				array(
					'type' => 'plain_text',
					'text' => $reason ?: 'Unknown',
				),
			),
			$session_id
		);

		self::send( array( 'blocks' => $blocks ) );
	}

	/**
	 * Send a "verification passed" Slack notification.
	 *
	 * @since 1.9.3
	 * @param WC_Order $order      Order object.
	 * @param string   $session_id Stripe verification session ID.
	 * @return void
	 */
	public static function notify_passed( WC_Order $order, string $session_id ): void {
		$blocks = self::build_order_blocks(
			':white_check_mark: ID Check Passed',
			$order,
			array(),
			$session_id
		);

		self::send( array( 'blocks' => $blocks ) );
	}

	/**
	 * Send a "verification failed" Slack notification.
	 *
	 * @since 1.9.3
	 * @param WC_Order $order      Order object.
	 * @param string   $reason     Failure reason.
	 * @param string   $session_id Stripe verification session ID.
	 * @return void
	 */
	public static function notify_failed( WC_Order $order, string $reason, string $session_id ): void {
		$blocks = self::build_order_blocks(
			':x: ID Check Failed',
			$order,
			array(
				array(
					'type' => 'mrkdwn',
					'text' => '*Failure Reason*',
				),
				array(
					'type' => 'plain_text',
					'text' => $reason ?: 'Unknown',
				),
			),
			$session_id
		);

		self::send( array( 'blocks' => $blocks ) );
	}

	/**
	 * Send an "early fraud warning" Slack notification.
	 *
	 * @since 1.9.3
	 * @param WC_Order $order             Order object.
	 * @param string   $efw_type          Fraud type string from Stripe.
	 * @param bool     $already_processed Whether order was already processing/completed.
	 * @return void
	 */
	public static function notify_efw( WC_Order $order, string $efw_type, bool $already_processed ): void {
		if ( ! self::is_enabled() || ! self::is_event_enabled( 'efw' ) ) {
			return;
		}

		$charge_id = $order->get_transaction_id();

		$extra_fields = array(
			array(
				'type' => 'mrkdwn',
				'text' => '*Fraud Type*',
			),
			array(
				'type' => 'plain_text',
				'text' => $efw_type ?: 'unknown',
			),
		);

		// build_order_blocks ends with [..., divider, actions].
		// If we need to insert a context block, pop divider+actions, append context, then re-add them.
		$blocks  = self::build_order_blocks( ':warning: Early Fraud Warning', $order, $extra_fields, '', $charge_id );
		$actions = array_pop( $blocks );
		$divider = array_pop( $blocks );

		$blocks[] = $divider;

		if ( $already_processed ) {
			$blocks[] = array(
				'type'     => 'context',
				'elements' => array(
					array(
						'type' => 'mrkdwn',
						'text' => ':rotating_light: *Order was already in ' . ucfirst( $order->get_status() ) . ' status when EFW arrived — may already be dispatched.*',
					),
				),
			);
		}

		$blocks[] = $actions;

		self::send( array( 'blocks' => $blocks ) );
	}

	/**
	 * Build standard Block Kit blocks for an order notification.
	 *
	 * @since 1.9.3
	 * @param string   $title        Header title text.
	 * @param WC_Order $order        Order object.
	 * @param array    $extra_fields Additional 2-element label/value pairs for the fields section.
	 * @param string   $session_id   Stripe verification session ID (for Stripe URL).
	 * @param string   $charge_id    Stripe charge ID (fallback for Stripe URL when no session).
	 * @return array Block Kit blocks array.
	 */
	private static function build_order_blocks(
		string $title,
		WC_Order $order,
		array $extra_fields,
		string $session_id = '',
		string $charge_id = ''
	): array {
		$order_id      = $order->get_id();
		$customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$order_total   = wp_strip_all_tags( $order->get_formatted_order_total() );
		$order_url     = admin_url( 'post.php?post=' . $order_id . '&action=edit' );

		// Build Stripe dashboard URL.
		$test_mode  = get_option( 'dsic_test_mode', true );
		$stripe_url = '';
		if ( ! empty( $session_id ) ) {
			$stripe_base = $test_mode
				? 'https://dashboard.stripe.com/test/identity/verification-sessions/'
				: 'https://dashboard.stripe.com/identity/verification-sessions/';
			$stripe_url  = $stripe_base . $session_id;
		} elseif ( ! empty( $charge_id ) ) {
			$stripe_base = $test_mode
				? 'https://dashboard.stripe.com/test/charges/'
				: 'https://dashboard.stripe.com/charges/';
			$stripe_url  = $stripe_base . $charge_id;
		}

		// Standard fields.
		$fields = array(
			array(
				'type' => 'mrkdwn',
				'text' => '*Order*',
			),
			array(
				'type' => 'plain_text',
				'text' => '#' . $order->get_order_number(),
			),
			array(
				'type' => 'mrkdwn',
				'text' => '*Customer*',
			),
			array(
				'type' => 'plain_text',
				'text' => $customer_name ?: 'N/A',
			),
			array(
				'type' => 'mrkdwn',
				'text' => '*Order Total*',
			),
			array(
				'type' => 'plain_text',
				'text' => $order_total,
			),
		);

		// Merge extra fields.
		foreach ( $extra_fields as $field ) {
			$fields[] = $field;
		}

		$blocks = array(
			// Header.
			array(
				'type' => 'header',
				'text' => array(
					'type'  => 'plain_text',
					'text'  => $title,
					'emoji' => true,
				),
			),
			// Fields section.
			array(
				'type'   => 'section',
				'fields' => $fields,
			),
			// Divider.
			array(
				'type' => 'divider',
			),
		);

		// Actions block with buttons.
		$action_elements = array(
			array(
				'type'  => 'button',
				'text'  => array(
					'type'  => 'plain_text',
					'text'  => 'View Order',
					'emoji' => true,
				),
				'url'   => $order_url,
				'style' => 'primary',
			),
		);

		if ( ! empty( $stripe_url ) ) {
			$action_elements[] = array(
				'type' => 'button',
				'text' => array(
					'type'  => 'plain_text',
					'text'  => 'View in Stripe',
					'emoji' => true,
				),
				'url' => $stripe_url,
			);
		}

		$blocks[] = array(
			'type'     => 'actions',
			'elements' => $action_elements,
		);

		return $blocks;
	}

	/**
	 * Infer the human-readable trigger reason from order meta.
	 *
	 * @since 1.9.3
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	private static function infer_trigger_reason( WC_Order $order ): string {
		if ( $order->get_meta( '_dsic_efw_triggered' ) ) {
			return 'Early Fraud Warning';
		}

		if ( $order->get_meta( '_dsic_radar_verification_triggered' ) ) {
			$risk_level = $order->get_meta( '_dsic_radar_risk_level' );
			$risk_score = $order->get_meta( '_dsic_radar_risk_score' );
			if ( $risk_level ) {
				return 'Radar: ' . $risk_level . ' risk'
					. ( $risk_score ? ' (score: ' . $risk_score . ')' : '' );
			}
			return 'Radar fraud score';
		}

		if ( $order->get_meta( '_dsic_auto_verification_triggered' ) ) {
			return 'Address mismatch';
		}

		return 'Manual request';
	}
}
