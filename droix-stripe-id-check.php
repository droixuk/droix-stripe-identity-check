<?php
/**
 * Plugin Name:       Stripe ID Check
 * Plugin URI:        https://DROIX.store
 * Description:       WooCommerce integration for Stripe Identity verification, fraud-review workflows, and optional Linnworks order locking.
 * Version:           1.10.1
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            DROIX / Entertainment Gadgets LTD
 * Author URI:        https://DROIX.store
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       droix-stripe-id-check
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   9.4
 *
 * @package DSIC
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Plugin version.
 */
define( 'DSIC_VERSION', '1.10.1' );

/**
 * Plugin directory path.
 */
define( 'DSIC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'DSIC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'DSIC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Minimum PHP version.
 */
define( 'DSIC_MIN_PHP_VERSION', '8.0' );

/**
 * Minimum WordPress version.
 */
define( 'DSIC_MIN_WP_VERSION', '6.4' );

/**
 * Minimum WooCommerce version.
 */
define( 'DSIC_MIN_WC_VERSION', '8.0' );

/**
 * Stripe API version to use.
 */
define( 'DSIC_STRIPE_API_VERSION', '2024-06-20' );

/**
 * Declare HPOS (High-Performance Order Storage) compatibility.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

/**
 * Check if WooCommerce is active.
 *
 * @return bool
 */
function dsic_is_woocommerce_active(): bool {
	return class_exists( 'WooCommerce' );
}

/**
 * Check minimum requirements.
 *
 * @return bool|WP_Error True if requirements met, WP_Error otherwise.
 */
function dsic_check_requirements() {
	$errors = array();

	// Check PHP version.
	if ( version_compare( PHP_VERSION, DSIC_MIN_PHP_VERSION, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required PHP version, 2: Current PHP version */
			__( 'Stripe ID Check requires PHP %1$s or higher. You are running PHP %2$s.', 'droix-stripe-id-check' ),
			DSIC_MIN_PHP_VERSION,
			PHP_VERSION
		);
	}

	// Check WordPress version.
	global $wp_version;
	if ( version_compare( $wp_version, DSIC_MIN_WP_VERSION, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required WordPress version, 2: Current WordPress version */
			__( 'Stripe ID Check requires WordPress %1$s or higher. You are running WordPress %2$s.', 'droix-stripe-id-check' ),
			DSIC_MIN_WP_VERSION,
			$wp_version
		);
	}

	// Check WooCommerce.
	if ( ! dsic_is_woocommerce_active() ) {
		$errors[] = __( 'Stripe ID Check requires WooCommerce to be installed and activated.', 'droix-stripe-id-check' );
	} elseif ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, DSIC_MIN_WC_VERSION, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required WooCommerce version, 2: Current WooCommerce version */
			__( 'Stripe ID Check requires WooCommerce %1$s or higher. You are running WooCommerce %2$s.', 'droix-stripe-id-check' ),
			DSIC_MIN_WC_VERSION,
			WC_VERSION
		);
	}

	if ( ! empty( $errors ) ) {
		return new WP_Error( 'dsic_requirements_not_met', implode( '<br>', $errors ) );
	}

	return true;
}

/**
 * Display admin notice for requirements not met.
 *
 * @return void
 */
function dsic_requirements_notice(): void {
	$requirements = dsic_check_requirements();

	if ( is_wp_error( $requirements ) ) {
		?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'Stripe ID Check', 'droix-stripe-id-check' ); ?></strong></p>
			<p><?php echo wp_kses_post( $requirements->get_error_message() ); ?></p>
		</div>
		<?php
	}
}

/**
 * Initialize the plugin.
 *
 * @return void
 */
