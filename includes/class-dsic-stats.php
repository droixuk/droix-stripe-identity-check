<?php
/**
 * Statistics tracking class.
 *
 * Handles verification statistics and reporting.
 *
 * @package    DSIC
 * @subpackage DSIC/includes
 * @since      0.3.1
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Stats
 *
 * @since 0.3.1
 */
class DSIC_Stats {

	/**
	 * Cache group name.
	 */
	const CACHE_GROUP = 'dsic_stats';

	/**
	 * Cache expiration in seconds (1 hour).
	 */
	const CACHE_EXPIRATION = HOUR_IN_SECONDS;

	/**
	 * Get all verification statistics.
	 *
	 * @since 0.3.1
	 * @param bool $force_refresh Force refresh from database.
	 * @return array Statistics data.
	 */
	public static function get_all_stats( bool $force_refresh = false ): array {
		$cache_key = 'all_stats';

		if ( ! $force_refresh ) {
			$cached = self::get_cached( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$stats = array(
			'totals'    => self::get_totals(),
			'by_status' => self::get_counts_by_status(),
			'by_period' => self::get_counts_by_period(),
			'recent'    => self::get_recent_verifications( 10 ),
			'rates'     => self::get_success_rates(),
		);

		self::set_cached( $cache_key, $stats );

		return $stats;
	}

	/**
	 * Get total verification counts.
	 *
	 * @since 0.3.1
	 * @return array Total counts.
	 */
	public static function get_totals(): array {
		global $wpdb;

		$cache_key = 'totals';
		$cached    = self::get_cached( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Get table name based on HPOS.
		$table = self::get_meta_table();

		$total_requested = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT {$table['id_column']}) FROM {$table['table']} WHERE {$table['key_column']} = %s",
				'_dsic_verification_status'
			)
		);

		$total_verified = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT {$table['id_column']}) FROM {$table['table']} WHERE {$table['key_column']} = %s AND {$table['value_column']} = %s",
				'_dsic_verification_status',
				'verified'
			)
		);

		$total_failed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT {$table['id_column']}) FROM {$table['table']} WHERE {$table['key_column']} = %s AND {$table['value_column']} = %s",
				'_dsic_verification_status',
				'failed'
			)
		);

		$total_pending = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT {$table['id_column']}) FROM {$table['table']} WHERE {$table['key_column']} = %s AND {$table['value_column']} = %s",
				'_dsic_verification_status',
				'pending'
			)
		);

		// Count orders where link was clicked.
		$total_clicked = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT {$table['id_column']}) FROM {$table['table']} WHERE {$table['key_column']} = %s",
				'_dsic_link_clicked'
			)
		);

		$totals = array(
			'requested'    => $total_requested,
			'link_clicked' => $total_clicked,
			'verified'     => $total_verified,
			'failed'       => $total_failed,
			'pending'      => $total_pending,
		);

		self::set_cached( $cache_key, $totals );

		return $totals;
	}

	/**
	 * Get counts grouped by status.
	 *
	 * @since 0.3.1
	 * @return array Status counts.
	 */
	public static function get_counts_by_status(): array {
		$totals = self::get_totals();

		return array(
			array(
				'status' => 'verified',
				'label'  => __( 'Verified', 'droix-stripe-id-check' ),
				'count'  => $totals['verified'],
				'color'  => '#00a32a',
			),
			array(
				'status' => 'failed',
				'label'  => __( 'Failed', 'droix-stripe-id-check' ),
				'count'  => $totals['failed'],
				'color'  => '#d63638',
			),
			array(
				'status' => 'pending',
				'label'  => __( 'Pending', 'droix-stripe-id-check' ),
				'count'  => $totals['pending'],
				'color'  => '#dba617',
			),
		);
	}

	/**
	 * Get counts by time period.
	 *
	 * @since 0.3.1
	 * @return array Period counts.
	 */
	public static function get_counts_by_period(): array {
		global $wpdb;

		$cache_key = 'by_period';
		$cached    = self::get_cached( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$table = self::get_meta_table();

		// Today.
		$today_start = strtotime( 'today midnight' );
		$today       = self::get_period_counts( $today_start );

		// This week.
		$week_start = strtotime( 'monday this week midnight' );
		$this_week  = self::get_period_counts( $week_start );

		// This month.
		$month_start = strtotime( 'first day of this month midnight' );
		$this_month  = self::get_period_counts( $month_start );

		// Last 30 days.
		$thirty_days_start = strtotime( '-30 days midnight' );
		$last_30_days      = self::get_period_counts( $thirty_days_start );

		$periods = array(
			'today'       => array(
				'label'    => __( 'Today', 'droix-stripe-id-check' ),
				'verified' => $today['verified'],
				'failed'   => $today['failed'],
				'pending'  => $today['pending'],
				'total'    => $today['total'],
			),
			'this_week'   => array(
				'label'    => __( 'This Week', 'droix-stripe-id-check' ),
				'verified' => $this_week['verified'],
				'failed'   => $this_week['failed'],
				'pending'  => $this_week['pending'],
				'total'    => $this_week['total'],
			),
			'this_month'  => array(
				'label'    => __( 'This Month', 'droix-stripe-id-check' ),
				'verified' => $this_month['verified'],
				'failed'   => $this_month['failed'],
				'pending'  => $this_month['pending'],
				'total'    => $this_month['total'],
			),
			'last_30_days' => array(
				'label'    => __( 'Last 30 Days', 'droix-stripe-id-check' ),
				'verified' => $last_30_days['verified'],
				'failed'   => $last_30_days['failed'],
				'pending'  => $last_30_days['pending'],
				'total'    => $last_30_days['total'],
			),
		);

		self::set_cached( $cache_key, $periods );

		return $periods;
	}

	/**
	 * Get counts for a specific time period.
	 *
	 * @since 0.3.1
	 * @param int $start_timestamp Start timestamp.
	 * @return array Period counts.
	 */
	private static function get_period_counts( int $start_timestamp ): array {
		global $wpdb;

		$table = self::get_meta_table();

		// Get order IDs with verification requested after start time.
		$order_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT {$table['id_column']} FROM {$table['table']}
				WHERE {$table['key_column']} = %s
				AND {$table['value_column']} >= %d",
				'_dsic_verification_requested',
				$start_timestamp
			)
		);

		if ( empty( $order_ids ) ) {
			return array(
				'verified' => 0,
				'failed'   => 0,
				'pending'  => 0,
				'total'    => 0,
			);
		}

		$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );

		$verified = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT {$table['id_column']}) FROM {$table['table']}
				WHERE {$table['id_column']} IN ($placeholders)
				AND {$table['key_column']} = %s
				AND {$table['value_column']} = %s",
				array_merge( $order_ids, array( '_dsic_verification_status', 'verified' ) )
			)
		);

		$failed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT {$table['id_column']}) FROM {$table['table']}
				WHERE {$table['id_column']} IN ($placeholders)
				AND {$table['key_column']} = %s
				AND {$table['value_column']} = %s",
				array_merge( $order_ids, array( '_dsic_verification_status', 'failed' ) )
			)
		);

		$pending = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT {$table['id_column']}) FROM {$table['table']}
				WHERE {$table['id_column']} IN ($placeholders)
				AND {$table['key_column']} = %s
				AND {$table['value_column']} = %s",
				array_merge( $order_ids, array( '_dsic_verification_status', 'pending' ) )
			)
		);

		return array(
			'verified' => $verified,
			'failed'   => $failed,
			'pending'  => $pending,
			'total'    => count( $order_ids ),
		);
	}

	/**
	 * Get recent verifications.
	 *
	 * @since 0.3.1
	 * @param int $limit Number of records to return.
	 * @return array Recent verifications.
	 */
	public static function get_recent_verifications( int $limit = 10 ): array {
		$orders = wc_get_orders(
			array(
				'meta_key'   => '_dsic_verification_status',
				'orderby'    => 'date',
				'order'      => 'DESC',
				'limit'      => $limit,
			)
		);

		$recent = array();

		foreach ( $orders as $order ) {
			$status           = $order->get_meta( '_dsic_verification_status' );
			$requested        = $order->get_meta( '_dsic_verification_requested' );
			$link_clicked     = $order->get_meta( '_dsic_link_clicked' );
			$completed        = $order->get_meta( '_dsic_verification_completed' );
			$session_id       = $order->get_meta( '_dsic_verification_session_id' );
			$error_msg        = $order->get_meta( '_dsic_verification_error_msg' );
			$redaction_status = $order->get_meta( '_dsic_data_redaction_status' );

			$recent[] = array(
				'order_id'         => $order->get_id(),
				'order_number'     => $order->get_order_number(),
				'customer'         => $order->get_formatted_billing_full_name(),
				'email'            => $order->get_billing_email(),
				'status'           => $status,
				'session_id'       => $session_id,
				'error_msg'        => $error_msg,
				'redaction_status' => $redaction_status,
				'requested'        => $requested ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $requested ) : '',
				'link_clicked'     => $link_clicked ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $link_clicked ) : '',
				'completed'        => $completed ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $completed ) : '',
				'edit_url'         => $order->get_edit_order_url(),
			);
		}

		return $recent;
	}

	/**
	 * Get success rates.
	 *
	 * @since 0.3.1
	 * @return array Success rates.
	 */
	public static function get_success_rates(): array {
		$totals = self::get_totals();

		$completed = $totals['verified'] + $totals['failed'];
		$success_rate = $completed > 0 ? round( ( $totals['verified'] / $completed ) * 100, 1 ) : 0;
		$failure_rate = $completed > 0 ? round( ( $totals['failed'] / $completed ) * 100, 1 ) : 0;

		$total_with_pending = $totals['verified'] + $totals['failed'] + $totals['pending'];
		$completion_rate = $total_with_pending > 0 ? round( ( $completed / $total_with_pending ) * 100, 1 ) : 0;

		return array(
			'success_rate'    => $success_rate,
			'failure_rate'    => $failure_rate,
			'completion_rate' => $completion_rate,
			'completed'       => $completed,
			'total'           => $total_with_pending,
		);
	}

	/**
	 * Get average verification time in seconds.
	 *
	 * @since 0.3.1
	 * @return int|null Average time in seconds or null if no data.
	 */
	public static function get_average_verification_time(): ?int {
		$orders = wc_get_orders(
			array(
				'meta_key'   => '_dsic_verification_completed',
				'limit'      => 100,
			)
		);

		if ( empty( $orders ) ) {
			return null;
		}

		$total_time = 0;
		$count      = 0;

		foreach ( $orders as $order ) {
			$requested = (int) $order->get_meta( '_dsic_verification_requested' );
			$completed = (int) $order->get_meta( '_dsic_verification_completed' );

			if ( $requested && $completed && $completed > $requested ) {
				$total_time += ( $completed - $requested );
				$count++;
			}
		}

		return $count > 0 ? (int) round( $total_time / $count ) : null;
	}

	/**
	 * Format seconds as human-readable duration.
	 *
	 * @since 0.3.1
	 * @param int $seconds Seconds to format.
	 * @return string Formatted duration.
	 */
	public static function format_duration( int $seconds ): string {
		if ( $seconds < 60 ) {
			return sprintf(
				/* translators: %d: Number of seconds */
				_n( '%d second', '%d seconds', $seconds, 'droix-stripe-id-check' ),
				$seconds
			);
		}

		if ( $seconds < 3600 ) {
			$minutes = round( $seconds / 60 );
			return sprintf(
				/* translators: %d: Number of minutes */
				_n( '%d minute', '%d minutes', $minutes, 'droix-stripe-id-check' ),
				$minutes
			);
		}

		if ( $seconds < 86400 ) {
			$hours = round( $seconds / 3600, 1 );
			return sprintf(
				/* translators: %s: Number of hours */
				__( '%s hours', 'droix-stripe-id-check' ),
				number_format_i18n( $hours, 1 )
			);
		}

		$days = round( $seconds / 86400, 1 );
		return sprintf(
			/* translators: %s: Number of days */
			__( '%s days', 'droix-stripe-id-check' ),
			number_format_i18n( $days, 1 )
		);
	}

	/**
	 * Get meta table information based on HPOS status.
	 *
	 * @since 0.3.1
	 * @return array Table information.
	 */
	private static function get_meta_table(): array {
		global $wpdb;

		// Check if HPOS is enabled.
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return array(
				'table'        => $wpdb->prefix . 'wc_orders_meta',
				'id_column'    => 'order_id',
				'key_column'   => 'meta_key',
				'value_column' => 'meta_value',
			);
		}

		return array(
			'table'        => $wpdb->postmeta,
			'id_column'    => 'post_id',
			'key_column'   => 'meta_key',
			'value_column' => 'meta_value',
		);
	}

	/**
	 * Get cached data.
	 *
	 * @since 0.3.1
	 * @param string $key Cache key.
	 * @return mixed Cached data or false.
	 */
	private static function get_cached( string $key ) {
		return wp_cache_get( $key, self::CACHE_GROUP );
	}

	/**
	 * Set cached data.
	 *
	 * @since 0.3.1
	 * @param string $key  Cache key.
	 * @param mixed  $data Data to cache.
	 * @return bool Success.
	 */
	private static function set_cached( string $key, $data ): bool {
		return wp_cache_set( $key, $data, self::CACHE_GROUP, self::CACHE_EXPIRATION );
	}

	/**
	 * Clear all stats cache.
	 *
	 * @since 0.3.1
	 * @return void
	 */
	public static function clear_cache(): void {
		wp_cache_delete( 'all_stats', self::CACHE_GROUP );
		wp_cache_delete( 'totals', self::CACHE_GROUP );
		wp_cache_delete( 'by_period', self::CACHE_GROUP );

		// Also clear transient if using that as fallback.
		delete_transient( 'dsic_stats_cache' );
	}

	/**
	 * Hook to clear cache when verification status changes.
	 *
	 * @since 0.3.1
	 * @return void
	 */
	public static function init_cache_clearing(): void {
		add_action( 'dsic_verification_passed', array( __CLASS__, 'clear_cache' ) );
		add_action( 'dsic_verification_failed', array( __CLASS__, 'clear_cache' ) );
		add_action( 'dsic_verification_requested', array( __CLASS__, 'clear_cache' ) );
	}
}
