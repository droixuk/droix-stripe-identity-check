<?php
/**
 * Settings page template.
 *
 * @package    DSIC
 * @subpackage DSIC/admin/partials
 * @since      0.0.1
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

// Get current tab.
$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
$dsic_generated_linnworks_api_key = '';

// Handle form submission - only update settings for the current tab to prevent cross-tab reset.
if ( isset( $_POST['dsic_save_settings'] ) && check_admin_referer( 'dsic_settings_nonce', 'dsic_nonce' ) ) {
	// Get the tab being saved from hidden field (more reliable than GET param).
	$saving_tab = isset( $_POST['dsic_current_tab'] ) ? sanitize_key( $_POST['dsic_current_tab'] ) : $current_tab;

	// General tab settings.
	if ( 'general' === $saving_tab ) {
		update_option( 'dsic_enabled', isset( $_POST['dsic_enabled'] ) ? '1' : '0' );
		update_option( 'dsic_crm_email', sanitize_email( wp_unslash( $_POST['dsic_crm_email'] ?? '' ) ) );
		update_option( 'dsic_delete_data_on_uninstall', isset( $_POST['dsic_delete_data_on_uninstall'] ) ? '1' : '0' );

		// Verification options.
		update_option( 'dsic_require_selfie', isset( $_POST['dsic_require_selfie'] ) ? '1' : '0' );
		update_option( 'dsic_require_id_number', isset( $_POST['dsic_require_id_number'] ) ? '1' : '0' );
		update_option( 'dsic_require_live_capture', isset( $_POST['dsic_require_live_capture'] ) ? '1' : '0' );
		update_option( 'dsic_prefill_phone', isset( $_POST['dsic_prefill_phone'] ) ? '1' : '0' );

		// Session expiry (convert days to seconds for Stripe API).
		if ( isset( $_POST['dsic_session_expiry_days'] ) ) {
			$days = absint( $_POST['dsic_session_expiry_days'] );
			// Clamp between 1 and 90 days.
			$days = max( 1, min( 90, $days ) );
			update_option( 'dsic_session_expiry_days', (string) $days );
		}

		// Document types - save as array.
		$doc_types = array();
		if ( isset( $_POST['dsic_doc_driving_license'] ) ) {
			$doc_types[] = 'driving_license';
		}
		if ( isset( $_POST['dsic_doc_id_card'] ) ) {
			$doc_types[] = 'id_card';
		}
		if ( isset( $_POST['dsic_doc_passport'] ) ) {
			$doc_types[] = 'passport';
		}
		// Ensure at least one document type is selected.
		if ( empty( $doc_types ) ) {
			$doc_types = array( 'driving_license', 'id_card', 'passport' );
		}
		update_option( 'dsic_allowed_document_types', $doc_types );

		// Auto-verification settings.
		update_option( 'dsic_auto_verify_different_address', isset( $_POST['dsic_auto_verify_different_address'] ) ? '1' : '0' );
		if ( isset( $_POST['dsic_auto_verify_checkout_message'] ) ) {
			update_option( 'dsic_auto_verify_checkout_message', wp_kses_post( wp_unslash( $_POST['dsic_auto_verify_checkout_message'] ) ) );
		}
		if ( isset( $_POST['dsic_auto_verify_thankyou_message'] ) ) {
			update_option( 'dsic_auto_verify_thankyou_message', wp_kses_post( wp_unslash( $_POST['dsic_auto_verify_thankyou_message'] ) ) );
		}

		// Radar fraud detection settings.
		update_option( 'dsic_radar_check_enabled', isset( $_POST['dsic_radar_check_enabled'] ) ? '1' : '0' );
		if ( isset( $_POST['dsic_radar_check_mode'] ) ) {
			$mode = sanitize_text_field( wp_unslash( $_POST['dsic_radar_check_mode'] ) );
			if ( in_array( $mode, array( 'risk_level', 'risk_score' ), true ) ) {
				update_option( 'dsic_radar_check_mode', $mode );
			}
		}
		if ( isset( $_POST['dsic_radar_risk_level_threshold'] ) ) {
			$level = sanitize_text_field( wp_unslash( $_POST['dsic_radar_risk_level_threshold'] ) );
			if ( in_array( $level, array( 'elevated', 'highest' ), true ) ) {
				update_option( 'dsic_radar_risk_level_threshold', $level );
			}
		}
		if ( isset( $_POST['dsic_radar_risk_score_threshold'] ) ) {
			$score = absint( $_POST['dsic_radar_risk_score_threshold'] );
			$score = max( 0, min( 99, $score ) );
			update_option( 'dsic_radar_risk_score_threshold', (string) $score );
		}
		update_option( 'dsic_radar_early_warnings_enabled', isset( $_POST['dsic_radar_early_warnings_enabled'] ) ? '1' : '0' );
	if ( isset( $_POST['dsic_radar_minimum_order_amount'] ) ) {
		$min_amount = floatval( $_POST['dsic_radar_minimum_order_amount'] );
		update_option( 'dsic_radar_minimum_order_amount', (string) max( 0, $min_amount ) );
	}
	if ( isset( $_POST['dsic_radar_thankyou_message'] ) ) {
		update_option( 'dsic_radar_thankyou_message', wp_kses_post( wp_unslash( $_POST['dsic_radar_thankyou_message'] ) ) );
	}

	// Amount threshold settings.
	update_option( 'dsic_amount_threshold_enabled', isset( $_POST['dsic_amount_threshold_enabled'] ) ? '1' : '0' );
	if ( isset( $_POST['dsic_amount_threshold_value'] ) ) {
		$threshold_value = floatval( $_POST['dsic_amount_threshold_value'] );
		update_option( 'dsic_amount_threshold_value', (string) max( 0, $threshold_value ) );
	}
	if ( isset( $_POST['dsic_amount_threshold_currency'] ) ) {
		update_option( 'dsic_amount_threshold_currency', sanitize_text_field( wp_unslash( $_POST['dsic_amount_threshold_currency'] ) ) );
	}
	}

	// API tab settings.
	if ( 'api' === $saving_tab ) {
		// Test mode toggle.
		update_option( 'dsic_test_mode', isset( $_POST['dsic_test_mode'] ) ? '1' : '0' );

		// Live API keys.
		if ( isset( $_POST['dsic_live_publishable_key'] ) ) {
			update_option( 'dsic_live_publishable_key', sanitize_text_field( wp_unslash( $_POST['dsic_live_publishable_key'] ) ) );
		}
		if ( isset( $_POST['dsic_live_secret_key'] ) ) {
			$secret_key = sanitize_text_field( wp_unslash( $_POST['dsic_live_secret_key'] ) );
			if ( ! empty( $secret_key ) ) {
				update_option( 'dsic_live_secret_key', $secret_key );
			}
		}
		if ( isset( $_POST['dsic_live_webhook_secret'] ) ) {
			$webhook_secret = sanitize_text_field( wp_unslash( $_POST['dsic_live_webhook_secret'] ) );
			if ( ! empty( $webhook_secret ) ) {
				update_option( 'dsic_live_webhook_secret', $webhook_secret );
			}
		}

		// Test API keys.
		if ( isset( $_POST['dsic_test_publishable_key'] ) ) {
			update_option( 'dsic_test_publishable_key', sanitize_text_field( wp_unslash( $_POST['dsic_test_publishable_key'] ) ) );
		}
		if ( isset( $_POST['dsic_test_secret_key'] ) ) {
			$secret_key = sanitize_text_field( wp_unslash( $_POST['dsic_test_secret_key'] ) );
			if ( ! empty( $secret_key ) ) {
				update_option( 'dsic_test_secret_key', $secret_key );
			}
		}
		if ( isset( $_POST['dsic_test_webhook_secret'] ) ) {
			$webhook_secret = sanitize_text_field( wp_unslash( $_POST['dsic_test_webhook_secret'] ) );
			if ( ! empty( $webhook_secret ) ) {
				update_option( 'dsic_test_webhook_secret', $webhook_secret );
			}
		}

		// Radar API keys.
		if ( isset( $_POST['dsic_radar_live_secret_key'] ) ) {
			$key = sanitize_text_field( wp_unslash( $_POST['dsic_radar_live_secret_key'] ) );
			if ( ! empty( $key ) ) {
				update_option( 'dsic_radar_live_secret_key', $key );
			}
		}
		if ( isset( $_POST['dsic_radar_test_secret_key'] ) ) {
			$key = sanitize_text_field( wp_unslash( $_POST['dsic_radar_test_secret_key'] ) );
			if ( ! empty( $key ) ) {
				update_option( 'dsic_radar_test_secret_key', $key );
			}
		}
		if ( isset( $_POST['dsic_radar_live_webhook_secret'] ) ) {
			$webhook_secret = sanitize_text_field( wp_unslash( $_POST['dsic_radar_live_webhook_secret'] ) );
			if ( ! empty( $webhook_secret ) ) {
				update_option( 'dsic_radar_live_webhook_secret', $webhook_secret );
			}
		}
		if ( isset( $_POST['dsic_radar_test_webhook_secret'] ) ) {
			$webhook_secret = sanitize_text_field( wp_unslash( $_POST['dsic_radar_test_webhook_secret'] ) );
			if ( ! empty( $webhook_secret ) ) {
				update_option( 'dsic_radar_test_webhook_secret', $webhook_secret );
			}
		}
	}

	// Email templates tab settings.
	if ( 'emails' === $saving_tab ) {
		$email_types = array( 'verification_request', 'verification_passed', 'verification_failed', 'data_redaction' );
		foreach ( $email_types as $type ) {
			update_option( 'dsic_email_' . $type . '_enabled', isset( $_POST[ 'dsic_email_' . $type . '_enabled' ] ) ? '1' : '0' );
			if ( isset( $_POST[ 'dsic_email_' . $type . '_subject' ] ) ) {
				update_option( 'dsic_email_' . $type . '_subject', sanitize_text_field( wp_unslash( $_POST[ 'dsic_email_' . $type . '_subject' ] ) ) );
			}
			if ( isset( $_POST[ 'dsic_email_' . $type . '_heading' ] ) ) {
				update_option( 'dsic_email_' . $type . '_heading', sanitize_text_field( wp_unslash( $_POST[ 'dsic_email_' . $type . '_heading' ] ) ) );
			}
			if ( isset( $_POST[ 'dsic_email_' . $type . '_body' ] ) ) {
				update_option( 'dsic_email_' . $type . '_body', wp_kses_post( wp_unslash( $_POST[ 'dsic_email_' . $type . '_body' ] ) ) );
			}
		}
	}

	// Data retention tab settings (v1.7.0+).
	if ( 'data_retention' === $saving_tab ) {
		update_option( 'dsic_auto_redaction_enabled', isset( $_POST['dsic_auto_redaction_enabled'] ) ? '1' : '0' );
		update_option( 'dsic_redaction_notify_customer', isset( $_POST['dsic_redaction_notify_customer'] ) ? '1' : '0' );

		// Retention period (30-365 days).
		if ( isset( $_POST['dsic_redaction_days'] ) ) {
			$days = absint( $_POST['dsic_redaction_days'] );
			$days = max( 30, min( 365, $days ) );
			update_option( 'dsic_redaction_days', (string) $days );
		}

		// Batch size (10-50 orders).
		if ( isset( $_POST['dsic_redaction_batch_size'] ) ) {
			$batch = absint( $_POST['dsic_redaction_batch_size'] );
			$batch = max( 10, min( 50, $batch ) );
			update_option( 'dsic_redaction_batch_size', (string) $batch );
		}

		// Schedule time (HH:MM format).
		if ( isset( $_POST['dsic_redaction_schedule_time'] ) ) {
			$time = sanitize_text_field( wp_unslash( $_POST['dsic_redaction_schedule_time'] ) );
			// Validate time format (HH:MM).
			if ( preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time ) ) {
				update_option( 'dsic_redaction_schedule_time', $time );

				// Reschedule the daily check with new time.
				if ( class_exists( 'DSIC_Auto_Redaction' ) ) {
					DSIC_Auto_Redaction::schedule_daily_check();
				}
			}
		}
	}

	// Debug tab settings.
	if ( 'debug' === $saving_tab ) {
		update_option( 'dsic_debug_mode', isset( $_POST['dsic_debug_mode'] ) ? '1' : '0' );
	}

	// Linnworks tab settings.
	if ( 'linnworks' === $saving_tab ) {
		update_option( 'dsic_linnworks_enabled', isset( $_POST['dsic_linnworks_enabled'] ) ? '1' : '0' );
		update_option( 'dsic_linnworks_auto_lock', isset( $_POST['dsic_linnworks_auto_lock'] ) ? '1' : '0' );
		update_option( 'dsic_linnworks_auto_unlock', isset( $_POST['dsic_linnworks_auto_unlock'] ) ? '1' : '0' );

		// Linnworks API credentials.
		if ( isset( $_POST['dsic_linnworks_app_id'] ) ) {
			update_option( 'dsic_linnworks_app_id', sanitize_text_field( wp_unslash( $_POST['dsic_linnworks_app_id'] ) ) );
		}
		if ( isset( $_POST['dsic_linnworks_app_secret'] ) ) {
			$app_secret = sanitize_text_field( wp_unslash( $_POST['dsic_linnworks_app_secret'] ) );
			if ( ! empty( $app_secret ) ) {
				update_option( 'dsic_linnworks_app_secret', $app_secret );
			}
		}
		if ( isset( $_POST['dsic_linnworks_token'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_POST['dsic_linnworks_token'] ) );
			if ( ! empty( $token ) ) {
				update_option( 'dsic_linnworks_token', $token );
			}
		}

		// External API key - generate new if requested.
		if ( isset( $_POST['dsic_linnworks_generate_api_key'] ) ) {
			$dsic_generated_linnworks_api_key = DSIC_REST_API::generate_api_key();
			update_option( 'dsic_linnworks_api_key_hash', DSIC_REST_API::hash_api_key( $dsic_generated_linnworks_api_key ) );
			delete_option( 'dsic_linnworks_api_key' );
		}
	}

	// Slack notification settings.
	if ( 'slack' === $saving_tab ) {
		if ( isset( $_POST['dsic_slack_webhook_url'] ) ) {
			$webhook_url = esc_url_raw( wp_unslash( $_POST['dsic_slack_webhook_url'] ) );
			if ( ! empty( $webhook_url ) ) {
				update_option( 'dsic_slack_webhook_url', $webhook_url );
			}
		}
		update_option( 'dsic_slack_notify_triggered', isset( $_POST['dsic_slack_notify_triggered'] ) ? '1' : '0' );
		update_option( 'dsic_slack_notify_passed', isset( $_POST['dsic_slack_notify_passed'] ) ? '1' : '0' );
		update_option( 'dsic_slack_notify_failed', isset( $_POST['dsic_slack_notify_failed'] ) ? '1' : '0' );
		update_option( 'dsic_slack_notify_efw', isset( $_POST['dsic_slack_notify_efw'] ) ? '1' : '0' );
	}

	// Refresh settings object after save.
	$settings_obj = new DSIC_Settings();
	$settings     = $settings_obj->get_settings();

	add_settings_error( 'dsic_settings', 'settings_saved', __( 'Settings saved successfully.', 'droix-stripe-id-check' ), 'success' );
}

// Define tabs.
$tabs = array(
	'dashboard'      => __( 'Dashboard', 'droix-stripe-id-check' ),
	'general'        => __( 'General', 'droix-stripe-id-check' ),
	'api'            => __( 'API Settings', 'droix-stripe-id-check' ),
	'emails'         => __( 'Email Templates', 'droix-stripe-id-check' ),
	'data_retention' => __( 'Data Retention', 'droix-stripe-id-check' ),
	'linnworks'      => __( 'Linnworks', 'droix-stripe-id-check' ),
	'slack'          => __( 'Slack', 'droix-stripe-id-check' ),
	'debug'          => __( 'Debug', 'droix-stripe-id-check' ),
);

// Get settings instance.
$settings_obj = new DSIC_Settings();

// Ensure email templates have default values (populates empty templates).
$settings_obj->ensure_email_template_defaults();

$settings = $settings_obj->get_settings();

// Get webhook URL for display.
$webhook_url = rest_url( 'dsic/v1/webhook' );
?>

<div class="wrap dsic-settings-wrap">
	<h1><?php esc_html_e( 'Stripe ID Check Settings', 'droix-stripe-id-check' ); ?></h1>

	<?php settings_errors( 'dsic_settings' ); ?>

	<nav class="nav-tab-wrapper dsic-tabs">
		<?php foreach ( $tabs as $tab_id => $tab_name ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsic-settings&tab=' . $tab_id ) ); ?>"
			   class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_name ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="dsic-settings-content">
		<form method="post" action="" id="dsic-settings-form">
			<?php wp_nonce_field( 'dsic_settings_nonce', 'dsic_nonce' ); ?>
			<input type="hidden" name="dsic_current_tab" value="<?php echo esc_attr( $current_tab ); ?>" />

			<?php if ( 'dashboard' === $current_tab ) : ?>
				<!-- Dashboard Tab -->
				<?php
				$stats        = DSIC_Stats::get_all_stats();
				$totals       = $stats['totals'];
				$rates        = $stats['rates'];
				$periods      = $stats['by_period'];
				$avg_time     = DSIC_Stats::get_average_verification_time();
				$all_checks   = DSIC_Stats::get_recent_verifications( 100 );
				$test_mode    = get_option( 'dsic_test_mode', true );
				$stripe_base  = $test_mode ? 'https://dashboard.stripe.com/test/identity/verification-sessions/' : 'https://dashboard.stripe.com/identity/verification-sessions/';
				$api_status   = $settings_obj->get_api_status();
				?>
				<div class="dsic-dashboard-page">
					<?php if ( ! $api_status['configured'] ) : ?>
						<!-- API Configuration Warning -->
						<div class="dsic-dashboard-notice dsic-notice-error">
							<span class="dashicons dashicons-warning"></span>
							<div class="dsic-notice-content">
								<strong><?php esc_html_e( 'API Configuration Required', 'droix-stripe-id-check' ); ?></strong>
								<p>
									<?php
									$missing_text = implode( ', ', $api_status['missing'] );
									printf(
										/* translators: 1: Mode label (Test/Live), 2: List of missing items */
										esc_html__( 'ID verification is currently not working. Missing %1$s API credentials: %2$s. Please configure your Stripe API keys to enable verification.', 'droix-stripe-id-check' ),
										esc_html( $api_status['mode_label'] ),
										'<strong>' . esc_html( $missing_text ) . '</strong>'
									);
									?>
								</p>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsic-settings&tab=api' ) ); ?>" class="button button-primary">
									<?php esc_html_e( 'Configure API Settings', 'droix-stripe-id-check' ); ?>
								</a>
							</div>
						</div>
					<?php endif; ?>
					<!-- Stats Overview -->
					<div class="dsic-stats-overview">
						<div class="dsic-stat-card dsic-stat-total">
							<div class="dsic-stat-icon"><span class="dashicons dashicons-id-alt"></span></div>
							<div class="dsic-stat-content">
								<span class="dsic-stat-value"><?php echo esc_html( number_format_i18n( $totals['requested'] ) ); ?></span>
								<span class="dsic-stat-title"><?php esc_html_e( 'Total Requested', 'droix-stripe-id-check' ); ?></span>
							</div>
						</div>
						<div class="dsic-stat-card dsic-stat-clicked">
							<div class="dsic-stat-icon"><span class="dashicons dashicons-admin-links"></span></div>
							<div class="dsic-stat-content">
								<span class="dsic-stat-value"><?php echo esc_html( number_format_i18n( $totals['link_clicked'] ) ); ?></span>
								<span class="dsic-stat-title"><?php esc_html_e( 'Links Clicked', 'droix-stripe-id-check' ); ?></span>
							</div>
						</div>
						<div class="dsic-stat-card dsic-stat-verified">
							<div class="dsic-stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
							<div class="dsic-stat-content">
								<span class="dsic-stat-value"><?php echo esc_html( number_format_i18n( $totals['verified'] ) ); ?></span>
								<span class="dsic-stat-title"><?php esc_html_e( 'Verified', 'droix-stripe-id-check' ); ?></span>
							</div>
						</div>
						<div class="dsic-stat-card dsic-stat-pending">
							<div class="dsic-stat-icon"><span class="dashicons dashicons-clock"></span></div>
							<div class="dsic-stat-content">
								<span class="dsic-stat-value"><?php echo esc_html( number_format_i18n( $totals['pending'] ) ); ?></span>
								<span class="dsic-stat-title"><?php esc_html_e( 'Pending', 'droix-stripe-id-check' ); ?></span>
							</div>
						</div>
						<div class="dsic-stat-card dsic-stat-failed">
							<div class="dsic-stat-icon"><span class="dashicons dashicons-dismiss"></span></div>
							<div class="dsic-stat-content">
								<span class="dsic-stat-value"><?php echo esc_html( number_format_i18n( $totals['failed'] ) ); ?></span>
								<span class="dsic-stat-title"><?php esc_html_e( 'Failed', 'droix-stripe-id-check' ); ?></span>
							</div>
						</div>
					</div>

					<!-- Success Rate & Period Stats Row -->
					<div class="dsic-stats-row">
						<div class="dsic-stats-box dsic-success-rate-box">
							<h3><?php esc_html_e( 'Success Rate', 'droix-stripe-id-check' ); ?></h3>
							<div class="dsic-success-rate">
								<div class="dsic-rate-circle">
									<svg viewBox="0 0 36 36" class="dsic-rate-svg">
										<path class="dsic-rate-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
										<path class="dsic-rate-fg" stroke-dasharray="<?php echo esc_attr( $rates['success_rate'] ); ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
									</svg>
									<span class="dsic-rate-percent"><?php echo esc_html( $rates['success_rate'] ); ?>%</span>
								</div>
								<div class="dsic-rate-details">
									<p>
										<strong><?php echo esc_html( $rates['completed'] ); ?></strong>
										<?php esc_html_e( 'completed verifications', 'droix-stripe-id-check' ); ?>
									</p>
									<p>
										<strong><?php echo esc_html( $rates['completion_rate'] ); ?>%</strong>
										<?php esc_html_e( 'completion rate', 'droix-stripe-id-check' ); ?>
									</p>
									<?php if ( $avg_time ) : ?>
										<p>
											<strong><?php echo esc_html( DSIC_Stats::format_duration( $avg_time ) ); ?></strong>
											<?php esc_html_e( 'avg. verification time', 'droix-stripe-id-check' ); ?>
										</p>
									<?php endif; ?>
								</div>
							</div>
						</div>

						<div class="dsic-stats-box dsic-period-stats-box">
							<h3><?php esc_html_e( 'Verification by Period', 'droix-stripe-id-check' ); ?></h3>
							<table class="dsic-stats-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Period', 'droix-stripe-id-check' ); ?></th>
										<th><?php esc_html_e( 'Total', 'droix-stripe-id-check' ); ?></th>
										<th><?php esc_html_e( 'Verified', 'droix-stripe-id-check' ); ?></th>
										<th><?php esc_html_e( 'Failed', 'droix-stripe-id-check' ); ?></th>
										<th><?php esc_html_e( 'Pending', 'droix-stripe-id-check' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $periods as $period ) : ?>
										<tr>
											<td><strong><?php echo esc_html( $period['label'] ); ?></strong></td>
											<td><?php echo esc_html( $period['total'] ); ?></td>
											<td class="dsic-text-success"><?php echo esc_html( $period['verified'] ); ?></td>
											<td class="dsic-text-danger"><?php echo esc_html( $period['failed'] ); ?></td>
											<td class="dsic-text-warning"><?php echo esc_html( $period['pending'] ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>

					<!-- All Verification Checks Table -->
					<div class="dsic-dashboard-section">
						<h2><?php esc_html_e( 'All Verification Checks', 'droix-stripe-id-check' ); ?></h2>
						<p class="dsic-section-desc"><?php esc_html_e( 'Monitor all ID verification requests and their statuses. Track the complete verification lifecycle.', 'droix-stripe-id-check' ); ?></p>

						<?php if ( ! empty( $all_checks ) ) : ?>
							<table class="dsic-dashboard-table widefat striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Order', 'droix-stripe-id-check' ); ?></th>
										<th><?php esc_html_e( 'Customer', 'droix-stripe-id-check' ); ?></th>
										<th><?php esc_html_e( 'Status', 'droix-stripe-id-check' ); ?></th>
										<th><?php esc_html_e( 'Email Sent', 'droix-stripe-id-check' ); ?></th>
										<th><?php esc_html_e( 'Link Clicked', 'droix-stripe-id-check' ); ?></th>
										<th><?php esc_html_e( 'Completed', 'droix-stripe-id-check' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'droix-stripe-id-check' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $all_checks as $check ) : ?>
										<tr>
											<td>
												<a href="<?php echo esc_url( $check['edit_url'] ); ?>" target="_blank">
													<strong>#<?php echo esc_html( $check['order_number'] ); ?></strong>
												</a>
											</td>
											<td>
												<?php echo esc_html( $check['customer'] ); ?>
												<br><small><?php echo esc_html( $check['email'] ); ?></small>
											</td>
											<td>
												<?php
												$status_class = 'dsic-status-' . esc_attr( $check['status'] );
												$status_icon  = '';
												$status_text  = ucfirst( $check['status'] );

												switch ( $check['status'] ) {
													case 'pending':
														$status_icon = '<span class="dashicons dashicons-clock"></span>';
														break;
													case 'verified':
														$status_icon = '<span class="dashicons dashicons-yes-alt"></span>';
														break;
													case 'failed':
														$status_icon = '<span class="dashicons dashicons-warning"></span>';
														$status_text .= $check['error_msg'] ? ' - ' . $check['error_msg'] : '';
														break;
												}
												?>
												<span class="dsic-status-badge <?php echo esc_attr( $status_class ); ?>">
													<?php echo $status_icon; // phpcs:ignore ?>
													<?php echo esc_html( $status_text ); ?>
												</span>
											</td>
											<td>
												<?php if ( $check['requested'] ) : ?>
													<span class="dsic-check-yes" title="<?php esc_attr_e( 'Email sent', 'droix-stripe-id-check' ); ?>">
														<span class="dashicons dashicons-yes"></span>
													</span>
													<small><?php echo esc_html( $check['requested'] ); ?></small>
												<?php else : ?>
													<span class="dsic-check-no">—</span>
												<?php endif; ?>
											</td>
											<td>
												<?php if ( $check['link_clicked'] ) : ?>
													<span class="dsic-check-yes" title="<?php esc_attr_e( 'Customer clicked link', 'droix-stripe-id-check' ); ?>">
														<span class="dashicons dashicons-yes"></span>
													</span>
													<small><?php echo esc_html( $check['link_clicked'] ); ?></small>
												<?php else : ?>
													<span class="dsic-check-no" title="<?php esc_attr_e( 'Not clicked yet', 'droix-stripe-id-check' ); ?>">—</span>
												<?php endif; ?>
											</td>
											<td>
												<?php if ( $check['completed'] ) : ?>
													<small><?php echo esc_html( $check['completed'] ); ?></small>
												<?php else : ?>
													<span class="dsic-check-no">—</span>
												<?php endif; ?>
											</td>
											<td class="dsic-actions-cell">
												<a href="<?php echo esc_url( $check['edit_url'] ); ?>" class="button button-small" title="<?php esc_attr_e( 'View Order', 'droix-stripe-id-check' ); ?>">
													<span class="dashicons dashicons-visibility"></span>
												</a>
												<?php if ( $check['session_id'] ) : ?>
													<a href="<?php echo esc_url( $stripe_base . $check['session_id'] ); ?>" class="button button-small" target="_blank" title="<?php esc_attr_e( 'View in Stripe', 'droix-stripe-id-check' ); ?>">
														<span class="dashicons dashicons-external"></span>
													</a>
												<?php endif; ?>
												<?php
												// Show delete button for verified/failed orders with session that haven't been redacted.
												$can_redact = in_array( $check['status'], array( 'verified', 'failed' ), true )
													&& ! empty( $check['session_id'] )
													&& 'redacted' !== $check['redaction_status'];
												?>
												<?php if ( $can_redact ) : ?>
													<button type="button" class="button button-small dsic-dashboard-redact-btn" data-order="<?php echo esc_attr( $check['order_id'] ); ?>" title="<?php esc_attr_e( 'Delete Data from Stripe', 'droix-stripe-id-check' ); ?>">
														<span class="dashicons dashicons-trash"></span>
													</button>
												<?php elseif ( 'redacted' === $check['redaction_status'] ) : ?>
													<span class="dsic-redacted-badge" title="<?php esc_attr_e( 'Data deleted', 'droix-stripe-id-check' ); ?>">
														<span class="dashicons dashicons-yes"></span>
													</span>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php else : ?>
							<p class="dsic-no-data"><?php esc_html_e( 'No verification checks yet. Request ID verification from an order to get started.', 'droix-stripe-id-check' ); ?></p>
						<?php endif; ?>
					</div>

					<!-- Status Legend -->
					<div class="dsic-legend">
						<h3><?php esc_html_e( 'Status Legend', 'droix-stripe-id-check' ); ?></h3>
						<ul>
							<li><span class="dsic-status-badge dsic-status-pending"><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Pending', 'droix-stripe-id-check' ); ?></span> — <?php esc_html_e( 'Email sent, waiting for customer to complete verification', 'droix-stripe-id-check' ); ?></li>
							<li><span class="dsic-status-badge dsic-status-verified"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Verified', 'droix-stripe-id-check' ); ?></span> — <?php esc_html_e( 'Customer identity verified successfully', 'droix-stripe-id-check' ); ?></li>
							<li><span class="dsic-status-badge dsic-status-failed"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Failed', 'droix-stripe-id-check' ); ?></span> — <?php esc_html_e( 'Verification failed or was abandoned', 'droix-stripe-id-check' ); ?></li>
						</ul>
						<p class="dsic-legend-note">
							<strong><?php esc_html_e( 'Note:', 'droix-stripe-id-check' ); ?></strong>
							<?php esc_html_e( 'Stripe charges $1.50 per successful verification. Multiple link clicks for the same order reuse the existing session to prevent extra charges.', 'droix-stripe-id-check' ); ?>
						</p>
					</div>
				</div>

				<style>
					.dsic-dashboard-page { padding: 10px 0; }

					/* Stats Overview Cards */
					.dsic-stats-overview { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
					.dsic-stat-card { display: flex; align-items: center; gap: 15px; background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 20px 24px; min-width: 180px; flex: 1; }
					.dsic-stat-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
					.dsic-stat-icon .dashicons { font-size: 24px; width: 24px; height: 24px; color: #fff; }
					.dsic-stat-content { display: flex; flex-direction: column; }
					.dsic-stat-value { font-size: 28px; font-weight: 600; line-height: 1.2; }
					.dsic-stat-title { font-size: 13px; color: #646970; margin-top: 2px; }
					.dsic-stat-total .dsic-stat-icon { background: #2271b1; }
					.dsic-stat-total .dsic-stat-value { color: #2271b1; }
					.dsic-stat-clicked .dsic-stat-icon { background: #8c6e00; }
					.dsic-stat-clicked .dsic-stat-value { color: #8c6e00; }
					.dsic-stat-verified .dsic-stat-icon { background: #00a32a; }
					.dsic-stat-verified .dsic-stat-value { color: #00a32a; }
					.dsic-stat-pending .dsic-stat-icon { background: #dba617; }
					.dsic-stat-pending .dsic-stat-value { color: #dba617; }
					.dsic-stat-failed .dsic-stat-icon { background: #d63638; }
					.dsic-stat-failed .dsic-stat-value { color: #d63638; }

					/* Stats Row (Success Rate + Period Table) */
					.dsic-stats-row { display: flex; gap: 20px; margin-bottom: 20px; }
					.dsic-stats-box { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 20px; }
					.dsic-stats-box h3 { margin: 0 0 15px; font-size: 14px; font-weight: 600; }
					.dsic-success-rate-box { flex: 0 0 280px; }
					.dsic-period-stats-box { flex: 1; }
					.dsic-success-rate { display: flex; align-items: center; gap: 20px; }
					.dsic-rate-circle { position: relative; width: 100px; height: 100px; }
					.dsic-rate-svg { width: 100%; height: 100%; transform: rotate(-90deg); }
					.dsic-rate-bg { fill: none; stroke: #e5e5e5; stroke-width: 3; }
					.dsic-rate-fg { fill: none; stroke: #00a32a; stroke-width: 3; stroke-linecap: round; }
					.dsic-rate-percent { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 20px; font-weight: 600; color: #00a32a; }
					.dsic-rate-details p { margin: 0 0 8px; font-size: 13px; color: #646970; }
					.dsic-rate-details strong { color: #1d2327; }
					.dsic-stats-table { width: 100%; border-collapse: collapse; }
					.dsic-stats-table th, .dsic-stats-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #f0f0f1; }
					.dsic-stats-table th { font-weight: 600; font-size: 12px; color: #646970; text-transform: uppercase; }
					.dsic-stats-table td { font-size: 14px; }
					.dsic-text-success { color: #00a32a; font-weight: 600; }
					.dsic-text-danger { color: #d63638; font-weight: 600; }
					.dsic-text-warning { color: #dba617; font-weight: 600; }

					/* Dashboard Section */
					.dsic-dashboard-section { background: #fff; border: 1px solid #dcdcde; border-radius: 4px; padding: 20px; margin-bottom: 20px; }
					.dsic-dashboard-section h2 { margin: 0 0 5px; font-size: 16px; }
					.dsic-section-desc { color: #646970; margin: 0 0 15px; }

					.dsic-dashboard-table { margin-top: 10px; }
					.dsic-dashboard-table th { font-weight: 600; }
					.dsic-dashboard-table td { vertical-align: middle; }
					.dsic-dashboard-table small { display: block; color: #646970; font-size: 11px; }

					.dsic-status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: 500; }
					.dsic-status-pending { background: #fcf9e8; color: #996800; }
					.dsic-status-verified { background: #edfaef; color: #00a32a; }
					.dsic-status-failed { background: #fcf0f1; color: #d63638; }
					.dsic-status-badge .dashicons { font-size: 14px; width: 14px; height: 14px; }

					.dsic-check-yes { color: #00a32a; }
					.dsic-check-yes .dashicons { font-size: 16px; width: 16px; height: 16px; vertical-align: middle; }
					.dsic-check-no { color: #c3c4c7; }

					.dsic-actions-cell { white-space: nowrap; }
					.dsic-actions-cell .button { padding: 0 6px; min-height: 26px; line-height: 24px; }
					.dsic-actions-cell .dashicons { font-size: 16px; width: 16px; height: 16px; vertical-align: middle; }
					.dsic-dashboard-redact-btn { color: #d63638 !important; border-color: #d63638 !important; }
					.dsic-dashboard-redact-btn:hover { background: #d63638 !important; color: #fff !important; }
					.dsic-redacted-badge { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; background: #edfaef; border-radius: 3px; color: #00a32a; }
					.dsic-redacted-badge .dashicons { font-size: 16px; width: 16px; height: 16px; }

					.dsic-legend { background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px; padding: 15px 20px; }
					.dsic-legend h3 { margin: 0 0 10px; font-size: 14px; }
					.dsic-legend ul { margin: 0; padding: 0; list-style: none; }
					.dsic-legend li { margin: 8px 0; }
					.dsic-legend-note { margin: 15px 0 0; padding-top: 10px; border-top: 1px solid #dcdcde; color: #646970; font-size: 13px; }

					.dsic-no-data { padding: 40px 20px; text-align: center; color: #646970; background: #f6f7f7; border-radius: 4px; }

					/* Responsive */
					@media (max-width: 1200px) {
						.dsic-stats-row { flex-direction: column; }
						.dsic-success-rate-box { flex: 1; }
					}
					@media (max-width: 782px) {
						.dsic-stats-overview { flex-direction: column; }
						.dsic-stat-card { min-width: 100%; }
					}
				</style>

			<?php elseif ( 'general' === $current_tab ) : ?>
				<!-- General Settings Tab -->
				<table class="form-table dsic-form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="dsic_enabled"><?php esc_html_e( 'Enable Plugin', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<label class="dsic-toggle">
									<input type="checkbox" id="dsic_enabled" name="dsic_enabled" value="1"
										<?php checked( $settings['enabled'], true ); ?>>
									<span class="dsic-toggle-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Enable or disable the ID verification functionality.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="dsic_crm_email"><?php esc_html_e( 'CRM Notification Email', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<input type="email" id="dsic_crm_email" name="dsic_crm_email"
									value="<?php echo esc_attr( $settings['crm_email'] ); ?>"
									class="regular-text">
								<p class="description">
									<?php esc_html_e( 'Email address to receive verification notifications.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="dsic_delete_data_on_uninstall"><?php esc_html_e( 'Delete Data on Uninstall', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<label class="dsic-toggle">
									<input type="checkbox" id="dsic_delete_data_on_uninstall" name="dsic_delete_data_on_uninstall" value="1"
										<?php checked( $settings['delete_on_uninstall'], true ); ?>>
									<span class="dsic-toggle-slider"></span>
								</label>
								<p class="description dsic-warning">
									<?php esc_html_e( 'Warning: This will permanently delete all plugin data including settings and order verification data when the plugin is uninstalled.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- Verification Options Section -->
				<h2 class="dsic-section-title"><?php esc_html_e( 'Verification Options', 'droix-stripe-id-check' ); ?></h2>
				<p class="dsic-section-description"><?php esc_html_e( 'Configure what checks are performed during identity verification.', 'droix-stripe-id-check' ); ?></p>

				<?php
				// Get current verification options.
				$require_selfie       = get_option( 'dsic_require_selfie', '1' );
				$require_id_number    = get_option( 'dsic_require_id_number', '0' );
				$require_live_capture = get_option( 'dsic_require_live_capture', '1' );
				$prefill_phone        = get_option( 'dsic_prefill_phone', '1' );
				$allowed_doc_types    = get_option( 'dsic_allowed_document_types', array( 'driving_license', 'id_card', 'passport' ) );
				?>

				<table class="form-table dsic-form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="dsic_require_selfie"><?php esc_html_e( 'Require Selfie Check', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<label class="dsic-toggle">
									<input type="checkbox" id="dsic_require_selfie" name="dsic_require_selfie" value="1"
										<?php checked( $require_selfie, '1' ); ?>>
									<span class="dsic-toggle-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Capture a selfie and compare it to the face on the photo ID. Recommended for fraud prevention.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="dsic_require_id_number"><?php esc_html_e( 'Require ID Number Check', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<label class="dsic-toggle">
									<input type="checkbox" id="dsic_require_id_number" name="dsic_require_id_number" value="1"
										<?php checked( $require_id_number, '1' ); ?>>
									<span class="dsic-toggle-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Collect an ID Number and verify it alongside the information on the document.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="dsic_require_live_capture"><?php esc_html_e( 'Require Live Capture', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<label class="dsic-toggle">
									<input type="checkbox" id="dsic_require_live_capture" name="dsic_require_live_capture" value="1"
										<?php checked( $require_live_capture, '1' ); ?>>
									<span class="dsic-toggle-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Disable image uploads and enforce that images are captured using the device\'s camera. Prevents use of screenshots or photos of photos.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="dsic_prefill_phone"><?php esc_html_e( 'Pre-fill Phone Number', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<label class="dsic-toggle">
									<input type="checkbox" id="dsic_prefill_phone" name="dsic_prefill_phone" value="1"
										<?php checked( $prefill_phone, '1' ); ?>>
									<span class="dsic-toggle-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Pre-fill the customer\'s phone number in the verification form. Phone must be in E.164 format (e.g., +447123456789) to be included.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'Allowed Document Types', 'droix-stripe-id-check' ); ?>
							</th>
							<td>
								<fieldset>
									<label>
										<input type="checkbox" name="dsic_doc_driving_license" value="1"
											<?php checked( in_array( 'driving_license', $allowed_doc_types, true ) ); ?>>
										<?php esc_html_e( 'Driving Licence', 'droix-stripe-id-check' ); ?>
									</label>
									<br>
									<label>
										<input type="checkbox" name="dsic_doc_id_card" value="1"
											<?php checked( in_array( 'id_card', $allowed_doc_types, true ) ); ?>>
										<?php esc_html_e( 'ID Card', 'droix-stripe-id-check' ); ?>
									</label>
									<br>
									<label>
										<input type="checkbox" name="dsic_doc_passport" value="1"
											<?php checked( in_array( 'passport', $allowed_doc_types, true ) ); ?>>
										<?php esc_html_e( 'Passport', 'droix-stripe-id-check' ); ?>
									</label>
								</fieldset>
								<p class="description">
									<?php esc_html_e( 'Select which document types customers can use for verification. At least one must be selected.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="dsic_session_expiry"><?php esc_html_e( 'Verification Session Expiry', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<?php
								$session_expiry = get_option( 'dsic_session_expiry_days', '30' );
								?>
								<input type="number" id="dsic_session_expiry" name="dsic_session_expiry_days"
									value="<?php echo esc_attr( $session_expiry ); ?>"
									min="1" max="90" step="1" class="small-text">
								<span><?php esc_html_e( 'days', 'droix-stripe-id-check' ); ?></span>
								<p class="description">
									<?php esc_html_e( 'How long the verification link remains valid. After this period, customers must request a new verification link. Stripe automatically deletes verification data 30 days after successful verification (configurable in Stripe Dashboard).', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- Auto-Verification Section -->
				<h2 class="dsic-section-title"><?php esc_html_e( 'Auto-Verification', 'droix-stripe-id-check' ); ?></h2>
				<p class="dsic-section-description"><?php esc_html_e( 'Automatically trigger ID verification when customers choose to ship to a different address.', 'droix-stripe-id-check' ); ?></p>

				<?php
				// Get current auto-verification options.
				$auto_verify_enabled    = get_option( 'dsic_auto_verify_different_address', '0' );
				$auto_verify_checkout   = get_option( 'dsic_auto_verify_checkout_message', $settings_obj->get_default_checkout_message() );
				$auto_verify_thankyou   = get_option( 'dsic_auto_verify_thankyou_message', $settings_obj->get_default_thankyou_message() );
				$wc_ship_to_destination = get_option( 'woocommerce_ship_to_destination', 'shipping' );
				?>

				<table class="form-table dsic-form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="dsic_auto_verify_different_address"><?php esc_html_e( 'Enable Auto-Verification', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<label class="dsic-toggle">
									<input type="checkbox" id="dsic_auto_verify_different_address" name="dsic_auto_verify_different_address" value="1"
										<?php checked( $auto_verify_enabled, '1' ); ?>>
									<span class="dsic-toggle-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'When enabled, orders with different shipping address will automatically require ID verification.', 'droix-stripe-id-check' ); ?>
								</p>
								<?php if ( 'shipping' !== $wc_ship_to_destination ) : ?>
									<p class="description dsic-warning" style="margin-top: 8px;">
										<span class="dashicons dashicons-warning" style="color: #d63638;"></span>
										<?php
										printf(
											/* translators: %s: WooCommerce settings URL */
											wp_kses_post( __( 'Note: This feature requires WooCommerce "Shipping destination" to be set to "Default to customer shipping address". <a href="%s">Configure WooCommerce settings</a>.', 'droix-stripe-id-check' ) ),
											esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=options' ) )
										);
										?>
									</p>
								<?php endif; ?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="dsic_auto_verify_checkout_message"><?php esc_html_e( 'Checkout Warning Message', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<textarea id="dsic_auto_verify_checkout_message" name="dsic_auto_verify_checkout_message"
									rows="3" class="large-text"><?php echo esc_textarea( $auto_verify_checkout ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Displayed in checkout when customer selects "Ship to different address". This message warns them that ID verification will be required.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="dsic_auto_verify_thankyou_message"><?php esc_html_e( 'Thank You Page Message', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<textarea id="dsic_auto_verify_thankyou_message" name="dsic_auto_verify_thankyou_message"
									rows="4" class="large-text"><?php echo esc_textarea( $auto_verify_thankyou ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Displayed on the order confirmation page when auto-verification is triggered. Explains that the order is on hold and verification email will be sent.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- Fraud Detection (Stripe Radar) Section -->
				<h2 class="dsic-section-title"><?php esc_html_e( 'Fraud Detection (Stripe Radar)', 'droix-stripe-id-check' ); ?></h2>
				<p class="dsic-section-description"><?php esc_html_e( 'Automatically trigger ID verification when Stripe Radar flags a payment as high risk.', 'droix-stripe-id-check' ); ?></p>

				<?php
				$radar_enabled          = get_option( 'dsic_radar_check_enabled', '0' );
				$radar_mode             = get_option( 'dsic_radar_check_mode', 'risk_level' );
				$radar_level_threshold  = get_option( 'dsic_radar_risk_level_threshold', 'elevated' );
				$radar_score_threshold  = get_option( 'dsic_radar_risk_score_threshold', '65' );
				$radar_early_warnings   = get_option( 'dsic_radar_early_warnings_enabled', '0' );
				$radar_min_amount       = get_option( 'dsic_radar_minimum_order_amount', '0' );
				$settings_obj           = new DSIC_Settings();
				$radar_thankyou_message = get_option( 'dsic_radar_thankyou_message', $settings_obj->get_default_radar_thankyou_message() );
				?>

				<table class="form-table dsic-form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="dsic_radar_check_enabled"><?php esc_html_e( 'Enable Radar Check', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<label class="dsic-toggle">
									<input type="checkbox" id="dsic_radar_check_enabled" name="dsic_radar_check_enabled" value="1"
										<?php checked( $radar_enabled, '1' ); ?>>
									<span class="dsic-toggle-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'When enabled, orders with high Stripe Radar risk scores will automatically require ID verification.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'Detection Mode', 'droix-stripe-id-check' ); ?>
							</th>
							<td>
								<fieldset>
									<label style="display: block; margin-bottom: 8px;">
										<input type="radio" name="dsic_radar_check_mode" value="risk_level"
											<?php checked( $radar_mode, 'risk_level' ); ?>>
										<?php esc_html_e( 'Risk Level (all Stripe accounts)', 'droix-stripe-id-check' ); ?>
									</label>
									<label style="display: block;">
										<input type="radio" name="dsic_radar_check_mode" value="risk_score"
											<?php checked( $radar_mode, 'risk_score' ); ?>>
										<?php esc_html_e( 'Risk Score (requires Radar for Fraud Teams)', 'droix-stripe-id-check' ); ?>
									</label>
								</fieldset>
								<p class="description">
									<?php esc_html_e( 'Risk Level is available on all Stripe accounts. Risk Score (0-99) requires the paid Radar for Fraud Teams add-on.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>

						<tr class="dsic-radar-level-row">
							<th scope="row">
								<label for="dsic_radar_risk_level_threshold"><?php esc_html_e( 'Risk Level Threshold', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<select id="dsic_radar_risk_level_threshold" name="dsic_radar_risk_level_threshold">
									<option value="elevated" <?php selected( $radar_level_threshold, 'elevated' ); ?>>
										<?php esc_html_e( 'Elevated or higher', 'droix-stripe-id-check' ); ?>
									</option>
									<option value="highest" <?php selected( $radar_level_threshold, 'highest' ); ?>>
										<?php esc_html_e( 'Highest only', 'droix-stripe-id-check' ); ?>
									</option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Trigger verification when risk level meets or exceeds this threshold.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>

						<tr class="dsic-radar-score-row">
							<th scope="row">
								<label for="dsic_radar_risk_score_threshold"><?php esc_html_e( 'Risk Score Threshold', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<input type="number" id="dsic_radar_risk_score_threshold" name="dsic_radar_risk_score_threshold"
									value="<?php echo esc_attr( $radar_score_threshold ); ?>"
									min="0" max="99" step="1" class="small-text">
								<p class="description">
									<?php esc_html_e( 'Trigger verification when risk score is at or above this value (0-99). Default: 65.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="dsic_radar_early_warnings_enabled"><?php esc_html_e( 'Early Fraud Warnings', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<label class="dsic-toggle">
									<input type="checkbox" id="dsic_radar_early_warnings_enabled" name="dsic_radar_early_warnings_enabled" value="1"
										<?php checked( $radar_early_warnings, '1' ); ?>>
									<span class="dsic-toggle-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Trigger verification when Stripe receives an Early Fraud Warning from the card issuer. These are retroactive alerts that can arrive hours or days after payment.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="dsic_radar_minimum_order_amount"><?php esc_html_e( 'Minimum Order Amount', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<input type="number" id="dsic_radar_minimum_order_amount" name="dsic_radar_minimum_order_amount"
									value="<?php echo esc_attr( $radar_min_amount ); ?>"
									min="0" step="0.01" class="small-text">
								<p class="description">
									<?php esc_html_e( 'Skip Radar fraud checks for orders below this amount (in your store currency). Set to 0 to check all orders regardless of amount.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="dsic_radar_thankyou_message"><?php esc_html_e( 'Thank You Page Message', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<textarea id="dsic_radar_thankyou_message" name="dsic_radar_thankyou_message"
									rows="4" class="large-text"><?php echo esc_textarea( $radar_thankyou_message ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Shown on the order confirmation page when Stripe Radar triggers ID verification. Explains the order is on hold for a security check.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<script>
				jQuery(function($) {
					function dsicToggleRadarFields() {
						var mode = $('input[name="dsic_radar_check_mode"]:checked').val();
						$('.dsic-radar-level-row').toggle(mode === 'risk_level');
						$('.dsic-radar-score-row').toggle(mode === 'risk_score');
					}
					dsicToggleRadarFields();
					$('input[name="dsic_radar_check_mode"]').on('change', dsicToggleRadarFields);
				});
				</script>

				<?php
				// Amount threshold settings.
				$amount_threshold_enabled  = get_option( 'dsic_amount_threshold_enabled', '0' );
				$amount_threshold_value    = get_option( 'dsic_amount_threshold_value', '0' );
				$amount_threshold_currency = get_option( 'dsic_amount_threshold_currency', '' );
				$threshold_currencies      = array(
					'USD' => 'USD — US Dollar',
					'GBP' => 'GBP — British Pound',
					'EUR' => 'EUR — Euro',
					'CAD' => 'CAD — Canadian Dollar',
					'AUD' => 'AUD — Australian Dollar',
					'JPY' => 'JPY — Japanese Yen',
					'CHF' => 'CHF — Swiss Franc',
					'SEK' => 'SEK — Swedish Krona',
					'NOK' => 'NOK — Norwegian Krone',
					'DKK' => 'DKK — Danish Krone',
					'PLN' => 'PLN — Polish Zloty',
					'CZK' => 'CZK — Czech Koruna',
					'HUF' => 'HUF — Hungarian Forint',
					'NZD' => 'NZD — New Zealand Dollar',
					'SGD' => 'SGD — Singapore Dollar',
					'HKD' => 'HKD — Hong Kong Dollar',
					'MXN' => 'MXN — Mexican Peso',
					'BRL' => 'BRL — Brazilian Real',
					'INR' => 'INR — Indian Rupee',
					'ZAR' => 'ZAR — South African Rand',
				);
				?>

				<!-- Order Amount Threshold Section -->
				<h2 class="dsic-section-title"><?php esc_html_e( 'Order Amount Threshold', 'droix-stripe-id-check' ); ?></h2>
				<p class="dsic-section-description">
					<?php esc_html_e( 'Automatically require ID verification for orders whose total exceeds a configured amount. Multi-currency stores can supply exchange rates with a filter.', 'droix-stripe-id-check' ); ?>
				</p>

				<table class="form-table dsic-form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="dsic_amount_threshold_enabled"><?php esc_html_e( 'Enable Amount Threshold', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<label class="dsic-toggle">
									<input type="checkbox" id="dsic_amount_threshold_enabled" name="dsic_amount_threshold_enabled" value="1" <?php checked( '1', $amount_threshold_enabled ); ?>>
									<span class="dsic-toggle-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'When enabled, orders above the threshold amount will be held for ID verification.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr class="dsic-threshold-row">
							<th scope="row">
								<label for="dsic_amount_threshold_value"><?php esc_html_e( 'Threshold Amount', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<input type="number" id="dsic_amount_threshold_value" name="dsic_amount_threshold_value"
									value="<?php echo esc_attr( $amount_threshold_value ); ?>"
									step="0.01" min="0" class="small-text">
								<p class="description">
									<?php esc_html_e( 'Orders with a total at or above this amount will trigger ID verification. Enter 0 to disable.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr class="dsic-threshold-row">
							<th scope="row">
								<label for="dsic_amount_threshold_currency"><?php esc_html_e( 'Threshold Currency', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<select id="dsic_amount_threshold_currency" name="dsic_amount_threshold_currency">
									<option value="" <?php selected( '', $amount_threshold_currency ); ?>>
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: WooCommerce store currency code */
												__( 'Auto-detect (%s)', 'droix-stripe-id-check' ),
												get_woocommerce_currency()
											)
										);
										?>
									</option>
									<?php foreach ( $threshold_currencies as $code => $label ) : ?>
										<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $code, $amount_threshold_currency ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'The currency to compare order totals against. Orders in other currencies require exchange rates supplied with the dsic_amount_threshold_exchange_rates filter.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr class="dsic-threshold-row">
							<th scope="row"></th>
							<td>
								<div class="notice notice-info inline" style="margin:0;padding:8px 12px;">
									<p style="margin:0;">
										<?php esc_html_e( 'Currency conversion is disabled by default to avoid stale exchange rates. Use the dsic_amount_threshold_exchange_rates filter to supply current USD-base rates for multi-currency stores.', 'droix-stripe-id-check' ); ?>
									</p>
								</div>
							</td>
						</tr>
					</tbody>
				</table>

				<script>
				jQuery(function($) {
					function dsicToggleThresholdFields() {
						var enabled = $('#dsic_amount_threshold_enabled').is(':checked');
						$('.dsic-threshold-row').toggle(enabled);
					}
					dsicToggleThresholdFields();
					$('#dsic_amount_threshold_enabled').on('change', dsicToggleThresholdFields);
				});
				</script>

			<?php elseif ( 'api' === $current_tab ) : ?>
				<!-- API Settings Tab -->
				<?php
				// Get current mode and keys.
				$test_mode = get_option( 'dsic_test_mode', '1' );
				$live_publishable_key = get_option( 'dsic_live_publishable_key', '' );
				$live_secret_key      = get_option( 'dsic_live_secret_key', '' );
				$live_webhook_secret  = get_option( 'dsic_live_webhook_secret', '' );
				$test_publishable_key = get_option( 'dsic_test_publishable_key', '' );
				$test_secret_key      = get_option( 'dsic_test_secret_key', '' );
				$test_webhook_secret  = get_option( 'dsic_test_webhook_secret', '' );
				?>

				<!-- API Mode Section -->
				<h2 class="dsic-section-title"><?php esc_html_e( 'API Mode', 'droix-stripe-id-check' ); ?></h2>
				<p class="dsic-section-description">
					<?php esc_html_e( 'Select which Stripe environment to use. Test mode uses sandbox keys for development.', 'droix-stripe-id-check' ); ?>
				</p>

				<table class="form-table dsic-form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="dsic_test_mode"><?php esc_html_e( 'Test Mode (Sandbox)', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<label class="dsic-toggle">
									<input type="checkbox" id="dsic_test_mode" name="dsic_test_mode" value="1"
										<?php checked( $test_mode, '1' ); ?>>
									<span class="dsic-toggle-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'When enabled, Test API keys are used. Disable for production to use Live keys.', 'droix-stripe-id-check' ); ?>
								</p>
								<p class="dsic-mode-indicator">
									<?php if ( $test_mode === '1' ) : ?>
										<span class="dsic-badge dsic-badge-warning">
											<span class="dashicons dashicons-info"></span>
											<?php esc_html_e( 'Currently using TEST mode', 'droix-stripe-id-check' ); ?>
										</span>
									<?php else : ?>
										<span class="dsic-badge dsic-badge-success">
											<span class="dashicons dashicons-yes-alt"></span>
											<?php esc_html_e( 'Currently using LIVE mode', 'droix-stripe-id-check' ); ?>
										</span>
									<?php endif; ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- Live API Keys Section -->
				<h2 class="dsic-section-title">
					<?php esc_html_e( 'Live API Keys', 'droix-stripe-id-check' ); ?>
					<span class="dsic-badge dsic-badge-success dsic-badge-small"><?php esc_html_e( 'Production', 'droix-stripe-id-check' ); ?></span>
				</h2>
				<p class="dsic-section-description">
					<?php esc_html_e( 'Enter your live Stripe API keys for production use.', 'droix-stripe-id-check' ); ?>
					<a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener">
						<?php esc_html_e( 'Get keys from Stripe Dashboard', 'droix-stripe-id-check' ); ?>
						<span class="dashicons dashicons-external" style="text-decoration: none;"></span>
					</a>
				</p>

				<table class="form-table dsic-form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="dsic_live_publishable_key"><?php esc_html_e( 'Live Publishable Key', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<input type="text" id="dsic_live_publishable_key" name="dsic_live_publishable_key"
									value="<?php echo esc_attr( $live_publishable_key ); ?>"
									class="regular-text"
									placeholder="pk_live_...">
								<p class="description">
									<?php esc_html_e( 'Starts with pk_live_', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="dsic_live_secret_key"><?php esc_html_e( 'Live Secret Key', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<div class="dsic-password-field">
									<input type="password" id="dsic_live_secret_key" name="dsic_live_secret_key"
										value=""
										class="regular-text"
										placeholder="<?php echo esc_attr( empty( $live_secret_key ) ? 'sk_live_...' : __( 'Saved secret key unchanged', 'droix-stripe-id-check' ) ); ?>">
									<button type="button" class="button dsic-toggle-password" data-target="dsic_live_secret_key">
										<span class="dashicons dashicons-visibility"></span>
									</button>
								</div>
								<p class="description">
									<?php esc_html_e( 'Starts with sk_live_. Leave blank to keep the saved key unchanged.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="dsic_live_webhook_secret"><?php esc_html_e( 'Live Webhook Secret', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<div class="dsic-password-field">
									<input type="password" id="dsic_live_webhook_secret" name="dsic_live_webhook_secret"
										value=""
										class="regular-text"
										placeholder="<?php echo esc_attr( empty( $live_webhook_secret ) ? 'whsec_...' : __( 'Saved webhook secret unchanged', 'droix-stripe-id-check' ) ); ?>">
									<button type="button" class="button dsic-toggle-password" data-target="dsic_live_webhook_secret">
										<span class="dashicons dashicons-visibility"></span>
									</button>
								</div>
								<p class="description">
									<?php esc_html_e( 'Webhook signing secret for live mode. Leave blank to keep the saved secret unchanged.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- Test API Keys Section -->
				<h2 class="dsic-section-title">
					<?php esc_html_e( 'Test API Keys', 'droix-stripe-id-check' ); ?>
					<span class="dsic-badge dsic-badge-warning dsic-badge-small"><?php esc_html_e( 'Sandbox', 'droix-stripe-id-check' ); ?></span>
				</h2>
				<p class="dsic-section-description">
					<?php esc_html_e( 'Enter your test Stripe API keys for development and testing.', 'droix-stripe-id-check' ); ?>
					<a href="https://dashboard.stripe.com/test/apikeys" target="_blank" rel="noopener">
						<?php esc_html_e( 'Get test keys from Stripe Dashboard', 'droix-stripe-id-check' ); ?>
						<span class="dashicons dashicons-external" style="text-decoration: none;"></span>
					</a>
				</p>

				<table class="form-table dsic-form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="dsic_test_publishable_key"><?php esc_html_e( 'Test Publishable Key', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<input type="text" id="dsic_test_publishable_key" name="dsic_test_publishable_key"
									value="<?php echo esc_attr( $test_publishable_key ); ?>"
									class="regular-text"
									placeholder="pk_test_...">
								<p class="description">
									<?php esc_html_e( 'Starts with pk_test_', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="dsic_test_secret_key"><?php esc_html_e( 'Test Secret Key', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<div class="dsic-password-field">
									<input type="password" id="dsic_test_secret_key" name="dsic_test_secret_key"
										value=""
										class="regular-text"
										placeholder="<?php echo esc_attr( empty( $test_secret_key ) ? 'sk_test_...' : __( 'Saved secret key unchanged', 'droix-stripe-id-check' ) ); ?>">
									<button type="button" class="button dsic-toggle-password" data-target="dsic_test_secret_key">
										<span class="dashicons dashicons-visibility"></span>
									</button>
								</div>
								<p class="description">
									<?php esc_html_e( 'Starts with sk_test_. Leave blank to keep the saved key unchanged.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="dsic_test_webhook_secret"><?php esc_html_e( 'Test Webhook Secret', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<div class="dsic-password-field">
									<input type="password" id="dsic_test_webhook_secret" name="dsic_test_webhook_secret"
										value=""
										class="regular-text"
										placeholder="<?php echo esc_attr( empty( $test_webhook_secret ) ? 'whsec_...' : __( 'Saved webhook secret unchanged', 'droix-stripe-id-check' ) ); ?>">
									<button type="button" class="button dsic-toggle-password" data-target="dsic_test_webhook_secret">
										<span class="dashicons dashicons-visibility"></span>
									</button>
								</div>
								<p class="description">
									<?php esc_html_e( 'Webhook signing secret for test mode. Leave blank to keep the saved secret unchanged.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- Webhook URL & Connection Test -->
				<h2 class="dsic-section-title"><?php esc_html_e( 'Webhook Configuration', 'droix-stripe-id-check' ); ?></h2>
				<p class="dsic-section-description">
					<?php esc_html_e( 'Configure the webhook endpoint in your Stripe Dashboard to receive verification updates.', 'droix-stripe-id-check' ); ?>
				</p>

				<table class="form-table dsic-form-table">
					<tbody>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Webhook URL', 'droix-stripe-id-check' ); ?>
							</th>
							<td>
								<div class="dsic-copy-field">
									<code id="dsic-webhook-url"><?php echo esc_url( $webhook_url ); ?></code>
									<button type="button" class="button dsic-copy-button" data-copy="dsic-webhook-url">
										<span class="dashicons dashicons-clipboard"></span>
										<?php esc_html_e( 'Copy', 'droix-stripe-id-check' ); ?>
									</button>
								</div>
								<p class="description">
									<?php esc_html_e( 'Add this URL to both Live and Test webhook endpoints in Stripe Dashboard.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Connection Test', 'droix-stripe-id-check' ); ?>
							</th>
							<td>
								<button type="button" id="dsic-test-connection" class="button button-secondary">
									<?php esc_html_e( 'Test Connection', 'droix-stripe-id-check' ); ?>
								</button>
								<span id="dsic-connection-status" class="dsic-status"></span>
								<p class="description">
									<?php
									if ( $test_mode === '1' ) {
										esc_html_e( 'Tests connection using Test API keys. Save settings first.', 'droix-stripe-id-check' );
									} else {
										esc_html_e( 'Tests connection using Live API keys. Save settings first.', 'droix-stripe-id-check' );
									}
									?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- Radar API Credentials -->
				<h2 class="dsic-section-title">
					<?php esc_html_e( 'Radar API Credentials', 'droix-stripe-id-check' ); ?>
					<span class="dsic-badge dsic-badge-warning dsic-badge-small"><?php esc_html_e( 'Optional', 'droix-stripe-id-check' ); ?></span>
				</h2>
				<p class="dsic-section-description">
					<?php esc_html_e( 'Separate keys are only needed if you use a different Stripe account for payments than for Identity verification. If you use the same account, leave these blank — your Identity API keys are used automatically.', 'droix-stripe-id-check' ); ?>
				</p>

				<?php
				$radar_live_secret_key     = get_option( 'dsic_radar_live_secret_key', '' );
				$radar_test_secret_key     = get_option( 'dsic_radar_test_secret_key', '' );
				$radar_live_webhook_secret = get_option( 'dsic_radar_live_webhook_secret', '' );
				$radar_test_webhook_secret = get_option( 'dsic_radar_test_webhook_secret', '' );
				?>

				<table class="form-table dsic-form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="dsic_radar_live_secret_key"><?php esc_html_e( 'Live Secret Key', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<div class="dsic-password-field">
									<input type="password" id="dsic_radar_live_secret_key" name="dsic_radar_live_secret_key"
										value=""
										class="regular-text"
										placeholder="<?php echo esc_attr( empty( $radar_live_secret_key ) ? 'sk_live_...' : __( 'Saved Radar key unchanged', 'droix-stripe-id-check' ) ); ?>">
									<button type="button" class="button dsic-toggle-password" data-target="dsic_radar_live_secret_key">
										<span class="dashicons dashicons-visibility"></span>
									</button>
								</div>
								<p class="description">
									<?php esc_html_e( 'Live secret key for Radar/Charges API access. Leave blank to keep the saved key unchanged.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="dsic_radar_test_secret_key"><?php esc_html_e( 'Test Secret Key', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<div class="dsic-password-field">
									<input type="password" id="dsic_radar_test_secret_key" name="dsic_radar_test_secret_key"
										value=""
										class="regular-text"
										placeholder="<?php echo esc_attr( empty( $radar_test_secret_key ) ? 'sk_test_...' : __( 'Saved Radar key unchanged', 'droix-stripe-id-check' ) ); ?>">
									<button type="button" class="button dsic-toggle-password" data-target="dsic_radar_test_secret_key">
										<span class="dashicons dashicons-visibility"></span>
									</button>
								</div>
								<p class="description">
									<?php esc_html_e( 'Test secret key for Radar/Charges API access. Leave blank to keep the saved key unchanged.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="dsic_radar_live_webhook_secret"><?php esc_html_e( 'Live Webhook Secret', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<div class="dsic-password-field">
									<input type="password" id="dsic_radar_live_webhook_secret" name="dsic_radar_live_webhook_secret"
										value=""
										class="regular-text"
										placeholder="<?php echo esc_attr( empty( $radar_live_webhook_secret ) ? 'whsec_...' : __( 'Saved Radar webhook secret unchanged', 'droix-stripe-id-check' ) ); ?>">
									<button type="button" class="button dsic-toggle-password" data-target="dsic_radar_live_webhook_secret">
										<span class="dashicons dashicons-visibility"></span>
									</button>
								</div>
								<p class="description">
									<?php esc_html_e( 'Webhook signing secret for the Radar Stripe account. Leave blank to keep the saved secret unchanged.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="dsic_radar_test_webhook_secret"><?php esc_html_e( 'Test Webhook Secret', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<div class="dsic-password-field">
									<input type="password" id="dsic_radar_test_webhook_secret" name="dsic_radar_test_webhook_secret"
										value=""
										class="regular-text"
										placeholder="<?php echo esc_attr( empty( $radar_test_webhook_secret ) ? 'whsec_...' : __( 'Saved Radar webhook secret unchanged', 'droix-stripe-id-check' ) ); ?>">
									<button type="button" class="button dsic-toggle-password" data-target="dsic_radar_test_webhook_secret">
										<span class="dashicons dashicons-visibility"></span>
									</button>
								</div>
								<p class="description">
									<?php esc_html_e( 'Test webhook signing secret for the Radar Stripe account. Leave blank to keep the saved secret unchanged.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Radar Connection Test', 'droix-stripe-id-check' ); ?>
							</th>
							<td>
								<button type="button" id="dsic-test-radar-connection" class="button button-secondary">
									<?php esc_html_e( 'Test Radar Connection', 'droix-stripe-id-check' ); ?>
								</button>
								<span id="dsic-radar-connection-status" class="dsic-status"></span>
								<p class="description">
									<?php esc_html_e( 'Tests the Radar API key by querying charges. Save settings first.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- Setup Help Accordions -->
				<div class="dsic-setup-help">
					<h3><?php esc_html_e( 'Setup Instructions', 'droix-stripe-id-check' ); ?></h3>

					<!-- Accordion: Getting API Keys -->
					<div class="dsic-accordion">
						<button type="button" class="dsic-accordion-header">
							<span class="dashicons dashicons-admin-network"></span>
							<?php esc_html_e( 'How to get Stripe API Keys', 'droix-stripe-id-check' ); ?>
							<span class="dsic-accordion-icon dashicons dashicons-arrow-down-alt2"></span>
						</button>
						<div class="dsic-accordion-content">
							<ol>
								<li><?php esc_html_e( 'Log in to your Stripe Dashboard at', 'droix-stripe-id-check' ); ?> <a href="https://dashboard.stripe.com" target="_blank" rel="noopener">dashboard.stripe.com</a></li>
								<li><?php esc_html_e( 'Click "Developers" in the left sidebar', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Click "API keys"', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Copy your "Publishable key" (starts with pk_)', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Click "Reveal" next to "Secret key" and copy it (starts with sk_)', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Paste both keys in the fields above and save', 'droix-stripe-id-check' ); ?></li>
							</ol>
							<p class="dsic-note">
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e( 'For testing, use test mode keys (pk_test_... and sk_test_...). For production, use live keys (pk_live_... and sk_live_...).', 'droix-stripe-id-check' ); ?>
							</p>
						</div>
					</div>

					<!-- Accordion: Setting up Webhook -->
					<div class="dsic-accordion">
						<button type="button" class="dsic-accordion-header">
							<span class="dashicons dashicons-randomize"></span>
							<?php esc_html_e( 'How to set up the Webhook', 'droix-stripe-id-check' ); ?>
							<span class="dsic-accordion-icon dashicons dashicons-arrow-down-alt2"></span>
						</button>
						<div class="dsic-accordion-content">
							<ol>
								<li><?php esc_html_e( 'Log in to your Stripe Dashboard at', 'droix-stripe-id-check' ); ?> <a href="https://dashboard.stripe.com" target="_blank" rel="noopener">dashboard.stripe.com</a></li>
								<li><?php esc_html_e( 'Click "Developers" in the left sidebar', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Click "Webhooks"', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Click "+ Add endpoint"', 'droix-stripe-id-check' ); ?></li>
								<li>
									<?php esc_html_e( 'In "Endpoint URL", paste this webhook URL:', 'droix-stripe-id-check' ); ?>
									<code class="dsic-inline-code"><?php echo esc_url( $webhook_url ); ?></code>
								</li>
								<li>
									<?php esc_html_e( 'Click "Select events" and choose these events:', 'droix-stripe-id-check' ); ?>
									<ul class="dsic-event-list">
										<li><code>identity.verification_session.verified</code> — <?php esc_html_e( 'Verification passed', 'droix-stripe-id-check' ); ?></li>
										<li><code>identity.verification_session.requires_input</code> — <?php esc_html_e( 'Verification failed', 'droix-stripe-id-check' ); ?></li>
										<li><code>identity.verification_session.canceled</code> — <?php esc_html_e( 'Session cancelled', 'droix-stripe-id-check' ); ?></li>
										<li><code>identity.verification_session.redacted</code> — <?php esc_html_e( 'Data deleted from Stripe', 'droix-stripe-id-check' ); ?></li>
									</ul>
									<p class="dsic-note" style="margin-top: 8px;">
										<span class="dashicons dashicons-lightbulb"></span>
										<?php esc_html_e( 'Tip: You can select all identity.verification_session.* events to ensure all updates are captured.', 'droix-stripe-id-check' ); ?>
									</p>
								</li>
								<li><?php esc_html_e( 'Click "Add endpoint" to save', 'droix-stripe-id-check' ); ?></li>
							</ol>
							<p class="dsic-note">
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e( 'Make sure to add the webhook in both Test mode and Live mode if you plan to use both.', 'droix-stripe-id-check' ); ?>
							</p>
						</div>
					</div>

					<!-- Accordion: Getting Webhook Secret -->
					<div class="dsic-accordion">
						<button type="button" class="dsic-accordion-header">
							<span class="dashicons dashicons-lock"></span>
							<?php esc_html_e( 'How to get the Webhook Secret', 'droix-stripe-id-check' ); ?>
							<span class="dsic-accordion-icon dashicons dashicons-arrow-down-alt2"></span>
						</button>
						<div class="dsic-accordion-content">
							<ol>
								<li><?php esc_html_e( 'After creating the webhook endpoint (see above), click on the endpoint URL in the webhooks list', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'In the endpoint details page, find "Signing secret"', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Click "Reveal" to show the signing secret (starts with whsec_)', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Copy the signing secret and paste it in the "Webhook Secret" field above', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Click "Save Changes"', 'droix-stripe-id-check' ); ?></li>
							</ol>
							<p class="dsic-note dsic-note-warning">
								<span class="dashicons dashicons-warning"></span>
								<?php esc_html_e( 'The webhook secret is essential for security. Without it, the plugin cannot verify that webhook events are genuinely from Stripe.', 'droix-stripe-id-check' ); ?>
							</p>
						</div>
					</div>

					<!-- Accordion: Enabling Stripe Identity -->
					<div class="dsic-accordion">
						<button type="button" class="dsic-accordion-header">
							<span class="dashicons dashicons-id"></span>
							<?php esc_html_e( 'How to enable Stripe Identity', 'droix-stripe-id-check' ); ?>
							<span class="dsic-accordion-icon dashicons dashicons-arrow-down-alt2"></span>
						</button>
						<div class="dsic-accordion-content">
							<ol>
								<li><?php esc_html_e( 'Log in to your Stripe Dashboard', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Go to "More +" in the left sidebar and click "Identity"', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'If Identity is not enabled, click "Get started" or "Request access"', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Complete any required business verification steps', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Once approved, you can create verification sessions', 'droix-stripe-id-check' ); ?></li>
							</ol>
							<p class="dsic-note">
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e( 'Stripe Identity requires account approval. Test mode is available immediately, but live mode may require additional verification of your business.', 'droix-stripe-id-check' ); ?>
							</p>
						</div>
					</div>
				</div>

			<?php elseif ( 'emails' === $current_tab ) : ?>
				<!-- Email Templates Tab -->
				<?php
				$email_types = array(
					'verification_request' => array(
						'title'       => __( '1. Verification Request Email', 'droix-stripe-id-check' ),
						'description' => __( 'Sent to customers when ID verification is requested for their order.', 'droix-stripe-id-check' ),
					),
					'verification_passed'  => array(
						'title'       => __( '2. Verification Passed Email', 'droix-stripe-id-check' ),
						'description' => __( 'Sent to customers when their ID has been successfully verified.', 'droix-stripe-id-check' ),
					),
					'verification_failed'  => array(
						'title'       => __( '3. Verification Failed Email', 'droix-stripe-id-check' ),
						'description' => __( 'Sent to customers when ID verification fails or requires attention.', 'droix-stripe-id-check' ),
					),
					'data_redaction'       => array(
						'title'       => __( '4. Data Deletion Confirmation Email', 'droix-stripe-id-check' ),
						'description' => __( 'Sent to customers when their verification data has been deleted from Stripe (manual deletion or automatic 30-day deletion).', 'droix-stripe-id-check' ),
					),
				);

				// Available placeholders for each email type.
				$placeholders = array(
					'verification_request' => array(
						'[dsic_customer_name]'       => __( 'Customer full name', 'droix-stripe-id-check' ),
						'[dsic_customer_first_name]' => __( 'Customer first name', 'droix-stripe-id-check' ),
						'[dsic_customer_email]'      => __( 'Customer email address', 'droix-stripe-id-check' ),
						'[dsic_order_number]'        => __( 'Order number', 'droix-stripe-id-check' ),
						'[dsic_order_date]'          => __( 'Order date', 'droix-stripe-id-check' ),
						'[dsic_order_total]'         => __( 'Order total amount', 'droix-stripe-id-check' ),
						'[dsic_verification_link]'   => __( 'Verification button/link', 'droix-stripe-id-check' ),
						'[dsic_site_name]'           => __( 'Your site name', 'droix-stripe-id-check' ),
						'{order_number}'             => __( 'Order number (for subject)', 'droix-stripe-id-check' ),
						'{site_title}'               => __( 'Site name (for subject)', 'droix-stripe-id-check' ),
					),
					'verification_passed' => array(
						'[dsic_customer_name]'       => __( 'Customer full name', 'droix-stripe-id-check' ),
						'[dsic_customer_first_name]' => __( 'Customer first name', 'droix-stripe-id-check' ),
						'[dsic_customer_email]'      => __( 'Customer email address', 'droix-stripe-id-check' ),
						'[dsic_order_number]'        => __( 'Order number', 'droix-stripe-id-check' ),
						'[dsic_order_date]'          => __( 'Order date', 'droix-stripe-id-check' ),
						'[dsic_order_total]'         => __( 'Order total amount', 'droix-stripe-id-check' ),
						'[dsic_verification_status]' => __( 'Verification status', 'droix-stripe-id-check' ),
						'[dsic_site_name]'           => __( 'Your site name', 'droix-stripe-id-check' ),
						'{order_number}'             => __( 'Order number (for subject)', 'droix-stripe-id-check' ),
						'{site_title}'               => __( 'Site name (for subject)', 'droix-stripe-id-check' ),
					),
					'verification_failed' => array(
						'[dsic_customer_name]'       => __( 'Customer full name', 'droix-stripe-id-check' ),
						'[dsic_customer_first_name]' => __( 'Customer first name', 'droix-stripe-id-check' ),
						'[dsic_customer_email]'      => __( 'Customer email address', 'droix-stripe-id-check' ),
						'[dsic_order_number]'        => __( 'Order number', 'droix-stripe-id-check' ),
						'[dsic_order_date]'          => __( 'Order date', 'droix-stripe-id-check' ),
						'[dsic_order_total]'         => __( 'Order total amount', 'droix-stripe-id-check' ),
						'[dsic_verification_status]' => __( 'Verification status', 'droix-stripe-id-check' ),
						'[dsic_site_name]'           => __( 'Your site name', 'droix-stripe-id-check' ),
						'{order_number}'             => __( 'Order number (for subject)', 'droix-stripe-id-check' ),
						'{site_title}'               => __( 'Site name (for subject)', 'droix-stripe-id-check' ),
					),
					'data_redaction' => array(
						'[dsic_customer_name]'       => __( 'Customer full name', 'droix-stripe-id-check' ),
						'[dsic_customer_first_name]' => __( 'Customer first name', 'droix-stripe-id-check' ),
						'[dsic_customer_email]'      => __( 'Customer email address', 'droix-stripe-id-check' ),
						'[dsic_order_number]'        => __( 'Order number', 'droix-stripe-id-check' ),
						'[dsic_order_date]'          => __( 'Order date', 'droix-stripe-id-check' ),
						'[dsic_site_name]'           => __( 'Your site name', 'droix-stripe-id-check' ),
						'[dsic_support_email]'       => __( 'Support email address', 'droix-stripe-id-check' ),
						'{order_number}'             => __( 'Order number (for subject)', 'droix-stripe-id-check' ),
						'{site_title}'               => __( 'Site name (for subject)', 'droix-stripe-id-check' ),
					),
				);
				?>

				<div class="dsic-email-templates">
					<div class="dsic-email-templates-header">
						<p class="dsic-email-intro"><?php esc_html_e( 'Customize email templates sent to customers during the ID verification process. Use the Visual editor for rich formatting or Code editor for HTML.', 'droix-stripe-id-check' ); ?></p>
						<button type="button" id="dsic-reset-all-templates" class="button">
							<?php esc_html_e( 'Reset All to Defaults', 'droix-stripe-id-check' ); ?>
						</button>
					</div>
					<p class="description" style="margin-top:6px;margin-bottom:16px;"><?php esc_html_e( 'Note: Template changes made via WooCommerce → Settings → Emails are separate from the customizations managed here. Use Reset to restore the defaults managed by this plugin.', 'droix-stripe-id-check' ); ?></p>

					<?php foreach ( $email_types as $type => $info ) : ?>
						<?php $template = $settings_obj->get_email_template( $type ); ?>
						<div class="dsic-template-section" data-email-type="<?php echo esc_attr( $type ); ?>">
							<div class="dsic-template-header">
								<h2><?php echo esc_html( $info['title'] ); ?></h2>
								<span class="dsic-template-status <?php echo $template['enabled'] ? 'active' : 'inactive'; ?>">
									<?php echo $template['enabled'] ? esc_html__( 'Active', 'droix-stripe-id-check' ) : esc_html__( 'Inactive', 'droix-stripe-id-check' ); ?>
								</span>
							</div>

							<p class="dsic-template-desc"><?php echo esc_html( $info['description'] ); ?></p>

							<div class="dsic-field-group dsic-field-inline">
								<label for="dsic_email_<?php echo esc_attr( $type ); ?>_enabled">
									<?php esc_html_e( 'Enable this email', 'droix-stripe-id-check' ); ?>
								</label>
								<label class="dsic-toggle">
									<input type="checkbox"
										id="dsic_email_<?php echo esc_attr( $type ); ?>_enabled"
										name="dsic_email_<?php echo esc_attr( $type ); ?>_enabled"
										value="1"
										<?php checked( $template['enabled'], true ); ?>>
									<span class="dsic-toggle-slider"></span>
								</label>
							</div>

							<div class="dsic-field-group">
								<label for="dsic_email_<?php echo esc_attr( $type ); ?>_subject">
									<?php esc_html_e( 'Email Subject', 'droix-stripe-id-check' ); ?>
								</label>
								<input type="text"
									id="dsic_email_<?php echo esc_attr( $type ); ?>_subject"
									name="dsic_email_<?php echo esc_attr( $type ); ?>_subject"
									value="<?php echo esc_attr( $template['subject'] ); ?>"
									class="large-text">
							</div>

							<div class="dsic-field-group">
								<label for="dsic_email_<?php echo esc_attr( $type ); ?>_heading">
									<?php esc_html_e( 'Email Heading', 'droix-stripe-id-check' ); ?>
								</label>
								<input type="text"
									id="dsic_email_<?php echo esc_attr( $type ); ?>_heading"
									name="dsic_email_<?php echo esc_attr( $type ); ?>_heading"
									value="<?php echo esc_attr( $template['heading'] ); ?>"
									class="large-text">
							</div>

							<div class="dsic-field-group">
								<label for="dsic_email_<?php echo esc_attr( $type ); ?>_body">
									<?php esc_html_e( 'Email Content', 'droix-stripe-id-check' ); ?>
								</label>
								<?php
								wp_editor(
									$template['body'],
									'dsic_email_' . $type . '_body',
									array(
										'textarea_name' => 'dsic_email_' . $type . '_body',
										'textarea_rows' => 12,
										'media_buttons' => false,
										'teeny'         => false,
										'quicktags'     => array( 'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close' ),
									)
								);
								?>
								<p class="dsic-field-description">
									<?php esc_html_e( 'The message content. Switch between Visual and Text modes using the tabs above. Use placeholders from the list below.', 'droix-stripe-id-check' ); ?>
								</p>
							</div>

							<!-- Available Placeholders -->
							<div class="dsic-available-placeholders">
								<h4><?php esc_html_e( 'Available Placeholders', 'droix-stripe-id-check' ); ?></h4>
								<p class="dsic-placeholders-hint">
									<?php esc_html_e( 'Click any placeholder below to insert it at the cursor position in the editor.', 'droix-stripe-id-check' ); ?>
								</p>
								<div class="dsic-placeholder-list">
									<?php foreach ( $placeholders[ $type ] as $placeholder => $description ) : ?>
										<div class="dsic-placeholder-item">
											<code class="dsic-placeholder-code" data-editor="dsic_email_<?php echo esc_attr( $type ); ?>_body" title="<?php esc_attr_e( 'Click to insert', 'droix-stripe-id-check' ); ?>"><?php echo esc_html( $placeholder ); ?></code>
											<span class="dsic-placeholder-desc"><?php echo esc_html( $description ); ?></span>
										</div>
									<?php endforeach; ?>
								</div>
							</div>

							<!-- Template Actions -->
							<div class="dsic-template-actions">
								<div class="dsic-test-email-input">
									<label for="dsic_test_email_<?php echo esc_attr( $type ); ?>"><?php esc_html_e( 'Test Email:', 'droix-stripe-id-check' ); ?></label>
									<input type="email"
										id="dsic_test_email_<?php echo esc_attr( $type ); ?>"
										placeholder="<?php esc_attr_e( 'your@email.com', 'droix-stripe-id-check' ); ?>"
										value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
								</div>
								<button type="button" class="button dsic-send-test-email" data-type="<?php echo esc_attr( $type ); ?>">
									<?php esc_html_e( 'Send Test Email', 'droix-stripe-id-check' ); ?>
								</button>
								<button type="button" class="button dsic-reset-template" data-type="<?php echo esc_attr( $type ); ?>">
									<?php esc_html_e( 'Reset to Default', 'droix-stripe-id-check' ); ?>
								</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

			<?php elseif ( 'data_retention' === $current_tab ) : ?>
				<!-- Data Retention Tab -->
				<?php
				$auto_redaction_enabled = get_option( 'dsic_auto_redaction_enabled', '0' );
				$redaction_days         = get_option( 'dsic_redaction_days', '30' );
				$batch_size             = get_option( 'dsic_redaction_batch_size', '20' );
				$schedule_time          = get_option( 'dsic_redaction_schedule_time', '01:00' );
				$notify_customer        = get_option( 'dsic_redaction_notify_customer', '1' );

				// Get next scheduled time for display.
				$next_run = null;
				if ( function_exists( 'as_next_scheduled_action' ) ) {
					$next_run = as_next_scheduled_action( 'dsic_daily_redaction_check' );
				} elseif ( wp_next_scheduled( 'dsic_daily_redaction_check' ) ) {
					$next_run = wp_next_scheduled( 'dsic_daily_redaction_check' );
				}
				?>

				<div class="dsic-note dsic-note-info" style="margin-bottom: 20px;">
					<span class="dashicons dashicons-info"></span>
					<strong><?php esc_html_e( 'GDPR Compliance', 'droix-stripe-id-check' ); ?></strong>
					<p>
						<?php esc_html_e( 'This feature automatically deletes verification data from Stripe after a specified retention period (GDPR Article 17: Right to Erasure). Verification data includes ID documents, selfies, and personal information collected during identity verification.', 'droix-stripe-id-check' ); ?>
					</p>
				</div>

				<table class="form-table dsic-form-table">
					<tbody>
						<!-- Enable Auto-Redaction -->
						<tr>
							<th scope="row">
								<label for="dsic_auto_redaction_enabled"><?php esc_html_e( 'Enable Auto-Redaction', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<label class="dsic-toggle">
									<input type="checkbox" id="dsic_auto_redaction_enabled" name="dsic_auto_redaction_enabled" value="1"
										<?php checked( $auto_redaction_enabled, '1' ); ?>>
									<span class="dsic-toggle-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Automatically delete verification data from Stripe after the retention period expires.', 'droix-stripe-id-check' ); ?>
								</p>
								<?php if ( $auto_redaction_enabled && $next_run ) : ?>
									<p class="description" style="color: #2271b1;">
										<span class="dashicons dashicons-clock"></span>
										<?php
										/* translators: %s: formatted next run date and time */
										printf( esc_html__( 'Next scheduled run: %s', 'droix-stripe-id-check' ), esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ) ) );
										?>
									</p>
								<?php endif; ?>
							</td>
						</tr>

						<!-- Retention Period -->
						<tr>
							<th scope="row">
								<label for="dsic_redaction_days"><?php esc_html_e( 'Retention Period', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<input type="number" id="dsic_redaction_days" name="dsic_redaction_days"
									value="<?php echo esc_attr( $redaction_days ); ?>"
									min="30" max="365" step="1" class="small-text">
								<span><?php esc_html_e( 'days', 'droix-stripe-id-check' ); ?></span>
								<p class="description">
									<?php esc_html_e( 'Number of days to retain verification data before automatic deletion (30-365 days). Default: 30 days.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>

						<!-- Batch Size -->
						<tr>
							<th scope="row">
								<label for="dsic_redaction_batch_size"><?php esc_html_e( 'Daily Batch Size', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<input type="number" id="dsic_redaction_batch_size" name="dsic_redaction_batch_size"
									value="<?php echo esc_attr( $batch_size ); ?>"
									min="10" max="50" step="1" class="small-text">
								<span><?php esc_html_e( 'orders per day', 'droix-stripe-id-check' ); ?></span>
								<p class="description">
									<?php esc_html_e( 'Maximum number of orders to process per day (10-50). Orders are processed in batches to avoid API rate limits.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>

						<!-- Schedule Time -->
						<tr>
							<th scope="row">
								<label for="dsic_redaction_schedule_time"><?php esc_html_e( 'Daily Run Time', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<input type="time" id="dsic_redaction_schedule_time" name="dsic_redaction_schedule_time"
									value="<?php echo esc_attr( $schedule_time ); ?>">
								<p class="description">
									<?php esc_html_e( 'Time of day to run the daily redaction check (24-hour format). Redaction actions are staggered over 1 hour.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>

						<!-- Customer Notification -->
						<tr>
							<th scope="row">
								<label for="dsic_redaction_notify_customer"><?php esc_html_e( 'Customer Notification', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<label class="dsic-toggle">
									<input type="checkbox" id="dsic_redaction_notify_customer" name="dsic_redaction_notify_customer" value="1"
										<?php checked( $notify_customer, '1' ); ?>>
									<span class="dsic-toggle-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Send email notification to customers after their verification data has been deleted.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- How It Works -->
				<div style="margin-top: 30px;">
					<h3><?php esc_html_e( 'How Auto-Redaction Works', 'droix-stripe-id-check' ); ?></h3>

					<div class="dsic-accordion" style="margin-top: 15px;">
						<button type="button" class="dsic-accordion-header">
							<span class="dashicons dashicons-clock"></span>
							<?php esc_html_e( 'Daily Processing Schedule', 'droix-stripe-id-check' ); ?>
							<span class="dsic-accordion-icon dashicons dashicons-arrow-down-alt2"></span>
						</button>
						<div class="dsic-accordion-content">
							<ol>
								<li><?php esc_html_e( 'Every day at the scheduled time, the system checks for verification data older than the retention period', 'droix-stripe-id-check' ); ?></li>
								<li>
									<?php
									/* translators: %d: batch size setting */
									printf( esc_html__( 'Up to %d orders are selected for processing (oldest first)', 'droix-stripe-id-check' ), esc_html( $batch_size ) );
									?>
								</li>
								<li><?php esc_html_e( 'Redaction actions are staggered across 1 hour to avoid API rate limits', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Each order is processed individually with automatic retry on failure (up to 3 attempts)', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Success/failure is logged to the compliance audit log', 'droix-stripe-id-check' ); ?></li>
							</ol>
							<p class="dsic-note">
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e( 'Redaction is performed using Stripe\'s official Data Redaction API, which permanently deletes verification data within 4 days.', 'droix-stripe-id-check' ); ?>
							</p>
						</div>
					</div>

					<div class="dsic-accordion">
						<button type="button" class="dsic-accordion-header">
							<span class="dashicons dashicons-shield"></span>
							<?php esc_html_e( 'What Data is Deleted?', 'droix-stripe-id-check' ); ?>
							<span class="dsic-accordion-icon dashicons dashicons-arrow-down-alt2"></span>
						</button>
						<div class="dsic-accordion-content">
							<p><?php esc_html_e( 'When verification data is redacted from Stripe, the following is permanently deleted:', 'droix-stripe-id-check' ); ?></p>
							<ul style="list-style: disc; padding-left: 20px;">
								<li><?php esc_html_e( 'Uploaded ID document images', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Selfie/biometric photos', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Extracted personal information (name, DOB, ID number)', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'All verification session data', 'droix-stripe-id-check' ); ?></li>
							</ul>
							<p class="dsic-note dsic-note-warning">
								<span class="dashicons dashicons-warning"></span>
								<?php esc_html_e( 'The verification status (verified/failed) and completion timestamp remain in WooCommerce order meta for compliance tracking. Only sensitive personal data is removed.', 'droix-stripe-id-check' ); ?>
							</p>
						</div>
					</div>

					<div class="dsic-accordion">
						<button type="button" class="dsic-accordion-header">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Which Orders are Eligible?', 'droix-stripe-id-check' ); ?>
							<span class="dsic-accordion-icon dashicons dashicons-arrow-down-alt2"></span>
						</button>
						<div class="dsic-accordion-content">
							<p><?php esc_html_e( 'Orders are eligible for auto-redaction when ALL of these conditions are met:', 'droix-stripe-id-check' ); ?></p>
							<ul style="list-style: disc; padding-left: 20px;">
								<li>
									<?php
									/* translators: %d: number of retention days */
									printf( esc_html__( 'Verification was completed more than %d days ago', 'droix-stripe-id-check' ), esc_html( $redaction_days ) );
									?>
								</li>
								<li><?php esc_html_e( 'Verification status is either "verified" or "failed"', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Data has not already been redacted', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'No pending redaction action exists', 'droix-stripe-id-check' ); ?></li>
							</ul>
						</div>
					</div>

					<div class="dsic-accordion">
						<button type="button" class="dsic-accordion-header">
							<span class="dashicons dashicons-admin-tools"></span>
							<?php esc_html_e( 'Manual Redaction', 'droix-stripe-id-check' ); ?>
							<span class="dsic-accordion-icon dashicons dashicons-arrow-down-alt2"></span>
						</button>
						<div class="dsic-accordion-content">
							<p><?php esc_html_e( 'You can manually trigger redaction for individual orders:', 'droix-stripe-id-check' ); ?></p>
							<ol>
								<li><?php esc_html_e( 'Go to WooCommerce → Orders', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Open the order that has been verified', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Find the "Stripe ID Verification" meta box on the right', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Click "Delete Verification Data" button', 'droix-stripe-id-check' ); ?></li>
							</ol>
							<p class="dsic-note">
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e( 'Manual redaction is useful for customer data deletion requests (GDPR Right to Erasure) or when you need to delete data before the retention period expires.', 'droix-stripe-id-check' ); ?>
							</p>
						</div>
					</div>

					<div class="dsic-accordion">
						<button type="button" class="dsic-accordion-header">
							<span class="dashicons dashicons-chart-line"></span>
							<?php esc_html_e( 'Compliance Reporting', 'droix-stripe-id-check' ); ?>
							<span class="dsic-accordion-icon dashicons dashicons-arrow-down-alt2"></span>
						</button>
						<div class="dsic-accordion-content">
							<p><?php esc_html_e( 'All redaction activities are logged to the compliance audit log:', 'droix-stripe-id-check' ); ?></p>
							<ul style="list-style: disc; padding-left: 20px;">
								<li><?php esc_html_e( 'Order ID and redaction timestamp', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Success/failure status', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Days data was retained', 'droix-stripe-id-check' ); ?></li>
								<li><?php esc_html_e( 'Error details (if failed)', 'droix-stripe-id-check' ); ?></li>
							</ul>
							<p>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsic-compliance-report' ) ); ?>" class="button button-primary">
									<?php esc_html_e( 'View Compliance Report', 'droix-stripe-id-check' ); ?>
								</a>
							</p>
						</div>
					</div>
				</div>

			<?php elseif ( 'linnworks' === $current_tab ) : ?>
				<!-- Linnworks Integration Tab -->
				<?php
				$lw_enabled     = get_option( 'dsic_linnworks_enabled', '0' );
				$lw_app_id      = get_option( 'dsic_linnworks_app_id', '' );
				$lw_app_secret  = get_option( 'dsic_linnworks_app_secret', '' );
				$lw_token       = get_option( 'dsic_linnworks_token', '' );
				$lw_api_key     = get_option( 'dsic_linnworks_api_key', '' );
				$lw_api_hash    = get_option( 'dsic_linnworks_api_key_hash', '' );
				$lw_has_api_key = ! empty( $lw_api_hash ) || ! empty( $lw_api_key );
				$lw_auto_lock   = get_option( 'dsic_linnworks_auto_lock', '1' );
				$lw_auto_unlock = get_option( 'dsic_linnworks_auto_unlock', '1' );

				// Check if REST API class exists.
				$rest_api_url = '';
				if ( class_exists( 'DSIC_REST_API' ) ) {
					$rest_api_url = DSIC_REST_API::get_api_url();
				}
				?>

				<!-- Enable Integration Section -->
				<h2 class="dsic-section-title"><?php esc_html_e( 'Linnworks Integration', 'droix-stripe-id-check' ); ?></h2>
				<p class="dsic-section-description">
					<?php esc_html_e( 'Integrate with Linnworks to automatically lock/unlock orders when ID verification is requested or completed.', 'droix-stripe-id-check' ); ?>
				</p>

				<table class="form-table dsic-form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="dsic_linnworks_enabled"><?php esc_html_e( 'Enable Integration', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<label class="dsic-toggle">
									<input type="checkbox" id="dsic_linnworks_enabled" name="dsic_linnworks_enabled" value="1"
										<?php checked( $lw_enabled, '1' ); ?>>
									<span class="dsic-toggle-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Enable or disable the Linnworks integration.', 'droix-stripe-id-check' ); ?>
								</p>
								<?php if ( $lw_enabled === '1' ) : ?>
									<p class="dsic-mode-indicator">
										<span class="dsic-badge dsic-badge-success">
											<span class="dashicons dashicons-yes-alt"></span>
											<?php esc_html_e( 'Integration Enabled', 'droix-stripe-id-check' ); ?>
										</span>
									</p>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- Linnworks API Credentials Section -->
				<h2 class="dsic-section-title"><?php esc_html_e( 'Linnworks API Credentials', 'droix-stripe-id-check' ); ?></h2>
				<p class="dsic-section-description">
					<?php esc_html_e( 'Enter your Linnworks API credentials. These are used to authenticate with the Linnworks API.', 'droix-stripe-id-check' ); ?>
					<a href="https://apps.linnworks.net/" target="_blank" rel="noopener">
						<?php esc_html_e( 'Get credentials from Linnworks Apps', 'droix-stripe-id-check' ); ?>
						<span class="dashicons dashicons-external" style="text-decoration: none;"></span>
					</a>
				</p>

				<table class="form-table dsic-form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="dsic_linnworks_app_id"><?php esc_html_e( 'Application ID', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<input type="text" id="dsic_linnworks_app_id" name="dsic_linnworks_app_id"
									value="<?php echo esc_attr( $lw_app_id ); ?>"
									class="regular-text"
									placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
								<p class="description">
									<?php esc_html_e( 'Your Linnworks Application ID (GUID format).', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="dsic_linnworks_app_secret"><?php esc_html_e( 'Application Secret', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<div class="dsic-password-field">
									<input type="password" id="dsic_linnworks_app_secret" name="dsic_linnworks_app_secret"
										value=""
										class="regular-text"
										placeholder="<?php echo esc_attr( empty( $lw_app_secret ) ? 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' : __( 'Saved application secret unchanged', 'droix-stripe-id-check' ) ); ?>">
									<button type="button" class="button dsic-toggle-password" data-target="dsic_linnworks_app_secret">
										<span class="dashicons dashicons-visibility"></span>
									</button>
								</div>
								<p class="description">
									<?php esc_html_e( 'Your Linnworks Application Secret. Leave blank to keep the saved secret unchanged.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="dsic_linnworks_token"><?php esc_html_e( 'Installation Token', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<div class="dsic-password-field">
									<input type="password" id="dsic_linnworks_token" name="dsic_linnworks_token"
										value=""
										class="regular-text"
										placeholder="<?php echo esc_attr( empty( $lw_token ) ? 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' : __( 'Saved installation token unchanged', 'droix-stripe-id-check' ) ); ?>">
									<button type="button" class="button dsic-toggle-password" data-target="dsic_linnworks_token">
										<span class="dashicons dashicons-visibility"></span>
									</button>
								</div>
								<p class="description">
									<?php esc_html_e( 'Your Linnworks Installation Token. Leave blank to keep the saved token unchanged.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Connection Test', 'droix-stripe-id-check' ); ?>
							</th>
							<td>
								<button type="button" id="dsic-test-linnworks-connection" class="button button-secondary">
									<?php esc_html_e( 'Test Connection', 'droix-stripe-id-check' ); ?>
								</button>
								<span id="dsic-linnworks-connection-status" class="dsic-status"></span>
								<p class="description">
									<?php esc_html_e( 'Test the connection to Linnworks API. Save settings first.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- Test Lock/Unlock Section -->
				<h2 class="dsic-section-title"><?php esc_html_e( 'Test Lock/Unlock', 'droix-stripe-id-check' ); ?></h2>
				<p class="dsic-section-description">
					<?php esc_html_e( 'Test the lock/unlock functionality on an existing order in Linnworks. Enter the order reference number exactly as it appears in Linnworks.', 'droix-stripe-id-check' ); ?>
				</p>

				<table class="form-table dsic-form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="dsic_test_order_id"><?php esc_html_e( 'Order Reference', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<input type="text" id="dsic_test_order_id" class="regular-text" style="width: 200px;"
									placeholder="e.g., 1272309">
								<p class="description">
									<?php esc_html_e( 'Enter the order reference number as it appears in Linnworks.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Test Actions', 'droix-stripe-id-check' ); ?>
							</th>
							<td>
								<div class="dsic-test-buttons" style="display: flex; gap: 10px; align-items: center;">
									<button type="button" id="dsic-test-linnworks-search" class="button button-secondary">
										<span class="dashicons dashicons-search" style="vertical-align: middle;"></span>
										<?php esc_html_e( 'Search Order', 'droix-stripe-id-check' ); ?>
									</button>
									<button type="button" id="dsic-test-linnworks-lock" class="button button-secondary">
										<span class="dashicons dashicons-lock" style="vertical-align: middle;"></span>
										<?php esc_html_e( 'Lock Order', 'droix-stripe-id-check' ); ?>
									</button>
									<button type="button" id="dsic-test-linnworks-unlock" class="button button-secondary">
										<span class="dashicons dashicons-unlock" style="vertical-align: middle;"></span>
										<?php esc_html_e( 'Unlock Order', 'droix-stripe-id-check' ); ?>
									</button>
								</div>
								<p class="description" style="margin-top: 8px;">
									<?php esc_html_e( 'Search finds the order in Linnworks. Lock/Unlock will change the order status.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Output', 'droix-stripe-id-check' ); ?>
							</th>
							<td>
								<div id="dsic-linnworks-test-output" class="dsic-test-output" style="min-height: 150px; max-height: 400px; overflow-y: auto; background: #1d2327; color: #50c878; font-family: monospace; font-size: 12px; padding: 15px; border-radius: 4px; white-space: pre-wrap; word-break: break-all;">
<span style="color: #888;"><?php esc_html_e( '// Output will appear here...', 'droix-stripe-id-check' ); ?></span>
								</div>
								<p style="margin-top: 8px;">
									<button type="button" id="dsic-clear-test-output" class="button button-link" style="color: #b32d2e;">
										<span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
										<?php esc_html_e( 'Clear Output', 'droix-stripe-id-check' ); ?>
									</button>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- Automation Settings Section -->
				<h2 class="dsic-section-title"><?php esc_html_e( 'Automation Settings', 'droix-stripe-id-check' ); ?></h2>
				<p class="dsic-section-description">
					<?php esc_html_e( 'Configure automatic order locking/unlocking based on verification status.', 'droix-stripe-id-check' ); ?>
				</p>

				<table class="form-table dsic-form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="dsic_linnworks_auto_lock"><?php esc_html_e( 'Auto-Lock Orders', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<label class="dsic-toggle">
									<input type="checkbox" id="dsic_linnworks_auto_lock" name="dsic_linnworks_auto_lock" value="1"
										<?php checked( $lw_auto_lock, '1' ); ?>>
									<span class="dsic-toggle-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Automatically lock orders in Linnworks when ID verification is requested.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="dsic_linnworks_auto_unlock"><?php esc_html_e( 'Auto-Unlock Orders', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<label class="dsic-toggle">
									<input type="checkbox" id="dsic_linnworks_auto_unlock" name="dsic_linnworks_auto_unlock" value="1"
										<?php checked( $lw_auto_unlock, '1' ); ?>>
									<span class="dsic-toggle-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Automatically unlock orders in Linnworks when ID verification passes.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- External REST API Section -->
				<h2 class="dsic-section-title"><?php esc_html_e( 'External REST API', 'droix-stripe-id-check' ); ?></h2>
				<p class="dsic-section-description">
					<?php esc_html_e( 'Use these endpoints to trigger lock/unlock operations from external systems (e.g., WooCommerce app, Linnworks macros).', 'droix-stripe-id-check' ); ?>
				</p>

				<table class="form-table dsic-form-table">
					<tbody>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'API Key', 'droix-stripe-id-check' ); ?>
							</th>
							<td>
								<?php if ( ! empty( $dsic_generated_linnworks_api_key ) ) : ?>
									<div class="dsic-copy-field">
										<code id="dsic-linnworks-api-key"><?php echo esc_html( $dsic_generated_linnworks_api_key ); ?></code>
										<button type="button" class="button dsic-copy-button" data-copy="dsic-linnworks-api-key">
											<span class="dashicons dashicons-clipboard"></span>
											<?php esc_html_e( 'Copy', 'droix-stripe-id-check' ); ?>
										</button>
									</div>
									<p class="description dsic-warning">
										<?php esc_html_e( 'Copy this key now. For security it is stored as a hash and will not be shown again.', 'droix-stripe-id-check' ); ?>
									</p>
								<?php elseif ( $lw_has_api_key ) : ?>
									<p class="dsic-key-configured">
										<?php esc_html_e( 'API key configured. Generate a new key if you need to copy it again.', 'droix-stripe-id-check' ); ?>
									</p>
								<?php else : ?>
									<p class="dsic-no-key"><?php esc_html_e( 'No API key generated yet.', 'droix-stripe-id-check' ); ?></p>
								<?php endif; ?>
								<p>
									<label>
										<input type="checkbox" name="dsic_linnworks_generate_api_key" value="1">
										<?php esc_html_e( 'Generate new API key on save', 'droix-stripe-id-check' ); ?>
									</label>
								</p>
								<p class="description dsic-warning">
									<?php esc_html_e( 'Warning: Generating a new key will invalidate the old one. Update all external integrations with the new key.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'API Base URL', 'droix-stripe-id-check' ); ?>
							</th>
							<td>
								<div class="dsic-copy-field">
									<code id="dsic-linnworks-api-url"><?php echo esc_url( $rest_api_url ); ?></code>
									<button type="button" class="button dsic-copy-button" data-copy="dsic-linnworks-api-url">
										<span class="dashicons dashicons-clipboard"></span>
										<?php esc_html_e( 'Copy', 'droix-stripe-id-check' ); ?>
									</button>
								</div>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- API Endpoints Documentation -->
				<div class="dsic-setup-help">
					<h3><?php esc_html_e( 'Available Endpoints', 'droix-stripe-id-check' ); ?></h3>

					<div class="dsic-accordion">
						<button type="button" class="dsic-accordion-header">
							<span class="dashicons dashicons-lock"></span>
							<?php esc_html_e( 'POST /linnworks/lock - Lock an order', 'droix-stripe-id-check' ); ?>
							<span class="dsic-accordion-icon dashicons dashicons-arrow-down-alt2"></span>
						</button>
						<div class="dsic-accordion-content">
							<p><?php esc_html_e( 'Locks an order in Linnworks by WooCommerce order ID.', 'droix-stripe-id-check' ); ?></p>
							<h4><?php esc_html_e( 'Request', 'droix-stripe-id-check' ); ?></h4>
							<pre class="dsic-code-block">POST <?php echo esc_url( $rest_api_url ); ?>lock
Content-Type: application/json
X-DSIC-API-Key: your_api_key

{
  "order_id": 12345
}</pre>
							<h4><?php esc_html_e( 'Response', 'droix-stripe-id-check' ); ?></h4>
							<pre class="dsic-code-block">{
  "success": true,
  "message": "Order #12345 locked successfully.",
  "wc_order_id": 12345,
  "linnworks_id": "guid-here",
  "timestamp": "2024-01-15T14:30:00+00:00"
}</pre>
						</div>
					</div>

					<div class="dsic-accordion">
						<button type="button" class="dsic-accordion-header">
							<span class="dashicons dashicons-unlock"></span>
							<?php esc_html_e( 'POST /linnworks/unlock - Unlock an order', 'droix-stripe-id-check' ); ?>
							<span class="dsic-accordion-icon dashicons dashicons-arrow-down-alt2"></span>
						</button>
						<div class="dsic-accordion-content">
							<p><?php esc_html_e( 'Unlocks an order in Linnworks by WooCommerce order ID.', 'droix-stripe-id-check' ); ?></p>
							<h4><?php esc_html_e( 'Request', 'droix-stripe-id-check' ); ?></h4>
							<pre class="dsic-code-block">POST <?php echo esc_url( $rest_api_url ); ?>unlock
Content-Type: application/json
X-DSIC-API-Key: your_api_key

{
  "order_id": 12345,
  "reason": "Verification passed"  // optional
}</pre>
							<h4><?php esc_html_e( 'Response', 'droix-stripe-id-check' ); ?></h4>
							<pre class="dsic-code-block">{
  "success": true,
  "message": "Order #12345 unlocked successfully.",
  "wc_order_id": 12345,
  "linnworks_id": "guid-here",
  "timestamp": "2024-01-15T14:30:00+00:00"
}</pre>
						</div>
					</div>

					<div class="dsic-accordion">
						<button type="button" class="dsic-accordion-header">
							<span class="dashicons dashicons-info"></span>
							<?php esc_html_e( 'GET /linnworks/status - Integration status', 'droix-stripe-id-check' ); ?>
							<span class="dsic-accordion-icon dashicons dashicons-arrow-down-alt2"></span>
						</button>
						<div class="dsic-accordion-content">
							<p><?php esc_html_e( 'Check the current status of the Linnworks integration.', 'droix-stripe-id-check' ); ?></p>
							<h4><?php esc_html_e( 'Request', 'droix-stripe-id-check' ); ?></h4>
							<pre class="dsic-code-block">GET <?php echo esc_url( $rest_api_url ); ?>status
X-DSIC-API-Key: your_api_key</pre>
							<h4><?php esc_html_e( 'Response', 'droix-stripe-id-check' ); ?></h4>
							<pre class="dsic-code-block">{
  "enabled": true,
  "configured": true,
  "connected": true,
  "connection": {
    "success": true,
    "server": "eu-ext.linnworks.net"
  },
  "stats_today": {
    "total": 5,
    "success": 4,
    "failed": 1
  },
  "timestamp": "2024-01-15T14:30:00+00:00"
}</pre>
						</div>
					</div>

					<div class="dsic-accordion">
						<button type="button" class="dsic-accordion-header">
							<span class="dashicons dashicons-search"></span>
							<?php esc_html_e( 'GET /linnworks/order/{id} - Order verification status', 'droix-stripe-id-check' ); ?>
							<span class="dsic-accordion-icon dashicons dashicons-arrow-down-alt2"></span>
						</button>
						<div class="dsic-accordion-content">
							<p><?php esc_html_e( 'Get the verification and Linnworks status for a specific order.', 'droix-stripe-id-check' ); ?></p>
							<h4><?php esc_html_e( 'Request', 'droix-stripe-id-check' ); ?></h4>
							<pre class="dsic-code-block">GET <?php echo esc_url( $rest_api_url ); ?>order/12345
X-DSIC-API-Key: your_api_key</pre>
							<h4><?php esc_html_e( 'Response', 'droix-stripe-id-check' ); ?></h4>
							<pre class="dsic-code-block">{
  "wc_order_id": 12345,
  "verification_status": "verified",
  "last_linnworks_action": {
    "action": "unlock",
    "status": "success",
    "timestamp": "2024-01-15 14:30:00",
    "lw_order_id": "guid-here"
  },
  "history_count": 2,
  "timestamp": "2024-01-15T14:30:00+00:00"
}</pre>
						</div>
					</div>

					<div class="dsic-note" style="margin-top: 15px;">
						<span class="dashicons dashicons-info"></span>
							<?php esc_html_e( 'Authentication: Include your API key in the X-DSIC-API-Key header. Query string and request-body API keys are not accepted.', 'droix-stripe-id-check' ); ?>
					</div>
				</div>

				<!-- View Logs Link -->
				<div class="dsic-linnworks-log-link" style="margin-top: 20px; padding: 15px; background: #f6f7f7; border-radius: 4px;">
					<p>
						<span class="dashicons dashicons-list-view" style="color: #2271b1;"></span>
						<strong><?php esc_html_e( 'View detailed operation logs:', 'droix-stripe-id-check' ); ?></strong>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsic-linnworks-log' ) ); ?>" class="button button-small" style="margin-left: 10px;">
							<?php esc_html_e( 'Linnworks Log', 'droix-stripe-id-check' ); ?>
						</a>
					</p>
				</div>

			<?php elseif ( 'slack' === $current_tab ) : ?>
				<!-- Slack Notifications Tab -->
				<?php
					$slack_webhook_url      = get_option( 'dsic_slack_webhook_url', '' );
					$slack_notify_triggered = get_option( 'dsic_slack_notify_triggered', '0' );
					$slack_notify_passed    = get_option( 'dsic_slack_notify_passed', '0' );
					$slack_notify_failed    = get_option( 'dsic_slack_notify_failed', '0' );
					$slack_notify_efw       = get_option( 'dsic_slack_notify_efw', '0' );
				?>
				<div class="dsic-section">
					<h2><?php esc_html_e( 'Slack Notifications', 'droix-stripe-id-check' ); ?></h2>
					<p><?php esc_html_e( 'Receive real-time Slack messages for ID verification events. Messages include a link to the WooCommerce order and the Stripe dashboard.', 'droix-stripe-id-check' ); ?></p>
				</div>

				<table class="form-table dsic-form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="dsic_slack_webhook_url"><?php esc_html_e( 'Incoming Webhook URL', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
									<input type="url" id="dsic_slack_webhook_url" name="dsic_slack_webhook_url"
										value=""
										class="regular-text" placeholder="<?php echo esc_attr( empty( $slack_webhook_url ) ? 'https://hooks.slack.com/services/...' : __( 'Saved webhook URL unchanged', 'droix-stripe-id-check' ) ); ?>" />
								<button type="button" id="dsic-test-slack-connection" class="button" style="margin-left:8px;">
									<?php esc_html_e( 'Test Connection', 'droix-stripe-id-check' ); ?>
								</button>
								<span id="dsic-slack-test-result" style="margin-left:8px;"></span>
								<p class="description">
										<?php esc_html_e( 'Create an Incoming Webhook in your Slack app settings and paste the URL here. Leave blank to keep the saved webhook URL unchanged.', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Notify On', 'droix-stripe-id-check' ); ?></th>
							<td>
								<fieldset>
									<label>
										<input type="checkbox" name="dsic_slack_notify_triggered" value="1"
											<?php checked( '1', $slack_notify_triggered ); ?> />
										<?php esc_html_e( 'ID check triggered (includes trigger reason)', 'droix-stripe-id-check' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="dsic_slack_notify_passed" value="1"
											<?php checked( '1', $slack_notify_passed ); ?> />
										<?php esc_html_e( 'ID check passed', 'droix-stripe-id-check' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="dsic_slack_notify_failed" value="1"
											<?php checked( '1', $slack_notify_failed ); ?> />
										<?php esc_html_e( 'ID check failed', 'droix-stripe-id-check' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="dsic_slack_notify_efw" value="1"
											<?php checked( '1', $slack_notify_efw ); ?> />
										<?php esc_html_e( 'Early Fraud Warning received (includes already-dispatched flag)', 'droix-stripe-id-check' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>
					</tbody>
				</table>

				<script>
				jQuery(function($) {
					$('#dsic-test-slack-connection').on('click', function() {
						var $btn    = $(this);
						var $result = $('#dsic-slack-test-result');
						$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Testing…', 'droix-stripe-id-check' ) ); ?>');
						$result.text('').css('color', '');

						$.post(ajaxurl, {
							action: 'dsic_test_slack_connection',
							nonce:  dsic_ajax.nonce
						}, function(response) {
							if (response.success) {
								$result.css('color', 'green').text(response.data.message);
							} else {
								$result.css('color', 'red').text(response.data.message);
							}
						}).fail(function() {
							$result.css('color', 'red').text('<?php echo esc_js( __( 'Request failed.', 'droix-stripe-id-check' ) ); ?>');
						}).always(function() {
							$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Test Connection', 'droix-stripe-id-check' ) ); ?>');
						});
					});
				});
				</script>

			<?php elseif ( 'debug' === $current_tab ) : ?>
				<!-- Debug Tab -->
				<table class="form-table dsic-form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="dsic_debug_mode"><?php esc_html_e( 'Debug Mode', 'droix-stripe-id-check' ); ?></label>
							</th>
							<td>
								<label class="dsic-toggle">
									<input type="checkbox" id="dsic_debug_mode" name="dsic_debug_mode" value="1"
										<?php checked( $settings['debug_mode'], true ); ?>>
									<span class="dsic-toggle-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Enable debug logging. Logs will be written to wp-content/uploads/dsic-logs/', 'droix-stripe-id-check' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'System Info', 'droix-stripe-id-check' ); ?>
							</th>
							<td>
								<div class="dsic-system-info">
									<p><strong><?php esc_html_e( 'Plugin Version:', 'droix-stripe-id-check' ); ?></strong> <?php echo esc_html( DSIC_VERSION ); ?></p>
									<p><strong><?php esc_html_e( 'PHP Version:', 'droix-stripe-id-check' ); ?></strong> <?php echo esc_html( PHP_VERSION ); ?></p>
									<p><strong><?php esc_html_e( 'WordPress Version:', 'droix-stripe-id-check' ); ?></strong> <?php echo esc_html( get_bloginfo( 'version' ) ); ?></p>
									<p><strong><?php esc_html_e( 'WooCommerce Version:', 'droix-stripe-id-check' ); ?></strong> <?php echo esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : 'N/A' ); ?></p>
									<p><strong><?php esc_html_e( 'Stripe API Version:', 'droix-stripe-id-check' ); ?></strong> <?php echo esc_html( DSIC_STRIPE_API_VERSION ); ?></p>
								</div>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'Recent Logs', 'droix-stripe-id-check' ); ?>
							</th>
							<td>
								<div class="dsic-log-viewer">
									<textarea id="dsic-log-content" readonly rows="15" class="large-text code"><?php
										$logs = DSIC_Logger::get_recent_logs( 50 );
										if ( ! empty( $logs ) ) {
											echo esc_textarea( implode( "\n", $logs ) );
										} else {
											esc_html_e( 'No log entries found.', 'droix-stripe-id-check' );
										}
									?></textarea>
								</div>
								<p class="dsic-log-actions">
									<button type="button" id="dsic-refresh-logs" class="button button-secondary">
										<span class="dashicons dashicons-update"></span>
										<?php esc_html_e( 'Refresh', 'droix-stripe-id-check' ); ?>
									</button>
									<button type="button" id="dsic-clear-logs" class="button button-secondary dsic-danger">
										<span class="dashicons dashicons-trash"></span>
										<?php esc_html_e( 'Clear Logs', 'droix-stripe-id-check' ); ?>
									</button>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

			<?php endif; ?>

			<?php submit_button( __( 'Save Settings', 'droix-stripe-id-check' ), 'primary', 'dsic_save_settings' ); ?>
		</form>
	</div>
</div>