function dsic_init(): void {
	// Check requirements.
	$requirements = dsic_check_requirements();
	if ( is_wp_error( $requirements ) ) {
		add_action( 'admin_notices', 'dsic_requirements_notice' );
		return;
	}

	// Load plugin text domain.
	load_plugin_textdomain(
		'droix-stripe-id-check',
		false,
		dirname( DSIC_PLUGIN_BASENAME ) . '/languages'
	);

	// Include required files.
	require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-loader.php';
	require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-activator.php';
	require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-deactivator.php';
	require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-i18n.php';
	require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-logger.php';
	require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-shortcodes.php';
	require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-stats.php';
	require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-wpml.php';
	require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-email-helper.php';
	require_once DSIC_PLUGIN_DIR . 'api/class-dsic-stripe-api.php';
	require_once DSIC_PLUGIN_DIR . 'api/class-dsic-webhook-handler.php';
	require_once DSIC_PLUGIN_DIR . 'api/class-dsic-linnworks-api.php';
	require_once DSIC_PLUGIN_DIR . 'api/class-dsic-rest-api.php';
	require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-linnworks-logger.php';
	require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-linnworks-integration.php';
	require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-auto-redaction.php';
	require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-compliance-report.php';
	require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-radar-check.php';
	require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-amount-threshold-check.php';
	require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-slack.php';

	// Note: Email class files are loaded in dsic_register_emails() when
	// woocommerce_email_classes filter runs. WC_Email is only available then.

	// Admin-only files.
	if ( is_admin() ) {
		require_once DSIC_PLUGIN_DIR . 'admin/class-dsic-admin.php';
		require_once DSIC_PLUGIN_DIR . 'admin/class-dsic-settings.php';
		require_once DSIC_PLUGIN_DIR . 'admin/class-dsic-menu.php';
		require_once DSIC_PLUGIN_DIR . 'admin/class-dsic-order-handler.php';
		require_once DSIC_PLUGIN_DIR . 'admin/class-dsic-dashboard-widget.php';
		require_once DSIC_PLUGIN_DIR . 'admin/class-dsic-bulk-actions.php';
	}

	// Check for version upgrades.
	dsic_maybe_upgrade();

	// Initialize the loader.
	$loader = new DSIC_Loader();

	// Initialize internationalization.
	$i18n = new DSIC_i18n();
	$loader->add_action( 'init', $i18n, 'load_plugin_textdomain' );

	// Initialize shortcodes.
	DSIC_Shortcodes::init();

	// Initialize WPML/Polylang integration.
	DSIC_WPML::init();

	// Initialize stats cache clearing.
	DSIC_Stats::init_cache_clearing();

	// Initialize webhook handler (frontend and admin).
	new DSIC_Webhook_Handler();

	// Initialize Linnworks REST API endpoints.
	DSIC_REST_API::init();

	// Initialize Linnworks integration (auto lock/unlock).
	DSIC_Linnworks_Integration::init();

	// Initialize auto-redaction (v1.7.0+).
	DSIC_Auto_Redaction::init();

	// Initialize Radar fraud check (v1.8.0+).
	DSIC_Radar_Check::init();

	// Initialize amount threshold check (v1.10.0+).
	DSIC_Amount_Threshold_Check::init();

	// Initialize Slack notifications (v1.9.3+).
	DSIC_Slack::init();

	// Initialize frontend (customer-facing verification display).
	if ( ! is_admin() ) {
		require_once DSIC_PLUGIN_DIR . 'public/class-dsic-frontend.php';
		new DSIC_Frontend();
	}

	// Initialize checkout integration (auto-verification for different addresses).
	// Load settings and order handler if not already loaded (needed for checkout processing).
	if ( ! class_exists( 'DSIC_Settings' ) ) {
		require_once DSIC_PLUGIN_DIR . 'admin/class-dsic-settings.php';
	}
	if ( ! class_exists( 'DSIC_Order_Handler' ) ) {
		require_once DSIC_PLUGIN_DIR . 'admin/class-dsic-order-handler.php';
	}
	require_once DSIC_PLUGIN_DIR . 'public/class-dsic-checkout.php';
	new DSIC_Checkout();

	// Initialize admin.
	if ( is_admin() ) {
		$admin = new DSIC_Admin();
		$loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
		$loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );

		$menu = new DSIC_Menu();
		$loader->add_action( 'admin_menu', $menu, 'register_menu' );

		$settings = new DSIC_Settings();
		$loader->add_action( 'admin_init', $settings, 'register_settings' );
		$loader->add_action( 'wp_ajax_dsic_test_connection', $settings, 'ajax_test_connection' );
		$loader->add_action( 'wp_ajax_dsic_send_test_email', $settings, 'ajax_send_test_email' );
		$loader->add_action( 'wp_ajax_dsic_reset_email_template', $settings, 'ajax_reset_email_template' );
		$loader->add_action( 'wp_ajax_dsic_reset_all_email_templates', $settings, 'ajax_reset_all_email_templates' );
		$loader->add_action( 'wp_ajax_dsic_test_radar_connection', $settings, 'ajax_test_radar_connection' );
		$loader->add_action( 'wp_ajax_dsic_test_slack_connection', $settings, 'ajax_test_slack_connection' );

		// Initialize order handler.
		new DSIC_Order_Handler();

		// Initialize dashboard widget.
		new DSIC_Dashboard_Widget();

		// Initialize bulk actions.
		new DSIC_Bulk_Actions();
	}

	// Register email classes with WooCommerce.
	add_filter( 'woocommerce_email_classes', 'dsic_register_emails' );

	// Run the loader.
	$loader->run();
}
add_action( 'plugins_loaded', 'dsic_init', 20 );

