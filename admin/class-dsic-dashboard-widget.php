<?php
/**
 * Dashboard widget class.
 *
 * Handles the WordPress admin dashboard widget.
 *
 * @package    DSIC
 * @subpackage DSIC/admin
 * @since      0.3.1
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Dashboard_Widget
 *
 * @since 0.3.1
 */
class DSIC_Dashboard_Widget {

	/**
	 * Widget ID.
	 */
	const WIDGET_ID = 'dsic_verification_stats';

	/**
	 * Constructor.
	 *
	 * @since 0.3.1
	 */
	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Register the dashboard widget.
	 *
	 * @since 0.3.1
	 * @return void
	 */
	public function register_widget(): void {
		// Only show to users who can manage WooCommerce.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Only show if plugin is enabled.
		if ( ! get_option( 'dsic_enabled', false ) ) {
			return;
		}

		wp_add_dashboard_widget(
			self::WIDGET_ID,
			__( 'ID Verification Stats', 'droix-stripe-id-check' ),
			array( $this, 'render_widget' ),
			null,
			null,
			'normal',
			'high'
		);
	}

	/**
	 * Enqueue widget styles.
	 *
	 * @since 0.3.1
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_styles( string $hook ): void {
		if ( 'index.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'dsic-dashboard-widget',
			DSIC_PLUGIN_URL . 'assets/css/dashboard-widget.css',
			array(),
			DSIC_VERSION
		);
	}

	/**
	 * Render the dashboard widget.
	 *
	 * @since 0.3.1
	 * @return void
	 */
	public function render_widget(): void {
		$settings   = new DSIC_Settings();
		$api_status = $settings->get_api_status();

		// Show warning if API keys are missing.
		if ( ! $api_status['configured'] ) {
			$this->render_api_warning( $api_status );
			return;
		}

		$stats = DSIC_Stats::get_all_stats();
		$rates = $stats['rates'];
		$totals = $stats['totals'];
		$recent = array_slice( $stats['recent'], 0, 5 );
		$periods = $stats['by_period'];

		?>
		<div class="dsic-widget">
			<!-- Summary Stats -->
			<div class="dsic-widget-summary">
				<div class="dsic-stat-box dsic-stat-verified">
					<span class="dsic-stat-number"><?php echo esc_html( number_format_i18n( $totals['verified'] ) ); ?></span>
					<span class="dsic-stat-label"><?php esc_html_e( 'Verified', 'droix-stripe-id-check' ); ?></span>
				</div>
				<div class="dsic-stat-box dsic-stat-pending">
					<span class="dsic-stat-number"><?php echo esc_html( number_format_i18n( $totals['pending'] ) ); ?></span>
					<span class="dsic-stat-label"><?php esc_html_e( 'Pending', 'droix-stripe-id-check' ); ?></span>
				</div>
				<div class="dsic-stat-box dsic-stat-failed">
					<span class="dsic-stat-number"><?php echo esc_html( number_format_i18n( $totals['failed'] ) ); ?></span>
					<span class="dsic-stat-label"><?php esc_html_e( 'Failed', 'droix-stripe-id-check' ); ?></span>
				</div>
			</div>

			<!-- Success Rate -->
			<div class="dsic-widget-rate">
				<div class="dsic-rate-bar">
					<div class="dsic-rate-fill" style="width: <?php echo esc_attr( $rates['success_rate'] ); ?>%;"></div>
				</div>
				<div class="dsic-rate-text">
					<span class="dsic-rate-number"><?php echo esc_html( $rates['success_rate'] ); ?>%</span>
					<span class="dsic-rate-label"><?php esc_html_e( 'Success Rate', 'droix-stripe-id-check' ); ?></span>
				</div>
			</div>

			<!-- Period Stats -->
			<div class="dsic-widget-periods">
				<table class="dsic-periods-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Period', 'droix-stripe-id-check' ); ?></th>
							<th class="dsic-col-center"><?php esc_html_e( 'Total', 'droix-stripe-id-check' ); ?></th>
							<th class="dsic-col-center"><?php esc_html_e( 'Passed', 'droix-stripe-id-check' ); ?></th>
							<th class="dsic-col-center"><?php esc_html_e( 'Failed', 'droix-stripe-id-check' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $periods as $key => $period ) : ?>
							<tr>
								<td><?php echo esc_html( $period['label'] ); ?></td>
								<td class="dsic-col-center"><?php echo esc_html( $period['total'] ); ?></td>
								<td class="dsic-col-center dsic-text-success"><?php echo esc_html( $period['verified'] ); ?></td>
								<td class="dsic-col-center dsic-text-danger"><?php echo esc_html( $period['failed'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( ! empty( $recent ) ) : ?>
				<!-- Recent Verifications -->
				<div class="dsic-widget-recent">
					<h4><?php esc_html_e( 'Recent Verifications', 'droix-stripe-id-check' ); ?></h4>
					<ul class="dsic-recent-list">
						<?php foreach ( $recent as $item ) : ?>
							<li class="dsic-recent-item">
								<a href="<?php echo esc_url( $item['edit_url'] ); ?>" class="dsic-recent-order">
									#<?php echo esc_html( $item['order_number'] ); ?>
								</a>
								<span class="dsic-recent-customer"><?php echo esc_html( $item['customer'] ); ?></span>
								<span class="dsic-recent-status dsic-status-<?php echo esc_attr( $item['status'] ); ?>">
									<?php echo esc_html( ucfirst( $item['status'] ) ); ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<!-- Widget Footer -->
			<div class="dsic-widget-footer">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsic-settings&tab=dashboard' ) ); ?>" class="dsic-view-all">
					<?php esc_html_e( 'View Full Dashboard', 'droix-stripe-id-check' ); ?>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render API configuration warning.
	 *
	 * @since 1.6.1
	 * @param array $api_status API status from DSIC_Settings::get_api_status().
	 * @return void
	 */
	private function render_api_warning( array $api_status ): void {
		$settings_url = admin_url( 'admin.php?page=dsic-settings&tab=api' );
		$missing_text = implode( ', ', $api_status['missing'] );
		?>
		<div class="dsic-widget dsic-widget-warning">
			<div class="dsic-api-warning">
				<span class="dashicons dashicons-warning"></span>
				<div class="dsic-warning-content">
					<strong><?php esc_html_e( 'Configuration Required', 'droix-stripe-id-check' ); ?></strong>
					<p>
						<?php
						printf(
							/* translators: 1: Mode label (Test/Live), 2: List of missing items */
							esc_html__( 'ID verification is not working. Missing %1$s API credentials: %2$s', 'droix-stripe-id-check' ),
							esc_html( $api_status['mode_label'] ),
							esc_html( $missing_text )
						);
						?>
					</p>
					<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary">
						<?php esc_html_e( 'Configure API Settings', 'droix-stripe-id-check' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}
}
