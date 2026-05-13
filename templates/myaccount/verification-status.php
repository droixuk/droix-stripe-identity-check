<?php
/**
 * Verification Status Template.
 *
 * Shows verification progress on customer order page.
 *
 * This template can be overridden by copying it to:
 * yourtheme/woocommerce/myaccount/verification-status.php
 *
 * @package    DSIC
 * @subpackage DSIC/templates/myaccount
 * @version    1.4.1
 *
 * @var WC_Order $order            Order object.
 * @var string   $status           Verification status (pending/verified/failed).
 * @var int      $requested_at     Request timestamp.
 * @var int      $completed_at     Completion timestamp.
 * @var string   $error_message    Error message if failed.
 * @var string   $verification_url URL to start verification.
 */

defined( 'ABSPATH' ) || exit;

// Determine step states.
$step_1_complete = true; // Always complete if we're showing this.
$step_2_complete = in_array( $status, array( 'verified', 'failed' ), true );
$step_3_complete = 'verified' === $status;
$step_3_failed   = 'failed' === $status;

// Helper function to get WPML translated string or fallback to default.
$t = function( $key, $default ) {
	if ( class_exists( 'DSIC_WPML' ) && DSIC_WPML::is_multilingual() ) {
		return DSIC_WPML::get_frontend_string( $key, $default );
	}
	return $default;
};

// Status labels with WPML support.
$status_labels = array(
	'pending'  => $t( 'Status Awaiting', __( 'Awaiting Verification', 'droix-stripe-id-check' ) ),
	'verified' => $t( 'Status Verified', __( 'Verified Successfully', 'droix-stripe-id-check' ) ),
	'failed'   => $t( 'Status Failed', __( 'Verification Failed', 'droix-stripe-id-check' ) ),
);

$status_label = $status_labels[ $status ] ?? ucfirst( $status );
?>