/**
 * Check and run plugin upgrades if needed.
 *
 * Compares stored version with current version and runs
 * necessary upgrade routines for existing installations.
 *
 * @since 0.3.7
 * @return void
 */
function dsic_maybe_upgrade(): void {
	$stored_version = get_option( 'dsic_version', '0.0.0' );

	// Skip if already at current version.
	if ( version_compare( $stored_version, DSIC_VERSION, '>=' ) ) {
		return;
	}

	// Run email template defaults for existing installations upgrading to 0.3.7+.
	if ( version_compare( $stored_version, '0.3.7', '<' ) ) {
		// Use the activator's method to set email template defaults.
		// This only sets options that don't exist, preserving user customizations.
		if ( class_exists( 'DSIC_Activator' ) ) {
			// Trigger activation to set any missing defaults.
			DSIC_Activator::activate();
		}
	}

	// Update stored version.
	update_option( 'dsic_version', DSIC_VERSION, false );
}

/**
 * Register custom emails with WooCommerce.
 *
 * Email class files are loaded here (not earlier) because WC_Email
 * is only available after WC()->mailer() initializes.
 *
 * @since 0.0.1
 * @param array $email_classes WooCommerce email classes.
 * @return array Modified email classes.
 */
function dsic_register_emails( array $email_classes ): array {
	// Load email class files now (WC_Email is available at this point).
	require_once DSIC_PLUGIN_DIR . 'emails/class-dsic-email-verification-request.php';
	require_once DSIC_PLUGIN_DIR . 'emails/class-dsic-email-verification-passed.php';
	require_once DSIC_PLUGIN_DIR . 'emails/class-dsic-email-verification-failed.php';
	require_once DSIC_PLUGIN_DIR . 'emails/class-dsic-email-crm-notification.php';
	require_once DSIC_PLUGIN_DIR . 'emails/class-dsic-email-data-redaction.php';

	$email_classes['DSIC_Email_Verification_Request'] = new DSIC_Email_Verification_Request();
	$email_classes['DSIC_Email_Verification_Passed']  = new DSIC_Email_Verification_Passed();
	$email_classes['DSIC_Email_Verification_Failed']  = new DSIC_Email_Verification_Failed();
	$email_classes['DSIC_Email_CRM_Notification']     = new DSIC_Email_CRM_Notification();
	$email_classes['DSIC_Email_Data_Redaction']       = new DSIC_Email_Data_Redaction();

	return $email_classes;
}

/**
 * Plugin activation hook.
 *
 * @return void
 */
function dsic_activate(): void {
	// Check requirements before activation.
	$requirements = dsic_check_requirements();
	if ( is_wp_error( $requirements ) ) {
		wp_die(
			wp_kses_post( $requirements->get_error_message() ),
			esc_html__( 'Plugin Activation Error', 'droix-stripe-id-check' ),
			array( 'back_link' => true )
		);
	}

	require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-activator.php';
	DSIC_Activator::activate();
}
register_activation_hook( __FILE__, 'dsic_activate' );

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function dsic_deactivate(): void {
	require_once DSIC_PLUGIN_DIR . 'includes/class-dsic-deactivator.php';
	DSIC_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'dsic_deactivate' );

/**
 * Add settings link to plugins page.
 *
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links.
 */
function dsic_plugin_action_links( array $links ): array {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'admin.php?page=dsic-settings' ) ),
		esc_html__( 'Settings', 'droix-stripe-id-check' )
	);

	array_unshift( $links, $settings_link );

	return $links;
}
add_filter( 'plugin_action_links_' . DSIC_PLUGIN_BASENAME, 'dsic_plugin_action_links' );
