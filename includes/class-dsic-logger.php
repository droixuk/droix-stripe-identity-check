<?php
/**
 * Logger class.
 *
 * Handles debug logging for the plugin.
 *
 * @package    DSIC
 * @subpackage DSIC/includes
 * @since      0.0.1
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Logger
 *
 * @since 0.0.1
 */
class DSIC_Logger {

	/**
	 * Log directory path.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	private static string $log_dir = '';

	/**
	 * Maximum log file age in days.
	 *
	 * @since 0.0.1
	 * @var int
	 */
	private static int $max_age_days = 30;

	/**
	 * Get the log directory path.
	 *
	 * @since 0.0.1
	 * @return string
	 */
	private static function get_log_dir(): string {
		if ( empty( self::$log_dir ) ) {
			self::$log_dir = WP_CONTENT_DIR . '/dsic-logs';
		}
		return self::$log_dir;
	}

	/**
	 * Get the current log file path.
	 *
	 * @since 0.0.1
	 * @return string
	 */
	private static function get_log_file(): string {
		return self::get_log_dir() . '/dsic-' . gmdate( 'Y-m-d' ) . '.log';
	}

	/**
	 * Check if debug logging is enabled.
	 *
	 * @since 0.0.1
	 * @return bool
	 */
	public static function is_debug_enabled(): bool {
		return (
			get_option( 'dsic_debug_mode', false ) ||
			( defined( 'WP_DEBUG' ) && WP_DEBUG )
		);
	}

	/**
	 * Write a log entry.
	 *
	 * Errors and warnings are ALWAYS logged regardless of debug mode.
	 * Info and debug messages only log when debug mode is enabled.
	 *
	 * @since 0.0.1
	 * @param string $message Log message.
	 * @param string $level   Log level (INFO, ERROR, DEBUG, WARNING).
	 * @return bool True on success, false on failure.
	 */
	public static function log( string $message, string $level = 'INFO' ): bool {
		$level = strtoupper( $level );

		// Always log errors and warnings, regardless of debug mode.
		$always_log_levels = array( 'ERROR', 'WARNING' );
		if ( ! in_array( $level, $always_log_levels, true ) && ! self::is_debug_enabled() ) {
			return false;
		}

		$log_dir = self::get_log_dir();

		// Ensure directory exists.
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		$timestamp = gmdate( 'Y-m-d H:i:s' );
		$entry     = sprintf( "[%s] [%s] %s\n", $timestamp, $level, self::redact_message( $message ) );

		$result = file_put_contents(
			self::get_log_file(),
			$entry,
			FILE_APPEND | LOCK_EX
		);

		return false !== $result;
	}

	/**
	 * Log an info message.
	 *
	 * @since 0.0.1
	 * @param string $message Log message.
	 * @return bool
	 */
	public static function info( string $message ): bool {
		return self::log( $message, 'INFO' );
	}

	/**
	 * Log an error message.
	 *
	 * @since 0.0.1
	 * @param string $message Log message.
	 * @return bool
	 */
	public static function error( string $message ): bool {
		return self::log( $message, 'ERROR' );
	}

	/**
	 * Log a debug message.
	 *
	 * @since 0.0.1
	 * @param string $message Log message.
	 * @return bool
	 */
	public static function debug( string $message ): bool {
		return self::log( $message, 'DEBUG' );
	}

	/**
	 * Log a warning message.
	 *
	 * @since 0.0.1
	 * @param string $message Log message.
	 * @return bool
	 */
	public static function warning( string $message ): bool {
		return self::log( $message, 'WARNING' );
	}

