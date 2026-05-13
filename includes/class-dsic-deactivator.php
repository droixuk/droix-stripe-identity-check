<?php
/**
 * Plugin deactivator class.
 *
 * Handles all tasks during plugin deactivation.
 *
 * @package    DSIC
 * @subpackage DSIC/includes
 * @since      0.0.1
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Deactivator
 *
 * @since 0.0.1
 */
class DSIC_Deactivator {

	/**
	 * Plugin deactivation handler.
	 *
	 * Cleans up scheduled events and transients.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public static function deactivate(): void {
		self::clear_scheduled_events();
		self::clear_transients();

		// Log deactivation if debug mode was enabled.
		if ( get_option( 'dsic_debug_mode', false ) ) {
			if ( class_exists( 'DSIC_Logger' ) ) {
				DSIC_Logger::info( 'Plugin deactivated.' );
			}
		}
	}

	/**
	 * Clear all scheduled cron events.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	private static function clear_scheduled_events(): void {
		$events = array(
			'dsic_cleanup_logs',
			'dsic_cleanup_expired_sessions',
			'dsic_daily_redaction_check',  // v1.7.0+.
			'dsic_redact_order_data',      // v1.7.0+.
		);

		foreach ( $events as $event ) {
			$timestamp = wp_next_scheduled( $event );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $event );
			}
		}

		// Clear Action Scheduler actions if available.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'dsic_daily_redaction_check' );
			as_unschedule_all_actions( 'dsic_redact_order_data' );
		}
	}

	/**
	 * Clear plugin transients.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	private static function clear_transients(): void {
		global $wpdb;

		// Delete all transients with our prefix.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_dsic_%',
				'_transient_timeout_dsic_%'
			)
		);

		// Clear object cache if available.
		wp_cache_flush();
	}
}
