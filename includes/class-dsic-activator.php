<?php
/**
 * Plugin activator class.
 *
 * Handles all tasks during plugin activation.
 *
 * @package    DSIC
 * @subpackage DSIC/includes
 * @since      0.0.1
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Activator
 *
 * @since 0.0.1
 */
class DSIC_Activator {

	/**
	 * Plugin activation handler.
	 *
	 * Sets default options and creates necessary directories.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public static function activate(): void {
		self::set_default_options();
		self::create_log_directory();
		self::create_database_tables();
		self::schedule_recurring_jobs();
		self::set_activation_timestamp();

		// Clear any cached data.
		wp_cache_flush();
	}

	/**
	 * Set default plugin options.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	private static function set_default_options(): void {
		// Use '1' and '0' strings for checkbox values for WordPress compatibility.
		$defaults = array(
			'dsic_enabled'                  => '1', // Plugin enabled by default.
			'dsic_test_mode'                => '1', // Test mode on by default for safety.
			'dsic_debug_mode'               => '0',
			'dsic_stripe_publishable_key'   => '',
			'dsic_stripe_secret_key'        => '',
			'dsic_webhook_secret'           => '',
			'dsic_crm_email'                => get_option( 'admin_email' ),
			'dsic_delete_data_on_uninstall' => '0',
			// Verification options.
			'dsic_require_selfie'           => '1',
			'dsic_require_id_number'        => '0',
			'dsic_require_live_capture'     => '1',
			// Auto-redaction options (v1.7.0+).
			'dsic_auto_redaction_enabled'   => '0', // Disabled by default (opt-in).
			'dsic_redaction_days'           => '30',
			'dsic_redaction_batch_size'     => '20',
			'dsic_redaction_schedule_time'  => '01:00',
			'dsic_redaction_notify_customer' => '1',
		);

		foreach ( $defaults as $option => $value ) {
			if ( false === get_option( $option ) ) {
				add_option( $option, $value, '', false );
			}
		}

		// Set default allowed document types.
		if ( false === get_option( 'dsic_allowed_document_types' ) ) {
			add_option( 'dsic_allowed_document_types', array( 'driving_license', 'id_card', 'passport' ), '', false );
		}

		// Set email template defaults.
		self::set_email_template_defaults();
	}

	/**
	 * Set default email template options.
	 *
	 * Only sets defaults if option doesn't exist (preserves user customizations).
	 * Uses DSIC_Settings class methods to get templates for consistency.
	 *
	 * @since 0.3.7
	 * @return void
	 */
	private static function set_email_template_defaults(): void {
		// Load settings class if not already loaded.
		if ( ! class_exists( 'DSIC_Settings' ) ) {
			require_once DSIC_PLUGIN_DIR . 'admin/class-dsic-settings.php';
		}

		$settings    = new DSIC_Settings();
		$email_types = array( 'verification_request', 'verification_passed', 'verification_failed', 'data_redaction' );

		foreach ( $email_types as $type ) {
			// Only set if option doesn't exist (false means not set).
			// This preserves user customizations on plugin updates.
			// Use '1' string for consistency with form handling.
			if ( false === get_option( 'dsic_email_' . $type . '_enabled' ) ) {
				add_option( 'dsic_email_' . $type . '_enabled', '1', '', false );
			}
			if ( false === get_option( 'dsic_email_' . $type . '_subject' ) ) {
				add_option( 'dsic_email_' . $type . '_subject', $settings->get_default_email_subject( $type ), '', false );
			}
			if ( false === get_option( 'dsic_email_' . $type . '_heading' ) ) {
				add_option( 'dsic_email_' . $type . '_heading', $settings->get_default_email_heading( $type ), '', false );
			}
			if ( false === get_option( 'dsic_email_' . $type . '_body' ) ) {
				add_option( 'dsic_email_' . $type . '_body', $settings->get_default_email_body( $type ), '', false );
			}
		}
	}

	/**
	 * Create log directory if it doesn't exist.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	private static function create_log_directory(): void {
		$log_dir = WP_CONTENT_DIR . '/dsic-logs';

		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );

			// Create .htaccess to protect logs.
			$htaccess_content = "Order deny,allow\nDeny from all";
			file_put_contents( $log_dir . '/.htaccess', $htaccess_content );

			// Create index.php for additional protection.
			file_put_contents( $log_dir . '/index.php', '<?php // Silence is golden' );
		}
	}

	/**
	 * Create database tables.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	private static function create_database_tables(): void {
		// Create Linnworks log table.
		if ( ! class_exists( 'DSIC_Linnworks_Logger' ) ) {
			require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-linnworks-logger.php';
		}

		DSIC_Linnworks_Logger::create_table();

		// Create compliance log table.
		if ( ! class_exists( 'DSIC_Compliance_Report' ) ) {
			require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-compliance-report.php';
		}

		DSIC_Compliance_Report::create_table();
	}

	/**
	 * Schedule recurring jobs.
	 *
	 * @since 1.7.0
	 * @return void
	 */
	private static function schedule_recurring_jobs(): void {
		// Schedule daily redaction check.
		if ( ! class_exists( 'DSIC_Auto_Redaction' ) ) {
			require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-auto-redaction.php';
		}

		DSIC_Auto_Redaction::schedule_daily_check();
	}

	/**
	 * Record activation timestamp.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	private static function set_activation_timestamp(): void {
		if ( false === get_option( 'dsic_activated_at' ) ) {
			add_option( 'dsic_activated_at', time(), '', false );
		}

		update_option( 'dsic_version', DSIC_VERSION, false );
	}
}
