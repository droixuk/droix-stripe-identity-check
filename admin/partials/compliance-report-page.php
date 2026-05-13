<?php
/**
 * Compliance Report page template.
 *
 * GDPR compliance audit log for data redaction activities.
 *
 * @package    DSIC
 * @subpackage DSIC/admin/partials
 * @since      1.7.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

// Handle CSV export.
if ( isset( $_GET['action'] ) && 'export_csv' === $_GET['action'] ) {
	DSIC_Compliance_Report::handle_export_request();
	exit;
}

// Get current filter values.
$current_page   = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$current_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
$date_from      = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
$date_to        = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

// Build filters array.
$filters = array();
if ( ! empty( $current_status ) ) {
	$filters['status'] = $current_status;
}
if ( ! empty( $date_from ) ) {
	$filters['start_date'] = $date_from;
}
if ( ! empty( $date_to ) ) {
	$filters['end_date'] = $date_to;
}

// Get logs with pagination.
$per_page    = 25;
$offset      = ( $current_page - 1 ) * $per_page;
$logs        = DSIC_Compliance_Report::get_log( $per_page, $offset, $filters );
$total_items = DSIC_Compliance_Report::get_log_count( $filters );
$total_pages = ceil( $total_items / $per_page );

// Get stats.
$stats_all   = DSIC_Compliance_Report::get_stats( $date_from, $date_to );
$stats_today = DSIC_Compliance_Report::get_stats( gmdate( 'Y-m-d' ), gmdate( 'Y-m-d' ) );
$stats_month = DSIC_Compliance_Report::get_stats( gmdate( 'Y-m-01' ), gmdate( 'Y-m-d' ) );

// Get auto-redaction settings for display.
$auto_enabled    = get_option( 'dsic_auto_redaction_enabled', '0' );
$retention_days  = get_option( 'dsic_redaction_days', '30' );
$batch_size      = get_option( 'dsic_redaction_batch_size', '20' );

// Get next scheduled run.
$next_run = null;
if ( function_exists( 'as_next_scheduled_action' ) ) {
	$next_run = as_next_scheduled_action( 'dsic_daily_redaction_check' );
} elseif ( wp_next_scheduled( 'dsic_daily_redaction_check' ) ) {
	$next_run = wp_next_scheduled( 'dsic_daily_redaction_check' );
}
?>

<div class="wrap dsic-compliance-wrap">
	<h1>
		<?php esc_html_e( 'GDPR Compliance Report', 'droix-stripe-id-check' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsic-settings&tab=data_retention' ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Settings', 'droix-stripe-id-check' ); ?>
		</a>
	</h1>

	<p class="description">
		<?php esc_html_e( 'Audit log of all data redaction activities (GDPR Article 17: Right to Erasure).', 'droix-stripe-id-check' ); ?>
	</p>

	<!-- Auto-Redaction Status -->
	<div class="dsic-notice <?php echo '1' === $auto_enabled ? 'dsic-notice-success' : 'dsic-notice-warning'; ?>" style="margin: 20px 0;">
		<span class="dashicons <?php echo '1' === $auto_enabled ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
		<div>
			<strong>
				<?php
				if ( '1' === $auto_enabled ) {
					esc_html_e( 'Auto-Redaction: Enabled', 'droix-stripe-id-check' );
				} else {
					esc_html_e( 'Auto-Redaction: Disabled', 'droix-stripe-id-check' );
				}
				?>
			</strong>
			<p style="margin: 5px 0 0 0;">
				<?php
				if ( '1' === $auto_enabled ) {
					if ( $next_run ) {
						printf(
							/* translators: 1: retention days, 2: batch size, 3: next run date */
							esc_html__( 'Retention: %1$d days | Batch: %2$d orders/day | Next Run: %3$s', 'droix-stripe-id-check' ),
							absint( $retention_days ),
							absint( $batch_size ),
							esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ) )
						);
					} else {
						printf(
							/* translators: 1: retention days, 2: batch size */
							esc_html__( 'Retention: %1$d days | Batch: %2$d orders/day | Schedule pending...', 'droix-stripe-id-check' ),
							absint( $retention_days ),
							absint( $batch_size )
						);
					}
				} else {
					esc_html_e( 'Automatic data redaction is currently disabled. Enable it in settings to comply with GDPR data minimization requirements.', 'droix-stripe-id-check' );
				}
				?>
			</p>
		</div>
	</div>

	<!-- Stats Overview -->
	<div class="dsic-stats-overview" style="display: flex; gap: 15px; margin: 20px 0; flex-wrap: wrap;">
		<!-- Today Stats -->
		<div class="dsic-stat-card" style="flex: 1; min-width: 200px; background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 15px;">
			<h3 style="margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; color: #666;">
				<?php esc_html_e( 'Today', 'droix-stripe-id-check' ); ?>
			</h3>
			<div style="font-size: 32px; font-weight: 600; color: #2271b1; margin-bottom: 10px;">
				<?php echo esc_html( $stats_today['total'] ); ?>
			</div>
			<div style="font-size: 12px; color: #666;">
				<span style="color: #00a32a;">✓ <?php echo esc_html( $stats_today['completed'] ); ?></span> /
				<span style="color: #d63638;">✗ <?php echo esc_html( $stats_today['failed'] ); ?></span> /
				<span style="color: #dba617;">⏱ <?php echo esc_html( $stats_today['pending'] ); ?></span>
			</div>
		</div>

		<!-- This Month Stats -->
		<div class="dsic-stat-card" style="flex: 1; min-width: 200px; background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 15px;">
			<h3 style="margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; color: #666;">
				<?php esc_html_e( 'This Month', 'droix-stripe-id-check' ); ?>
			</h3>
			<div style="font-size: 32px; font-weight: 600; color: #2271b1; margin-bottom: 10px;">
				<?php echo esc_html( $stats_month['total'] ); ?>
			</div>
			<div style="font-size: 12px; color: #666;">
				<span style="color: #00a32a;">✓ <?php echo esc_html( $stats_month['completed'] ); ?></span> /
				<span style="color: #d63638;">✗ <?php echo esc_html( $stats_month['failed'] ); ?></span> /
				<span style="color: #dba617;">⏱ <?php echo esc_html( $stats_month['pending'] ); ?></span>
			</div>
		</div>

		<!-- All Time Stats -->
		<div class="dsic-stat-card" style="flex: 1; min-width: 200px; background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 15px;">
			<h3 style="margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; color: #666;">
				<?php esc_html_e( 'All Time', 'droix-stripe-id-check' ); ?>
			</h3>
			<div style="font-size: 32px; font-weight: 600; color: #2271b1; margin-bottom: 10px;">
				<?php echo esc_html( $stats_all['total'] ); ?>
			</div>
			<div style="font-size: 12px; color: #666;">
				<span style="color: #00a32a;">✓ <?php echo esc_html( $stats_all['completed'] ); ?></span> /
				<span style="color: #d63638;">✗ <?php echo esc_html( $stats_all['failed'] ); ?></span> /
				<span style="color: #dba617;">⏱ <?php echo esc_html( $stats_all['pending'] ); ?></span>
			</div>
		</div>
	</div>

	<!-- Filters -->
	<div class="dsic-filters" style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 20px 0;">
		<form method="get" action="">
			<input type="hidden" name="page" value="dsic-compliance-report">

			<div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
				<div>
					<label for="status" style="display: block; margin-bottom: 3px; font-weight: 500;">
						<?php esc_html_e( 'Status', 'droix-stripe-id-check' ); ?>
					</label>
					<select name="status" id="status">
						<option value=""><?php esc_html_e( 'All Statuses', 'droix-stripe-id-check' ); ?></option>
						<option value="completed" <?php selected( $current_status, 'completed' ); ?>><?php esc_html_e( 'Completed', 'droix-stripe-id-check' ); ?></option>
						<option value="failed" <?php selected( $current_status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'droix-stripe-id-check' ); ?></option>
						<option value="pending" <?php selected( $current_status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'droix-stripe-id-check' ); ?></option>
					</select>
				</div>

				<div>
					<label for="date_from" style="display: block; margin-bottom: 3px; font-weight: 500;">
						<?php esc_html_e( 'From Date', 'droix-stripe-id-check' ); ?>
					</label>
					<input type="date" name="date_from" id="date_from" value="<?php echo esc_attr( $date_from ); ?>">
				</div>

				<div>
					<label for="date_to" style="display: block; margin-bottom: 3px; font-weight: 500;">
						<?php esc_html_e( 'To Date', 'droix-stripe-id-check' ); ?>
					</label>
					<input type="date" name="date_to" id="date_to" value="<?php echo esc_attr( $date_to ); ?>">
				</div>

				<div>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Filter', 'droix-stripe-id-check' ); ?>
					</button>
					<?php if ( ! empty( $filters ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsic-compliance-report' ) ); ?>" class="button">
							<?php esc_html_e( 'Clear', 'droix-stripe-id-check' ); ?>
						</a>
					<?php endif; ?>
				</div>

				<div style="margin-left: auto;">
					<?php
					$export_url = add_query_arg(
						array(
							'action'     => 'export_csv',
							'start_date' => $date_from,
							'end_date'   => $date_to,
							'_wpnonce'   => wp_create_nonce( 'dsic_export_compliance' ),
						),
						admin_url( 'admin.php?page=dsic-compliance-report' )
					);
					?>
					<a href="<?php echo esc_url( $export_url ); ?>" class="button">
						<span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
						<?php esc_html_e( 'Export CSV', 'droix-stripe-id-check' ); ?>
					</a>
				</div>
			</div>
		</form>
	</div>

	<!-- Log Table -->
	<?php if ( empty( $logs ) ) : ?>
		<div class="dsic-notice dsic-notice-info" style="margin: 20px 0;">
			<span class="dashicons dashicons-info"></span>
			<p>
				<?php
				if ( ! empty( $filters ) ) {
					esc_html_e( 'No redaction activities match your filter criteria.', 'droix-stripe-id-check' );
				} else {
					esc_html_e( 'No redaction activities recorded yet. Data redaction history will appear here once auto-redaction runs or manual redactions are performed.', 'droix-stripe-id-check' );
				}
				?>
			</p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
			<thead>
				<tr>
					<th style="width: 100px;"><?php esc_html_e( 'Order ID', 'droix-stripe-id-check' ); ?></th>
					<th style="width: 150px;"><?php esc_html_e( 'Date', 'droix-stripe-id-check' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Action', 'droix-stripe-id-check' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Status', 'droix-stripe-id-check' ); ?></th>
					<th><?php esc_html_e( 'Details', 'droix-stripe-id-check' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<?php
					$order      = wc_get_order( $log['order_id'] );
					$details    = json_decode( $log['details'], true );
					$status_map = array(
						'completed' => array( 'color' => '#00a32a', 'icon' => 'yes-alt', 'label' => __( 'Completed', 'droix-stripe-id-check' ) ),
						'failed'    => array( 'color' => '#d63638', 'icon' => 'dismiss', 'label' => __( 'Failed', 'droix-stripe-id-check' ) ),
						'pending'   => array( 'color' => '#dba617', 'icon' => 'clock', 'label' => __( 'Pending', 'droix-stripe-id-check' ) ),
					);
					$status_info = $status_map[ $log['status'] ] ?? $status_map['pending'];
					?>
					<tr>
						<td>
							<?php if ( $order ) : ?>
								<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
									#<?php echo esc_html( $log['order_id'] ); ?>
								</a>
							<?php else : ?>
								#<?php echo esc_html( $log['order_id'] ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log['created_at'] ) ) ); ?>
						</td>
						<td>
							<?php echo esc_html( ucwords( str_replace( '_', ' ', $log['action'] ) ) ); ?>
						</td>
						<td>
							<span style="color: <?php echo esc_attr( $status_info['color'] ); ?>;">
								<span class="dashicons dashicons-<?php echo esc_attr( $status_info['icon'] ); ?>" style="font-size: 16px; width: 16px; height: 16px;"></span>
								<?php echo esc_html( $status_info['label'] ); ?>
							</span>
						</td>
						<td>
							<?php if ( ! empty( $details ) ) : ?>
								<?php if ( 'completed' === $log['status'] ) : ?>
									<?php
									/* translators: %d: number of days data was retained */
									printf( esc_html__( 'Data retained: %d days', 'droix-stripe-id-check' ), absint( $details['days_retained'] ?? 0 ) );
									?>
									<?php if ( ! empty( $details['already_redacted'] ) ) : ?>
										<br><em>(<?php esc_html_e( 'Already redacted', 'droix-stripe-id-check' ); ?>)</em>
									<?php endif; ?>
								<?php elseif ( 'failed' === $log['status'] ) : ?>
									<?php
									/* translators: 1: error message, 2: number of attempts */
									printf( esc_html__( 'Error: %1$s (Attempts: %2$d)', 'droix-stripe-id-check' ), esc_html( $details['error'] ?? 'Unknown' ), absint( $details['attempts'] ?? 0 ) );
									?>
								<?php else : ?>
									<?php esc_html_e( 'Processing...', 'droix-stripe-id-check' ); ?>
								<?php endif; ?>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav" style="margin-top: 15px;">
				<div class="tablenav-pages">
					<?php
					$pagination_args = array_merge(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $current_page,
							'total'     => $total_pages,
							'prev_text' => __( '&laquo; Previous', 'droix-stripe-id-check' ),
							'next_text' => __( 'Next &raquo;', 'droix-stripe-id-check' ),
						)
					);
					echo wp_kses_post( paginate_links( $pagination_args ) );
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
