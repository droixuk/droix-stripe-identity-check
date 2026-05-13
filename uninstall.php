<?php
/**
 * Plugin uninstall handler.
 *
 * Fired when the plugin is uninstalled.
 *
 * @package    DSIC
 * @since      0.0.1
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if data should be deleted.
$delete_data = get_option( 'dsic_delete_data_on_uninstall', false );

if ( ! $delete_data ) {
	return;
}

global $wpdb;

// Delete plugin options.
$options = array(
	'dsic_enabled',
	'dsic_test_mode',
	'dsic_debug_mode',
	'dsic_stripe_publishable_key',
	'dsic_stripe_secret_key',
	'dsic_webhook_secret',
	'dsic_crm_email',
	'dsic_delete_data_on_uninstall',
	'dsic_activated_at',
	'dsic_version',
	// Auto-redaction options (v1.7.0+).
	'dsic_auto_redaction_enabled',
	'dsic_redaction_days',
	'dsic_redaction_batch_size',
	'dsic_redaction_schedule_time',
	'dsic_redaction_notify_customer',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Delete options that might have been added dynamically.
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'dsic_%'"
);

// Delete transients.
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_dsic_%'"
);
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_dsic_%'"
);

// Delete order meta.
$meta_keys = array(
	'_dsic_verification_token',
	'_dsic_verification_session_id',
	'_dsic_verification_status',
	'_dsic_verification_requested',
	'_dsic_link_clicked',
	'_dsic_verification_completed',
	'_dsic_verification_error_code',
	'_dsic_verification_error_msg',
	'_dsic_verification_attempts',
	// Data redaction meta (v1.7.0+).
	'_dsic_data_redaction_status',
	'_dsic_data_redaction_requested',
	'_dsic_data_redaction_completed',
	'_dsic_redaction_email_sent',
	'_dsic_redaction_error_count',
	'_dsic_redaction_error_message',
);

// For HPOS (High-Performance Order Storage).
if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) &&
	 Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
	// Delete from orders meta table.
	$orders_meta_table = $wpdb->prefix . 'wc_orders_meta';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$orders_meta_table}'" ) === $orders_meta_table ) {
		foreach ( $meta_keys as $key ) {
			$wpdb->delete(
				$orders_meta_table,
				array( 'meta_key' => $key ),
				array( '%s' )
			);
		}
	}
} else {
	// Legacy: Delete from post meta.
	foreach ( $meta_keys as $key ) {
		$wpdb->delete(
			$wpdb->postmeta,
			array( 'meta_key' => $key ),
			array( '%s' )
		);
	}
}

// Drop custom database tables.
$linnworks_table   = $wpdb->prefix . 'dsic_linnworks_log';
$compliance_table  = $wpdb->prefix . 'dsic_compliance_log';

$wpdb->query( "DROP TABLE IF EXISTS {$linnworks_table}" ); // phpcs:ignore
$wpdb->query( "DROP TABLE IF EXISTS {$compliance_table}" ); // phpcs:ignore

delete_option( 'dsic_linnworks_log_table_version' );
delete_option( 'dsic_compliance_log_table_version' );

// Delete log files.
$log_dirs = array(
	WP_CONTENT_DIR . '/dsic-logs',
	WP_CONTENT_DIR . '/uploads/dsic-logs',
);

foreach ( $log_dirs as $log_dir ) {
	if ( is_dir( $log_dir ) ) {
		$files = glob( $log_dir . '/*' );
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file );
				}
			}
		}
		rmdir( $log_dir );
	}
}

// Clear any scheduled cron events.
$cron_events = array(
	'dsic_cleanup_logs',
	'dsic_cleanup_expired_sessions',
	'dsic_daily_redaction_check',  // v1.7.0+.
	'dsic_redact_order_data',      // v1.7.0+.
);

foreach ( $cron_events as $event ) {
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

// Clear object cache.
wp_cache_flush();
