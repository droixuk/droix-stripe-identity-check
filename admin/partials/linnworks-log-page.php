<?php
/**
 * Linnworks Log page template.
 *
 * @package    DSIC
 * @subpackage DSIC/admin/partials
 * @since      1.5.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

// Get current filter values.
$current_page   = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$current_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
$current_action = isset( $_GET['action_type'] ) ? sanitize_key( $_GET['action_type'] ) : '';
$order_search   = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
$date_from      = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
$date_to        = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';

// Get logs with pagination.
$logs = DSIC_Linnworks_Logger::get_logs(
	array(
		'page'        => $current_page,
		'per_page'    => 25,
		'status'      => $current_status,
		'action'      => $current_action,
		'wc_order_id' => $order_search,
		'date_from'   => $date_from,
		'date_to'     => $date_to,
	)
);

// Get stats for different periods.
$stats_today = DSIC_Linnworks_Logger::get_stats( 'today' );
$stats_week  = DSIC_Linnworks_Logger::get_stats( 'week' );
$stats_month = DSIC_Linnworks_Logger::get_stats( 'month' );

// Get recent failures.
$recent_failures = DSIC_Linnworks_Logger::get_recent_failures( 5 );
?>

<div class="wrap dsic-linnworks-log-wrap">
	<h1>
		<?php esc_html_e( 'Linnworks Operation Log', 'droix-stripe-id-check' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsic-settings&tab=linnworks' ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Settings', 'droix-stripe-id-check' ); ?>
		</a>
	</h1>

	<!-- Stats Overview -->
	<div class="dsic-lw-stats-overview">
		<div class="dsic-lw-stat-card">
			<h3><?php esc_html_e( 'Today', 'droix-stripe-id-check' ); ?></h3>
			<div class="dsic-lw-stat-numbers">
				<span class="dsic-lw-stat-total"><?php echo esc_html( $stats_today['total'] ); ?></span>
				<span class="dsic-lw-stat-label"><?php esc_html_e( 'operations', 'droix-stripe-id-check' ); ?></span>
			</div>
			<div class="dsic-lw-stat-details">
				<span class="dsic-lw-success"><?php echo esc_html( $stats_today['success'] ); ?> <?php esc_html_e( 'success', 'droix-stripe-id-check' ); ?></span>
				<span class="dsic-lw-failed"><?php echo esc_html( $stats_today['failed'] ); ?> <?php esc_html_e( 'failed', 'droix-stripe-id-check' ); ?></span>
			</div>
			<div class="dsic-lw-stat-rate">
				<?php echo esc_html( $stats_today['success_rate'] ); ?>% <?php esc_html_e( 'success rate', 'droix-stripe-id-check' ); ?>
			</div>
		</div>

		<div class="dsic-lw-stat-card">
			<h3><?php esc_html_e( 'Last 7 Days', 'droix-stripe-id-check' ); ?></h3>
			<div class="dsic-lw-stat-numbers">
				<span class="dsic-lw-stat-total"><?php echo esc_html( $stats_week['total'] ); ?></span>
				<span class="dsic-lw-stat-label"><?php esc_html_e( 'operations', 'droix-stripe-id-check' ); ?></span>
			</div>
			<div class="dsic-lw-stat-details">
				<span class="dsic-lw-success"><?php echo esc_html( $stats_week['success'] ); ?> <?php esc_html_e( 'success', 'droix-stripe-id-check' ); ?></span>
				<span class="dsic-lw-failed"><?php echo esc_html( $stats_week['failed'] ); ?> <?php esc_html_e( 'failed', 'droix-stripe-id-check' ); ?></span>
			</div>
			<div class="dsic-lw-stat-rate">
				<?php echo esc_html( $stats_week['success_rate'] ); ?>% <?php esc_html_e( 'success rate', 'droix-stripe-id-check' ); ?>
			</div>
		</div>

		<div class="dsic-lw-stat-card">
			<h3><?php esc_html_e( 'Last 30 Days', 'droix-stripe-id-check' ); ?></h3>
			<div class="dsic-lw-stat-numbers">
				<span class="dsic-lw-stat-total"><?php echo esc_html( $stats_month['total'] ); ?></span>
				<span class="dsic-lw-stat-label"><?php esc_html_e( 'operations', 'droix-stripe-id-check' ); ?></span>
			</div>
			<div class="dsic-lw-stat-details">
				<span class="dsic-lw-success"><?php echo esc_html( $stats_month['success'] ); ?> <?php esc_html_e( 'success', 'droix-stripe-id-check' ); ?></span>
				<span class="dsic-lw-failed"><?php echo esc_html( $stats_month['failed'] ); ?> <?php esc_html_e( 'failed', 'droix-stripe-id-check' ); ?></span>
			</div>
			<div class="dsic-lw-stat-rate">
				<?php echo esc_html( $stats_month['success_rate'] ); ?>% <?php esc_html_e( 'success rate', 'droix-stripe-id-check' ); ?>
			</div>
		</div>

		<div class="dsic-lw-stat-card dsic-lw-stat-performance">
			<h3><?php esc_html_e( 'Performance', 'droix-stripe-id-check' ); ?></h3>
			<div class="dsic-lw-stat-numbers">
				<span class="dsic-lw-stat-total"><?php echo esc_html( $stats_month['avg_response_ms'] ); ?>ms</span>
				<span class="dsic-lw-stat-label"><?php esc_html_e( 'avg response', 'droix-stripe-id-check' ); ?></span>
			</div>
			<div class="dsic-lw-stat-details">
				<span><?php echo esc_html( $stats_month['locks'] ); ?> <?php esc_html_e( 'locks', 'droix-stripe-id-check' ); ?></span>
				<span><?php echo esc_html( $stats_month['unlocks'] ); ?> <?php esc_html_e( 'unlocks', 'droix-stripe-id-check' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Recent Failures Alert -->
	<?php if ( ! empty( $recent_failures ) ) : ?>
		<div class="dsic-lw-failures-alert">
			<h3>
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'Recent Failures', 'droix-stripe-id-check' ); ?>
			</h3>
			<table class="dsic-lw-failures-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'droix-stripe-id-check' ); ?></th>
						<th><?php esc_html_e( 'Order', 'droix-stripe-id-check' ); ?></th>
						<th><?php esc_html_e( 'Action', 'droix-stripe-id-check' ); ?></th>
						<th><?php esc_html_e( 'Error', 'droix-stripe-id-check' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_failures as $failure ) : ?>
						<tr>
							<td><?php echo esc_html( date_i18n( 'M j, H:i', strtotime( $failure['created_at'] ) ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $failure['wc_order_id'] . '&action=edit' ) ); ?>">
									#<?php echo esc_html( $failure['wc_order_id'] ); ?>
								</a>
							</td>
							<td><?php echo esc_html( ucfirst( $failure['action'] ) ); ?></td>
							<td class="dsic-lw-error-msg"><?php echo esc_html( $failure['error_message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<!-- Filters -->
	<div class="dsic-lw-filters">
		<form method="get" action="">
			<input type="hidden" name="page" value="dsic-linnworks-log">

			<label for="dsic-filter-order">
				<?php esc_html_e( 'Order ID:', 'droix-stripe-id-check' ); ?>
				<input type="number" id="dsic-filter-order" name="order_id" value="<?php echo esc_attr( $order_search ?: '' ); ?>" placeholder="12345" min="1">
			</label>

			<label for="dsic-filter-status">
				<?php esc_html_e( 'Status:', 'droix-stripe-id-check' ); ?>
				<select id="dsic-filter-status" name="status">
					<option value=""><?php esc_html_e( 'All', 'droix-stripe-id-check' ); ?></option>
					<option value="success" <?php selected( $current_status, 'success' ); ?>><?php esc_html_e( 'Success', 'droix-stripe-id-check' ); ?></option>
					<option value="failed" <?php selected( $current_status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'droix-stripe-id-check' ); ?></option>
					<option value="pending" <?php selected( $current_status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'droix-stripe-id-check' ); ?></option>
				</select>
			</label>

			<label for="dsic-filter-action">
				<?php esc_html_e( 'Action:', 'droix-stripe-id-check' ); ?>
				<select id="dsic-filter-action" name="action_type">
					<option value=""><?php esc_html_e( 'All', 'droix-stripe-id-check' ); ?></option>
					<option value="lock" <?php selected( $current_action, 'lock' ); ?>><?php esc_html_e( 'Lock', 'droix-stripe-id-check' ); ?></option>
					<option value="unlock" <?php selected( $current_action, 'unlock' ); ?>><?php esc_html_e( 'Unlock', 'droix-stripe-id-check' ); ?></option>
				</select>
			</label>

			<label for="dsic-filter-date-from">
				<?php esc_html_e( 'From:', 'droix-stripe-id-check' ); ?>
				<input type="date" id="dsic-filter-date-from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
			</label>

			<label for="dsic-filter-date-to">
				<?php esc_html_e( 'To:', 'droix-stripe-id-check' ); ?>
				<input type="date" id="dsic-filter-date-to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
			</label>

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'droix-stripe-id-check' ); ?></button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsic-linnworks-log' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'droix-stripe-id-check' ); ?></a>
		</form>
	</div>

	<!-- Log Table -->
	<div class="dsic-lw-log-table-wrap">
		<?php if ( ! empty( $logs['items'] ) ) : ?>
			<table class="dsic-lw-log-table widefat striped">
				<thead>
					<tr>
						<th class="dsic-lw-col-time"><?php esc_html_e( 'Time', 'droix-stripe-id-check' ); ?></th>
						<th class="dsic-lw-col-order"><?php esc_html_e( 'WC Order', 'droix-stripe-id-check' ); ?></th>
						<th class="dsic-lw-col-lw-order"><?php esc_html_e( 'LW Order', 'droix-stripe-id-check' ); ?></th>
						<th class="dsic-lw-col-action"><?php esc_html_e( 'Action', 'droix-stripe-id-check' ); ?></th>
						<th class="dsic-lw-col-trigger"><?php esc_html_e( 'Trigger', 'droix-stripe-id-check' ); ?></th>
						<th class="dsic-lw-col-status"><?php esc_html_e( 'Status', 'droix-stripe-id-check' ); ?></th>
						<th class="dsic-lw-col-time-ms"><?php esc_html_e( 'Response', 'droix-stripe-id-check' ); ?></th>
						<th class="dsic-lw-col-message"><?php esc_html_e( 'Message', 'droix-stripe-id-check' ); ?></th>
						<th class="dsic-lw-col-details"><?php esc_html_e( 'Details', 'droix-stripe-id-check' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs['items'] as $log ) : ?>
						<tr class="dsic-lw-row-<?php echo esc_attr( $log['status'] ); ?>">
							<td class="dsic-lw-col-time">
								<?php echo esc_html( date_i18n( 'M j, H:i:s', strtotime( $log['created_at'] ) ) ); ?>
							</td>
							<td class="dsic-lw-col-order">
								<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $log['wc_order_id'] . '&action=edit' ) ); ?>">
									#<?php echo esc_html( $log['wc_order_id'] ); ?>
								</a>
							</td>
							<td class="dsic-lw-col-lw-order">
								<?php if ( ! empty( $log['lw_order_id'] ) ) : ?>
									<code title="<?php echo esc_attr( $log['lw_order_id'] ); ?>"><?php echo esc_html( substr( $log['lw_order_id'], 0, 8 ) ); ?>...</code>
								<?php else : ?>
									<span class="dsic-lw-na">—</span>
								<?php endif; ?>
							</td>
							<td class="dsic-lw-col-action">
								<?php
								$action_icon = 'lock' === $log['action'] ? 'lock' : 'unlock';
								?>
								<span class="dsic-lw-action dsic-lw-action-<?php echo esc_attr( $log['action'] ); ?>">
									<span class="dashicons dashicons-<?php echo esc_attr( $action_icon ); ?>"></span>
									<?php echo esc_html( ucfirst( $log['action'] ) ); ?>
								</span>
							</td>
							<td class="dsic-lw-col-trigger">
								<span class="dsic-lw-trigger dsic-lw-trigger-<?php echo esc_attr( $log['trigger_source'] ); ?>">
									<?php echo esc_html( ucfirst( $log['trigger_source'] ) ); ?>
								</span>
							</td>
							<td class="dsic-lw-col-status">
								<span class="dsic-lw-status dsic-lw-status-<?php echo esc_attr( $log['status'] ); ?>">
									<?php
									if ( 'success' === $log['status'] ) {
										echo '<span class="dashicons dashicons-yes-alt"></span>';
									} elseif ( 'failed' === $log['status'] ) {
										echo '<span class="dashicons dashicons-dismiss"></span>';
									} else {
										echo '<span class="dashicons dashicons-clock"></span>';
									}
									echo esc_html( ucfirst( $log['status'] ) );
									?>
								</span>
							</td>
							<td class="dsic-lw-col-time-ms">
								<?php if ( ! empty( $log['response_time_ms'] ) ) : ?>
									<?php echo esc_html( $log['response_time_ms'] ); ?>ms
								<?php else : ?>
									<span class="dsic-lw-na">—</span>
								<?php endif; ?>
							</td>
							<td class="dsic-lw-col-message">
								<?php if ( ! empty( $log['error_message'] ) ) : ?>
									<span class="dsic-lw-error-message" title="<?php echo esc_attr( $log['error_message'] ); ?>">
										<?php echo esc_html( wp_trim_words( $log['error_message'], 10, '...' ) ); ?>
									</span>
								<?php else : ?>
									<span class="dsic-lw-na">—</span>
								<?php endif; ?>
							</td>
							<td class="dsic-lw-col-details">
								<button type="button" class="button button-small dsic-lw-view-details"
									data-log-id="<?php echo esc_attr( $log['id'] ); ?>"
									data-request="<?php echo esc_attr( $log['request_data'] ); ?>"
									data-response="<?php echo esc_attr( $log['response_data'] ); ?>">
									<span class="dashicons dashicons-visibility"></span>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $logs['total_pages'] > 1 ) : ?>
				<div class="dsic-lw-pagination">
					<?php
					$pagination_args = array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $current_page,
						'total'     => $logs['total_pages'],
						'prev_text' => '&laquo; ' . __( 'Previous', 'droix-stripe-id-check' ),
						'next_text' => __( 'Next', 'droix-stripe-id-check' ) . ' &raquo;',
					);
					echo paginate_links( $pagination_args ); // phpcs:ignore
					?>
					<span class="dsic-lw-pagination-info">
						<?php
						printf(
							/* translators: 1: current page, 2: total pages, 3: total items */
							esc_html__( 'Page %1$d of %2$d (%3$d items)', 'droix-stripe-id-check' ),
							$current_page,
							$logs['total_pages'],
							$logs['total']
						);
						?>
					</span>
				</div>
			<?php endif; ?>

		<?php else : ?>
			<div class="dsic-lw-no-logs">
				<span class="dashicons dashicons-info"></span>
				<p><?php esc_html_e( 'No log entries found.', 'droix-stripe-id-check' ); ?></p>
				<?php if ( ! empty( $current_status ) || ! empty( $current_action ) || ! empty( $order_search ) ) : ?>
					<p><?php esc_html_e( 'Try adjusting your filters.', 'droix-stripe-id-check' ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<!-- Details Modal -->
<div id="dsic-lw-details-modal" class="dsic-lw-modal" style="display: none;">
	<div class="dsic-lw-modal-content">
		<div class="dsic-lw-modal-header">
			<h2><?php esc_html_e( 'Request/Response Details', 'droix-stripe-id-check' ); ?></h2>
			<button type="button" class="dsic-lw-modal-close">&times;</button>
		</div>
		<div class="dsic-lw-modal-body">
			<div class="dsic-lw-detail-section">
				<h3><?php esc_html_e( 'Request Data', 'droix-stripe-id-check' ); ?></h3>
				<pre id="dsic-lw-request-data"></pre>
			</div>
			<div class="dsic-lw-detail-section">
				<h3><?php esc_html_e( 'Response Data', 'droix-stripe-id-check' ); ?></h3>
				<pre id="dsic-lw-response-data"></pre>
			</div>
		</div>
	</div>
</div>

<style>
/* Stats Overview */
.dsic-lw-stats-overview {
	display: flex;
	gap: 15px;
	margin: 20px 0;
	flex-wrap: wrap;
}

.dsic-lw-stat-card {
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 4px;
	padding: 15px 20px;
	flex: 1;
	min-width: 180px;
}

.dsic-lw-stat-card h3 {
	margin: 0 0 10px;
	font-size: 13px;
	color: #646970;
	text-transform: uppercase;
}

.dsic-lw-stat-numbers {
	display: flex;
	align-items: baseline;
	gap: 8px;
	margin-bottom: 8px;
}

.dsic-lw-stat-total {
	font-size: 32px;
	font-weight: 600;
	color: #1d2327;
}

.dsic-lw-stat-label {
	font-size: 13px;
	color: #646970;
}

.dsic-lw-stat-details {
	display: flex;
	gap: 15px;
	font-size: 13px;
}

.dsic-lw-success { color: #00a32a; }
.dsic-lw-failed { color: #d63638; }

.dsic-lw-stat-rate {
	margin-top: 8px;
	padding-top: 8px;
	border-top: 1px solid #f0f0f1;
	font-size: 12px;
	color: #646970;
}

/* Failures Alert */
.dsic-lw-failures-alert {
	background: #fcf0f1;
	border: 1px solid #d63638;
	border-radius: 4px;
	padding: 15px;
	margin-bottom: 20px;
}

.dsic-lw-failures-alert h3 {
	margin: 0 0 10px;
	color: #d63638;
	display: flex;
	align-items: center;
	gap: 5px;
}

.dsic-lw-failures-table {
	width: 100%;
	background: #fff;
	border-collapse: collapse;
}

.dsic-lw-failures-table th,
.dsic-lw-failures-table td {
	padding: 8px 12px;
	text-align: left;
	border-bottom: 1px solid #f0f0f1;
}

.dsic-lw-failures-table th {
	font-weight: 600;
	font-size: 12px;
}

.dsic-lw-error-msg {
	color: #d63638;
	max-width: 400px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

/* Filters */
.dsic-lw-filters {
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 4px;
	padding: 15px;
	margin-bottom: 20px;
}

.dsic-lw-filters form {
	display: flex;
	gap: 15px;
	align-items: flex-end;
	flex-wrap: wrap;
}

.dsic-lw-filters label {
	display: flex;
	flex-direction: column;
	gap: 5px;
	font-size: 13px;
	font-weight: 500;
}

.dsic-lw-filters input[type="number"],
.dsic-lw-filters input[type="date"],
.dsic-lw-filters select {
	min-width: 120px;
}

/* Log Table */
.dsic-lw-log-table-wrap {
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 4px;
}

.dsic-lw-log-table {
	margin: 0;
	border: 0;
}

.dsic-lw-log-table th {
	font-weight: 600;
	font-size: 12px;
}

.dsic-lw-log-table td {
	vertical-align: middle;
}

.dsic-lw-col-time { width: 120px; }
.dsic-lw-col-order { width: 80px; }
.dsic-lw-col-lw-order { width: 100px; }
.dsic-lw-col-action { width: 80px; }
.dsic-lw-col-trigger { width: 70px; }
.dsic-lw-col-status { width: 90px; }
.dsic-lw-col-time-ms { width: 80px; }
.dsic-lw-col-details { width: 60px; }

.dsic-lw-action {
	display: inline-flex;
	align-items: center;
	gap: 3px;
}

.dsic-lw-action .dashicons {
	font-size: 14px;
	width: 14px;
	height: 14px;
}

.dsic-lw-action-lock { color: #d63638; }
.dsic-lw-action-unlock { color: #00a32a; }

.dsic-lw-trigger {
	display: inline-block;
	padding: 2px 6px;
	border-radius: 3px;
	font-size: 11px;
	text-transform: uppercase;
}

.dsic-lw-trigger-auto { background: #e7f5e9; color: #00a32a; }
.dsic-lw-trigger-manual { background: #f0f0f1; color: #646970; }
.dsic-lw-trigger-api { background: #e5f3ff; color: #0073aa; }

.dsic-lw-status {
	display: inline-flex;
	align-items: center;
	gap: 3px;
	padding: 2px 8px;
	border-radius: 3px;
	font-size: 12px;
}

.dsic-lw-status .dashicons {
	font-size: 14px;
	width: 14px;
	height: 14px;
}

.dsic-lw-status-success { background: #e7f5e9; color: #00a32a; }
.dsic-lw-status-failed { background: #fcf0f1; color: #d63638; }
.dsic-lw-status-pending { background: #fcf9e8; color: #996800; }

.dsic-lw-row-failed { background: #fff8f8 !important; }

.dsic-lw-na { color: #c3c4c7; }

.dsic-lw-error-message {
	color: #d63638;
	cursor: help;
}

.dsic-lw-view-details .dashicons {
	font-size: 14px;
	width: 14px;
	height: 14px;
	vertical-align: middle;
}

/* Pagination */
.dsic-lw-pagination {
	padding: 15px;
	text-align: center;
	border-top: 1px solid #f0f0f1;
}

.dsic-lw-pagination .page-numbers {
	display: inline-block;
	padding: 5px 10px;
	margin: 0 2px;
	border: 1px solid #dcdcde;
	border-radius: 3px;
	text-decoration: none;
}

.dsic-lw-pagination .page-numbers.current {
	background: #2271b1;
	border-color: #2271b1;
	color: #fff;
}

.dsic-lw-pagination-info {
	display: block;
	margin-top: 10px;
	color: #646970;
	font-size: 13px;
}

/* No Logs */
.dsic-lw-no-logs {
	padding: 40px;
	text-align: center;
	color: #646970;
}

.dsic-lw-no-logs .dashicons {
	font-size: 48px;
	width: 48px;
	height: 48px;
	margin-bottom: 10px;
	color: #c3c4c7;
}

/* Modal */
.dsic-lw-modal {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.5);
	z-index: 100000;
	display: flex;
	align-items: center;
	justify-content: center;
}

.dsic-lw-modal-content {
	background: #fff;
	border-radius: 4px;
	max-width: 800px;
	width: 90%;
	max-height: 80vh;
	display: flex;
	flex-direction: column;
}

.dsic-lw-modal-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 15px 20px;
	border-bottom: 1px solid #dcdcde;
}

.dsic-lw-modal-header h2 {
	margin: 0;
	font-size: 16px;
}

.dsic-lw-modal-close {
	background: none;
	border: none;
	font-size: 24px;
	cursor: pointer;
	color: #646970;
	padding: 0;
	line-height: 1;
}

.dsic-lw-modal-close:hover {
	color: #d63638;
}

.dsic-lw-modal-body {
	padding: 20px;
	overflow-y: auto;
}

.dsic-lw-detail-section h3 {
	margin: 0 0 10px;
	font-size: 14px;
}

.dsic-lw-detail-section pre {
	background: #f6f7f7;
	padding: 15px;
	border-radius: 4px;
	overflow-x: auto;
	font-size: 12px;
	max-height: 200px;
	margin-bottom: 20px;
}

.dsic-lw-detail-section:last-child pre {
	margin-bottom: 0;
}

@media (max-width: 782px) {
	.dsic-lw-stats-overview {
		flex-direction: column;
	}

	.dsic-lw-filters form {
		flex-direction: column;
		align-items: stretch;
	}

	.dsic-lw-log-table-wrap {
		overflow-x: auto;
	}
}
</style>

<script>
(function() {
	'use strict';

	// View details modal
	var modal = document.getElementById('dsic-lw-details-modal');
	var requestData = document.getElementById('dsic-lw-request-data');
	var responseData = document.getElementById('dsic-lw-response-data');

	document.querySelectorAll('.dsic-lw-view-details').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var request = this.dataset.request;
			var response = this.dataset.response;

			try {
				requestData.textContent = request ? JSON.stringify(JSON.parse(request), null, 2) : 'No data';
			} catch (e) {
				requestData.textContent = request || 'No data';
			}

			try {
				responseData.textContent = response ? JSON.stringify(JSON.parse(response), null, 2) : 'No data';
			} catch (e) {
				responseData.textContent = response || 'No data';
			}

			modal.style.display = 'flex';
		});
	});

	// Close modal
	document.querySelector('.dsic-lw-modal-close').addEventListener('click', function() {
		modal.style.display = 'none';
	});

	modal.addEventListener('click', function(e) {
		if (e.target === modal) {
			modal.style.display = 'none';
		}
	});

	document.addEventListener('keydown', function(e) {
		if (e.key === 'Escape' && modal.style.display === 'flex') {
			modal.style.display = 'none';
		}
	});
})();
</script>