	/**
	 * Redact sensitive values before writing logs.
	 *
	 * @since 1.10.2
	 * @param string $message Log message.
	 * @return string
	 */
	private static function redact_message( string $message ): string {
		$patterns = array(
			'/sk_(live|test)_[A-Za-z0-9_]+/'                 => 'sk_$1_[redacted]',
			'/rk_(live|test)_[A-Za-z0-9_]+/'                 => 'rk_$1_[redacted]',
			'/whsec_[A-Za-z0-9_]+/'                          => 'whsec_[redacted]',
			'/dsic_[a-f0-9]{48}/i'                            => 'dsic_[redacted]',
			'/ghp_[A-Za-z0-9_]+/'                             => 'ghp_[redacted]',
			'/github_pat_[A-Za-z0-9_]+/'                      => 'github_pat_[redacted]',
			'#https://hooks\.slack\.com/services/[^\s"\']+#'  => 'https://hooks.slack.com/services/[redacted]',
			'/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i'        => '[email redacted]',
			'/(token|secret|password|authorization)(["\']?\s*[:=]\s*["\']?)[^,\s"\']+/i' => '$1$2[redacted]',
		);

		return preg_replace( array_keys( $patterns ), array_values( $patterns ), $message ) ?? $message;
	}

	/**
	 * Get recent log entries.
	 *
	 * @since 0.0.1
	 * @param int $lines Number of lines to retrieve.
	 * @return array Array of log entries.
	 */
	public static function get_recent_logs( int $lines = 100 ): array {
		$log_file = self::get_log_file();

		if ( ! file_exists( $log_file ) ) {
			return array();
		}

		$content = file_get_contents( $log_file );
		if ( false === $content ) {
			return array();
		}

		$all_lines = explode( "\n", trim( $content ) );
		$all_lines = array_filter( $all_lines );

		return array_slice( $all_lines, -$lines );
	}

	/**
	 * Get all log files.
	 *
	 * @since 0.0.1
	 * @return array Array of log file info.
	 */
	public static function get_log_files(): array {
		$log_dir = self::get_log_dir();
		$files   = array();

		if ( ! is_dir( $log_dir ) ) {
			return $files;
		}

		$pattern = $log_dir . '/dsic-*.log';
		$matches = glob( $pattern );

		if ( false === $matches ) {
			return $files;
		}

		foreach ( $matches as $file ) {
			$files[] = array(
				'name'     => basename( $file ),
				'path'     => $file,
				'size'     => filesize( $file ),
				'modified' => filemtime( $file ),
			);
		}

		// Sort by modified date, newest first.
		usort(
			$files,
			function ( $a, $b ) {
				return $b['modified'] - $a['modified'];
			}
		);

		return $files;
	}

	/**
	 * Cleanup old log files.
	 *
	 * @since 0.0.1
	 * @return int Number of files deleted.
	 */
	public static function cleanup_old_logs(): int {
		$log_dir  = self::get_log_dir();
		$deleted  = 0;
		$max_age  = self::$max_age_days * DAY_IN_SECONDS;
		$now      = time();

		if ( ! is_dir( $log_dir ) ) {
			return 0;
		}

		$files = glob( $log_dir . '/dsic-*.log' );

		if ( false === $files ) {
			return 0;
		}

		foreach ( $files as $file ) {
			$file_age = $now - filemtime( $file );

			if ( $file_age > $max_age ) {
				if ( unlink( $file ) ) {
					++$deleted;
				}
			}
		}

		if ( $deleted > 0 ) {
			self::info( sprintf( 'Cleaned up %d old log file(s).', $deleted ) );
		}

		return $deleted;
	}

	/**
	 * Clear all logs.
	 *
	 * @since 0.0.1
	 * @return bool
	 */
	public static function clear_all_logs(): bool {
		$log_dir = self::get_log_dir();

		if ( ! is_dir( $log_dir ) ) {
			return true;
		}

		$files = glob( $log_dir . '/dsic-*.log' );

		if ( false === $files ) {
			return true;
		}

		$success = true;
		foreach ( $files as $file ) {
			if ( ! unlink( $file ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Mask sensitive data for logging.
	 *
	 * @since 0.0.1
	 * @param string $value The value to mask.
	 * @param int    $visible_chars Number of characters to leave visible at end.
	 * @return string Masked value.
	 */
	public static function mask_sensitive( string $value, int $visible_chars = 4 ): string {
		$length = strlen( $value );

		if ( $length <= $visible_chars ) {
			return str_repeat( '*', $length );
		}

		$masked_length = $length - $visible_chars;
		return str_repeat( '*', $masked_length ) . substr( $value, -$visible_chars );
	}
}
