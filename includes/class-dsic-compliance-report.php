<?php
/**
 * Compliance report class.
 *
 * Handles GDPR compliance reporting and audit logs.
 *
 * @package    DSIC
 * @subpackage DSIC/includes
 * @since      1.7.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Compliance_Report
 *
 * @since 1.7.0
 */
class DSIC_Compliance_Report {

	/**
	 * Create compliance log table.
	 *
	 * @since 1.7.0
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'dsic_compliance_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id bigint(20) UNSIGNED NOT NULL,
			action varchar(50) NOT NULL,
			status varchar(20) NOT NULL,
			details longtext,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY order_id (order_id),
			KEY action (action),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'dsic_compliance_log_table_version', '1.0' );

		// Log if logger is available (may not be loaded during activation).
		if ( class_exists( 'DSIC_Logger' ) ) {
			DSIC_Logger::info( 'Compliance log table created/updated successfully' );
		}
	}

	/**
	 * Get compliance statistics.
	 *
	 * @since 1.7.0
	 * @param string|null $start_date Start date (Y-m-d format).
	 * @param string|null $end_date   End date (Y-m-d format).
	 * @return array Statistics array.
	 */
	public static function get_stats( string $start_date = null, string $end_date = null ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'dsic_compliance_log';

		$where = "WHERE action = 'data_redaction'";

		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND created_at >= %s', $start_date . ' 00:00:00' );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND created_at <= %s', $end_date . ' 23:59:59' );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$stats = array(
			'total'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name $where" ),
			'completed' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name $where AND status = 'completed'" ),
			'failed'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name $where AND status = 'failed'" ),
			'pending'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name $where AND status = 'pending'" ),
		);
		// phpcs:enable

		return $stats;
	}

	/**
	 * Get compliance log entries.
	 *
	 * @since 1.7.0
	 * @param int   $limit   Number of entries to retrieve.
	 * @param int   $offset  Offset for pagination.
	 * @param array $filters Filter criteria.
	 * @return array Log entries.
	 */
	public static function get_log( int $limit = 100, int $offset = 0, array $filters = array() ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'dsic_compliance_log';

		$where = "WHERE action = 'data_redaction'";

		if ( ! empty( $filters['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $filters['status'] );
		}

		if ( ! empty( $filters['start_date'] ) ) {
			$where .= $wpdb->prepare( ' AND created_at >= %s', $filters['start_date'] . ' 00:00:00' );
		}

		if ( ! empty( $filters['end_date'] ) ) {
			$where .= $wpdb->prepare( ' AND created_at <= %s', $filters['end_date'] . ' 23:59:59' );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare( "SELECT * FROM $table_name $where ORDER BY created_at DESC LIMIT %d OFFSET %d", $limit, $offset );
		// phpcs:enable

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get total count of log entries (for pagination).
	 *
	 * @since 1.7.0
	 * @param array $filters Filter criteria.
	 * @return int Total count.
	 */
	public static function get_log_count( array $filters = array() ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'dsic_compliance_log';

		$where = "WHERE action = 'data_redaction'";

		if ( ! empty( $filters['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $filters['status'] );
		}

		if ( ! empty( $filters['start_date'] ) ) {
			$where .= $wpdb->prepare( ' AND created_at >= %s', $filters['start_date'] . ' 00:00:00' );
		}

		if ( ! empty( $filters['end_date'] ) ) {
			$where .= $wpdb->prepare( ' AND created_at <= %s', $filters['end_date'] . ' 23:59:59' );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name $where" );
		// phpcs:enable
	}

	/**
	 * Export compliance log to CSV.
	 *
	 * @since 1.7.0
	 * @param array $filters Filter criteria.
	 * @return void
	 */
	public static function export_csv( array $filters = array() ): void {
		$logs = self::get_log( 10000, 0, $filters );

		// Set headers for CSV download.
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="compliance-audit-log-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// Add BOM for proper UTF-8 encoding in Excel.
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Headers.
		fputcsv( $output, array( 'Date', 'Order ID', 'Action', 'Status', 'Details' ) );

		// Data.
		foreach ( $logs as $log ) {
			fputcsv(
				$output,
				array(
					$log['created_at'],
					$log['order_id'],
					$log['action'],
					$log['status'],
					$log['details'],
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Handle CSV export request.
	 *
	 * @since 1.7.0
	 * @return void
	 */
	public static function handle_export_request(): void {
		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dsic_export_compliance' ) ) {
			wp_die( esc_html__( 'Security check failed', 'droix-stripe-id-check' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to export compliance data', 'droix-stripe-id-check' ) );
		}

		// Get filters.
		$filters = array();
		if ( ! empty( $_GET['start_date'] ) ) {
			$filters['start_date'] = sanitize_text_field( wp_unslash( $_GET['start_date'] ) );
		}
		if ( ! empty( $_GET['end_date'] ) ) {
			$filters['end_date'] = sanitize_text_field( wp_unslash( $_GET['end_date'] ) );
		}

		// Export.
		self::export_csv( $filters );
	}
}
