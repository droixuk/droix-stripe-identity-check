<?php
/**
 * Admin functionality class.
 *
 * Handles admin-specific functionality including assets.
 *
 * @package    DSIC
 * @subpackage DSIC/admin
 * @since      0.0.1
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Admin
 *
 * @since 0.0.1
 */
class DSIC_Admin {

	/**
	 * Plugin screens where assets should be loaded.
	 *
	 * @since 0.0.1
	 * @since 1.6.0 Changed ID Check from WooCommerce submenu to top-level menu.
	 * @var array
	 */
	private array $plugin_screens = array(
		'toplevel_page_droix-plugins',
		'droix-plugins_page_dsic-settings',
		'toplevel_page_dsic-id-check', // Top-level menu (changed from woocommerce_page_dsic-id-check).
	);

	/**
	 * Check if current screen is a plugin page.
	 *
	 * @since 0.0.1
	 * @return bool
	 */
	private function is_plugin_screen(): bool {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		return in_array( $screen->id, $this->plugin_screens, true );
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function enqueue_styles(): void {
		if ( ! $this->is_plugin_screen() ) {
			return;
		}

		wp_enqueue_style(
			'dsic-admin',
			DSIC_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			DSIC_VERSION
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( ! $this->is_plugin_screen() ) {
			return;
		}

		wp_enqueue_script(
			'dsic-admin',
			DSIC_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			DSIC_VERSION,
			true
		);

		$current_user = wp_get_current_user();

		wp_localize_script(
			'dsic-admin',
			'dsic_ajax',
			array(
				'ajax_url'           => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( 'dsic_nonce' ),
				'current_user_email' => $current_user->user_email,
				'strings'            => array(
					'testing'          => __( 'Testing connection...', 'droix-stripe-id-check' ),
					'success'          => __( 'Connection successful!', 'droix-stripe-id-check' ),
					'error'            => __( 'Connection failed', 'droix-stripe-id-check' ),
					'enter_keys'       => __( 'Please enter API keys first.', 'droix-stripe-id-check' ),
					'confirm_clear'    => __( 'Are you sure you want to clear all logs?', 'droix-stripe-id-check' ),
					'logs_cleared'     => __( 'Logs cleared successfully.', 'droix-stripe-id-check' ),
					'saving'           => __( 'Saving...', 'droix-stripe-id-check' ),
					'saved'            => __( 'Settings saved.', 'droix-stripe-id-check' ),
					'enter_email'      => __( 'Enter recipient email address:', 'droix-stripe-id-check' ),
					'sending'          => __( 'Sending...', 'droix-stripe-id-check' ),
					'confirm_reset'    => __( 'Are you sure you want to reset this template to default?', 'droix-stripe-id-check' ),
					'confirm_reset_all' => __( 'Are you sure you want to reset ALL email templates to their default values? This will overwrite any customizations you have made.', 'droix-stripe-id-check' ),
					'resetting'        => __( 'Resetting...', 'droix-stripe-id-check' ),
					'exporting'        => __( 'Exporting...', 'droix-stripe-id-check' ),
					'exported'         => __( 'Exported', 'droix-stripe-id-check' ),
					'records'          => __( 'records', 'droix-stripe-id-check' ),
					'copied'           => __( 'Copied!', 'droix-stripe-id-check' ),
				),
			)
		);
	}

	/**
	 * Add admin notices.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function admin_notices(): void {
		// Check if plugin is enabled but API keys are missing.
		$enabled    = get_option( 'dsic_enabled', false );
		$secret_key = get_option( 'dsic_stripe_secret_key', '' );

		if ( $enabled && empty( $secret_key ) ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<strong><?php esc_html_e( 'Stripe ID Check:', 'droix-stripe-id-check' ); ?></strong>
					<?php
					printf(
						/* translators: %s: Settings page URL */
						wp_kses_post( __( 'Plugin is enabled but API keys are not configured. <a href="%s">Configure now</a>.', 'droix-stripe-id-check' ) ),
						esc_url( admin_url( 'admin.php?page=dsic-settings' ) )
					);
					?>
				</p>
			</div>
			<?php
		}
	}
}
