<?php
/**
 * Menu registration class.
 *
 * Handles registration of admin menu pages.
 *
 * @package    DSIC
 * @subpackage DSIC/admin
 * @since      0.0.1
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Menu
 *
 * @since 0.0.1
 */
class DSIC_Menu {

	/**
	 * Parent menu slug.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	private string $parent_slug = 'droix-plugins';

	/**
	 * Register admin menu pages.
	 *
	 * @since 0.0.1
	 * @since 0.5.6 Added WooCommerce submenu for ID Check (below Payments).
	 * @return void
	 */
	public function register_menu(): void {
		// Check if DROIX Plugins parent menu already exists.
		global $admin_page_hooks;

		if ( ! isset( $admin_page_hooks[ $this->parent_slug ] ) ) {
			// Create parent menu.
			add_menu_page(
				__( 'DROIX Plugins', 'droix-stripe-id-check' ),
				__( 'DROIX Plugins', 'droix-stripe-id-check' ),
				'manage_options',
				$this->parent_slug,
				array( $this, 'render_overview_page' ),
				'dashicons-admin-generic',
				58
			);
		}

		// Add Stripe ID Check submenu under DROIX Plugins.
		add_submenu_page(
			$this->parent_slug,
			__( 'Stripe ID Check', 'droix-stripe-id-check' ),
			__( 'Stripe ID Check', 'droix-stripe-id-check' ),
			'manage_woocommerce',
			'dsic-settings',
			array( $this, 'render_settings_page' )
		);

		// Add ID Check as a top-level menu (right after WooCommerce, before Payments).
		add_menu_page(
			__( 'ID Check', 'droix-stripe-id-check' ),
			__( 'ID Check', 'droix-stripe-id-check' ),
			'manage_woocommerce',
			'dsic-id-check',
			array( $this, 'render_settings_page' ),
			'dashicons-id-alt', // ID card icon.
			55.9 // Position after WooCommerce (55) but before Payments (56).
		);

		// Add Linnworks Log submenu (hidden from main menu, accessible via direct link).
		add_submenu_page(
			null, // Hidden from menu.
			__( 'Linnworks Log', 'droix-stripe-id-check' ),
			__( 'Linnworks Log', 'droix-stripe-id-check' ),
			'manage_woocommerce',
			'dsic-linnworks-log',
			array( $this, 'render_linnworks_log_page' )
		);

		// Add Compliance Report submenu (v1.7.0+, hidden from main menu, accessible via direct link).
		add_submenu_page(
			null, // Hidden from menu.
			__( 'GDPR Compliance Report', 'droix-stripe-id-check' ),
			__( 'Compliance Report', 'droix-stripe-id-check' ),
			'manage_woocommerce',
			'dsic-compliance-report',
			array( $this, 'render_compliance_report_page' )
		);
	}

	/**
	 * Render the DROIX Plugins overview page.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function render_overview_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'droix-stripe-id-check' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'DROIX Plugins', 'droix-stripe-id-check' ); ?></h1>
			<div class="dsic-overview-cards">
				<div class="dsic-card">
					<h2><?php esc_html_e( 'Stripe ID Check', 'droix-stripe-id-check' ); ?></h2>
					<p><?php esc_html_e( 'Request and manage customer ID verification using Stripe Identity.', 'droix-stripe-id-check' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsic-settings' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Configure', 'droix-stripe-id-check' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the settings page.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'droix-stripe-id-check' ) );
		}

		include DSIC_PLUGIN_DIR . 'admin/partials/settings-page.php';
	}

	/**
	 * Render the Linnworks log page.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public function render_linnworks_log_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'droix-stripe-id-check' ) );
		}

		include DSIC_PLUGIN_DIR . 'admin/partials/linnworks-log-page.php';
	}

	/**
	 * Render the compliance report page.
	 *
	 * @since 1.7.0
	 * @return void
	 */
	public function render_compliance_report_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'droix-stripe-id-check' ) );
		}

		include DSIC_PLUGIN_DIR . 'admin/partials/compliance-report-page.php';
	}
}