<section class="dsic-verification-section woocommerce-verification-details">
	<h2 class="dsic-verification-title">
		<?php echo esc_html( $t( 'Title', __( 'Identity Verification', 'droix-stripe-id-check' ) ) ); ?>
	</h2>

	<!-- Progress Steps -->
	<div class="dsic-progress-tracker">
		<div class="dsic-progress-step <?php echo $step_1_complete ? 'complete' : ''; ?>">
			<div class="dsic-step-indicator">
				<?php if ( $step_1_complete ) : ?>
					<svg viewBox="0 0 24 24" class="dsic-check-icon"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
				<?php else : ?>
					<span>1</span>
				<?php endif; ?>
			</div>
			<div class="dsic-step-label"><?php echo esc_html( $t( 'Step Requested', __( 'Requested', 'droix-stripe-id-check' ) ) ); ?></div>
		</div>

		<div class="dsic-progress-line <?php echo $step_2_complete ? 'complete' : ''; ?>"></div>

		<div class="dsic-progress-step <?php echo $step_2_complete ? 'complete' : ( 'pending' === $status ? 'active' : '' ); ?>">
			<div class="dsic-step-indicator">
				<?php if ( $step_2_complete ) : ?>
					<svg viewBox="0 0 24 24" class="dsic-check-icon"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
				<?php else : ?>
					<span>2</span>
				<?php endif; ?>
			</div>
			<div class="dsic-step-label"><?php echo esc_html( $t( 'Step In Progress', __( 'In Progress', 'droix-stripe-id-check' ) ) ); ?></div>
		</div>

		<div class="dsic-progress-line <?php echo $step_3_complete ? 'complete' : ( $step_3_failed ? 'failed' : '' ); ?>"></div>

		<div class="dsic-progress-step <?php echo $step_3_complete ? 'complete' : ( $step_3_failed ? 'failed' : '' ); ?>">
			<div class="dsic-step-indicator">
				<?php if ( $step_3_complete ) : ?>
					<svg viewBox="0 0 24 24" class="dsic-check-icon"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
				<?php elseif ( $step_3_failed ) : ?>
					<svg viewBox="0 0 24 24" class="dsic-x-icon"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
				<?php else : ?>
					<span>3</span>
				<?php endif; ?>
			</div>
			<div class="dsic-step-label"><?php echo esc_html( $t( 'Step Complete', __( 'Complete', 'droix-stripe-id-check' ) ) ); ?></div>
		</div>
	</div>

	<!-- Status Box -->
	<div class="dsic-status-box dsic-status-<?php echo esc_attr( $status ); ?>">
		<div class="dsic-status-header">
			<span class="dsic-status-badge"><?php echo esc_html( $status_label ); ?></span>
		</div>

		<div class="dsic-status-content">
			<?php if ( 'pending' === $status ) : ?>
				<p class="dsic-status-message">
					<?php echo esc_html( $t( 'Pending Message', __( 'To process your order, we need to verify your identity. This is a quick and secure process powered by Stripe.', 'droix-stripe-id-check' ) ) ); ?>
				</p>

				<div class="dsic-what-you-need">
					<strong><?php echo esc_html( $t( 'What You Need', __( 'What you\'ll need:', 'droix-stripe-id-check' ) ) ); ?></strong>
					<ul>
						<li><?php echo esc_html( $t( 'Need ID', __( 'A valid government-issued ID (passport, driving licence, or ID card)', 'droix-stripe-id-check' ) ) ); ?></li>
						<li><?php echo esc_html( $t( 'Need Camera', __( 'A device with a camera', 'droix-stripe-id-check' ) ) ); ?></li>
						<li><?php echo esc_html( $t( 'Need Time', __( 'About 2-3 minutes', 'droix-stripe-id-check' ) ) ); ?></li>
					</ul>
				</div>

				<?php if ( $verification_url ) : ?>
					<a href="<?php echo esc_url( $verification_url ); ?>" class="dsic-verify-button button" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( $t( 'Verify Button', __( 'Verify My Identity', 'droix-stripe-id-check' ) ) ); ?>
					</a>
				<?php endif; ?>

			<?php elseif ( 'verified' === $status ) : ?>
				<p class="dsic-status-message dsic-success-message">
					<svg viewBox="0 0 24 24" class="dsic-success-icon"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
					<?php echo esc_html( $t( 'Verified Message', __( 'Your identity has been successfully verified. Your order is now being processed.', 'droix-stripe-id-check' ) ) ); ?>
				</p>

			<?php elseif ( 'failed' === $status ) : ?>
				<p class="dsic-status-message dsic-error-message">
					<?php echo esc_html( $t( 'Failed Message', __( 'Unfortunately, we were unable to verify your identity.', 'droix-stripe-id-check' ) ) ); ?>
				</p>

				<?php if ( $error_message ) : ?>
					<p class="dsic-error-reason">
						<strong><?php echo esc_html( $t( 'Reason Label', __( 'Reason:', 'droix-stripe-id-check' ) ) ); ?></strong>
						<?php echo esc_html( $error_message ); ?>
					</p>
				<?php endif; ?>

				<p class="dsic-next-steps">
					<?php echo esc_html( $t( 'Contact Support', __( 'Please contact our support team for assistance with your order.', 'droix-stripe-id-check' ) ) ); ?>
				</p>
			<?php endif; ?>
		</div>

		<!-- Timestamps -->
		<div class="dsic-status-meta">
			<?php if ( $requested_at ) : ?>
				<span class="dsic-timestamp">
					<strong><?php echo esc_html( $t( 'Requested Label', __( 'Requested:', 'droix-stripe-id-check' ) ) ); ?></strong>
					<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $requested_at ) ); ?>
				</span>
			<?php endif; ?>

			<?php if ( $completed_at && in_array( $status, array( 'verified', 'failed' ), true ) ) : ?>
				<span class="dsic-timestamp">
					<strong><?php echo esc_html( 'verified' === $status ? $t( 'Verified Label', __( 'Verified:', 'droix-stripe-id-check' ) ) : $t( 'Failed Label', __( 'Failed:', 'droix-stripe-id-check' ) ) ); ?></strong>
					<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $completed_at ) ); ?>
				</span>
			<?php endif; ?>
		</div>
	</div>
</section>
