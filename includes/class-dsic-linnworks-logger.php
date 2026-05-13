<?php
/**
 * Linnworks Logger Class.
 *
 * Handles database logging for Linnworks API operations.
 *
 * @package    DSIC
 * @subpackage DSIC/includes
 * @since      1.5.0
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Linnworks_Logger
 *
 * Manages the Linnworks operation log database table.
 *
 * @since 1.5.0
 */
class DSIC_Linnworks_Logger {

	/**
	 * Table name (without prefix).
	 *
	 * @since 1.5.0
	 * @var string
	 */
	const TABLE_NAME = 'dsic_linnworks_log';

	/**
	 * Get the full table name with prefix.
	 *
	 * @since 1.5.0
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create the database table.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			wc_order_id bigint(20) unsigned NOT NULL,
			lw_order_id varchar(50) DEFAULT NULL,
			action varchar(20) NOT NULL,
			trigger_source varchar(20) NOT NULL,
			status varchar(20) NOT NULL,
			error_message text DEFAULT NULL,
			response_time_ms int(11) DEFAULT NULL,
			request_data longtext DEFAULT NULL,
			response_data longtext DEFAULT NULL,
			PRIMARY KEY (id),
			KEY wc_order_id (wc_order_id),
			KEY action (action),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Store table version for future upgrades.
		update_option( 'dsic_linnworks_log_table_version', '1.0.0' );
	}

	/**
	 * Drop the database table.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

		delete_option( 'dsic_linnworks_log_table_version' );
	}

	/**
	 * Log an operation.
	 *
	 * @since 1.5.0
	 * @param array $data Log data.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public static function log( array $data ) {
		global $wpdb;

		$defaults = array(
			'created_at'       => current_time( 'mysql' ),
			'wc_order_id'      => 0,
			'lw_order_id'      => null,
			'action'           => '',
			'trigger_source'   => 'manual',
			'status'           => 'pending',
			'error_message'    => null,
			'response_time_ms' => null,
			'request_data'     => null,
			'response_data'    => null,
		);

		$data = wp_parse_args( $data, $defaults );

		// Serialize arrays/objects.
		if ( is_array( $data['request_data'] ) || is_object( $data['request_data'] ) ) {
			$data['request_data'] = wp_json_encode( $data['request_data'] );
		}
		if ( is_array( $data['response_data'] ) || is_object( $data['response_data'] ) ) {
			$data['response_data'] = wp_json_encode( $data['response_data'] );
		}

		$result = $wpdb->insert(
			self::get_table_name(),
			$data,
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			DSIC_Logger::error( 'Failed to log Linnworks operation: ' . $wpdb->last_error );
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update a log entry.
	 *
	 * @since 1.5.0
	 * @param int   $log_id Log entry ID.
	 * @param array $data   Data to update.
	 * @return bool
	 */
	public static function update( int $log_id, array $data ): bool {
		global $wpdb;

		// Serialize arrays/objects.
		if ( isset( $data['request_data'] ) && ( is_array( $data['request_data'] ) || is_object( $data['request_data'] ) ) ) {
			$data['request_data'] = wp_json_encode( $data['request_data'] );
		}
		if ( isset( $data['response_data'] ) && ( is_array( $data['response_data'] ) || is_object( $data['response_data'] ) ) ) {
			$data['response_data'] = wp_json_encode( $data['response_data'] );
		}

		$result = $wpdb->update(
			self::get_table_name(),
			$data,
			array( 'id' => $log_id ),
			null,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get log entries with pagination.
	 *
	 * @since 1.5.0
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function get_logs( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'per_page'    => 20,
			'page'        => 1,
			'orderby'     => 'created_at',
			'order'       => 'DESC',
			'status'      => '',
			'action'      => '',
			'wc_order_id' => 0,
			'date_from'   => '',
			'date_to'     => '',
		);

		$args       = wp_parse_args( $args, $defaults );
		$table_name = self::get_table_name();
		$where      = array( '1=1' );
		$values     = array();

		// Filter by status.
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		// Filter by action.
		if ( ! empty( $args['action'] ) ) {
			$where[]  = 'action = %s';
			$values[] = $args['action'];
		}

		// Filter by WC order ID.
		if ( ! empty( $args['wc_order_id'] ) ) {
			$where[]  = 'wc_order_id = %d';
			$values[] = $args['wc_order_id'];
		}

		// Filter by date range.
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = $args['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = $args['date_to'] . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );

		// Sanitize orderby.
		$allowed_orderby = array( 'id', 'created_at', 'wc_order_id', 'action', 'status', 'response_time_ms' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		// Calculate offset.
		$offset   = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );
		$per_page = absint( $args['per_page'] );

		// Get total count.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$count_sql = $wpdb->prepare( $count_sql, $values );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( $count_sql );

		// Get results.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		$values[] = $per_page;
		$values[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );

		return array(
			'items'       => $results ? $results : array(),
			'total'       => $total,
			'total_pages' => ceil( $total / $per_page ),
			'page'        => absint( $args['page'] ),
			'per_page'    => $per_page,
		);
	}

	/**
	 * Get statistics.
	 *
	 * @since 1.5.0
	 * @param string $period Period: 'today', 'week', 'month', 'all'.
	 * @return array
	 */
	public static function get_stats( string $period = 'month' ): array {
		global $wpdb;

		$table_name = self::get_table_name();

		// Determine date filter.
		$date_filter = '';
		switch ( $period ) {
			case 'today':
				$date_filter = $wpdb->prepare( ' AND created_at >= %s', gmdate( 'Y-m-d 00:00:00' ) );
				break;
			case 'week':
				$date_filter = $wpdb->prepare( ' AND created_at >= %s', gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days' ) ) );
				break;
			case 'month':
				$date_filter = $wpdb->prepare( ' AND created_at >= %s', gmdate( 'Y-m-d 00:00:00', strtotime( '-30 days' ) ) );
				break;
			case 'all':
			default:
				$date_filter = '';
				break;
		}

		// Total operations.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE 1=1 {$date_filter}" );

		// Success count.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$success = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'success' {$date_filter}" );

		// Failed count.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$failed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'failed' {$date_filter}" );

		// Lock operations.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$locks = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE action = 'lock' {$date_filter}" );

		// Unlock operations.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$unlocks = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE action = 'unlock' {$date_filter}" );

		// Average response time.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$avg_response = (float) $wpdb->get_var( "SELECT AVG(response_time_ms) FROM {$table_name} WHERE response_time_ms IS NOT NULL {$date_filter}" );

		// Calculate success rate.
		$success_rate = $total > 0 ? round( ( $success / $total ) * 100, 1 ) : 0;

		return array(
			'total'            => $total,
			'success'          => $success,
			'failed'           => $failed,
			'locks'            => $locks,
			'unlocks'          => $unlocks,
			'success_rate'     => $success_rate,
			'avg_response_ms'  => round( $avg_response, 0 ),
			'period'           => $period,
		);
	}

	/**
	 * Get recent failed operations.
	 *
	 * @since 1.5.0
	 * @param int $limit Number of results.
	 * @return array
	 */
	public static function get_recent_failures( int $limit = 10 ): array {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE status = 'failed' ORDER BY created_at DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Get log entry by ID.
	 *
	 * @since 1.5.0
	 * @param int $log_id Log entry ID.
	 * @return array|null
	 */
	public static function get_log( int $log_id ): ?array {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $log_id ),
			ARRAY_A
		);

		return $result ? $result : null;
	}

	/**
	 * Delete old log entries.
	 *
	 * @since 1.5.0
	 * @param int $days_old Delete entries older than this many days.
	 * @return int Number of deleted rows.
	 */
	public static function cleanup( int $days_old = 90 ): int {
		global $wpdb;

		$table_name = self::get_table_name();
		$cutoff     = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_old} days" ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table_name} WHERE created_at < %s", $cutoff )
		);

		return $deleted ? $deleted : 0;
	}

	/**
	 * Check if table exists.
	 *
	 * @since 1.5.0
	 * @return bool
	 */
	public static function table_exists(): bool {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );

		return $result === $table_name;
	}
}
