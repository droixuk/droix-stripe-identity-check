<?php
/**
 * Order handler class.
 *
 * Handles WooCommerce order integration for ID verification.
 *
 * @package    DSIC
 * @subpackage DSIC/admin
 * @since      0.0.1
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_Order_Handler
 *
 * @since 0.0.1
 */
class DSIC_Order_Handler {

	/**
	 * Order meta keys.
	 */
	const META_VERIFICATION_TOKEN        = '_dsic_verification_token';
	const META_VERIFICATION_SESSION      = '_dsic_verification_session_id';
	const META_VERIFICATION_STATUS       = '_dsic_verification_status';
	const META_VERIFICATION_REQUESTED    = '_dsic_verification_requested';
	const META_VERIFICATION_LINK_CLICKED = '_dsic_link_clicked';
	const META_VERIFICATION_COMPLETED    = '_dsic_verification_completed';
	const META_VERIFICATION_ERROR        = '_dsic_verification_error_msg';
	const META_VERIFICATION_ATTEMPTS     = '_dsic_verification_attempts';
	const META_DATA_REDACTION_STATUS     = '_dsic_data_redaction_status';
	const META_DATA_REDACTION_REQUESTED  = '_dsic_data_redaction_requested';

	/**
	 * Constructor.
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	private function init_hooks(): void {
		// Order actions.
		add_filter( 'woocommerce_order_actions', array( $this, 'add_order_actions' ), 10, 2 );
		add_action( 'woocommerce_order_action_dsic_request_verification', array( $this, 'handle_request_verification' ) );
		add_action( 'woocommerce_order_action_dsic_resend_verification', array( $this, 'handle_resend_verification' ) );
		add_action( 'woocommerce_order_action_dsic_cancel_verification', array( $this, 'handle_cancel_verification' ) );

		// Order meta box.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

		// Order list column.
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_order_column' ), 10, 2 );

		// HPOS compatibility.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_order_column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_order_column_hpos' ), 10, 2 );

		// AJAX handlers.
		add_action( 'wp_ajax_dsic_request_verification', array( $this, 'ajax_request_verification' ) );
		add_action( 'wp_ajax_dsic_resend_verification', array( $this, 'ajax_resend_verification' ) );
		add_action( 'wp_ajax_dsic_cancel_verification', array( $this, 'ajax_cancel_verification' ) );
		add_action( 'wp_ajax_dsic_approve_verification', array( $this, 'ajax_approve_verification' ) );
		add_action( 'wp_ajax_dsic_redact_verification', array( $this, 'ajax_redact_verification' ) );
	}

	/**
	 * Add verification actions to order actions dropdown.
	 *
	 * @since 0.0.1
	 * @param array    $actions Available actions.
	 * @param WC_Order $order   Order object.
	 * @return array Modified actions.
	 */
	public function add_order_actions( array $actions, $order ): array {
		if ( ! $order instanceof WC_Order ) {
			return $actions;
		}

		// Check if plugin is enabled.
		if ( ! get_option( 'dsic_enabled', false ) ) {
			return $actions;
		}

		$status = $order->get_meta( self::META_VERIFICATION_STATUS );

		if ( empty( $status ) ) {
			// No verification requested yet.
			$actions['dsic_request_verification'] = __( 'Request ID Verification', 'droix-stripe-id-check' );
		} elseif ( 'pending' === $status ) {
			// Verification pending.
			$actions['dsic_resend_verification'] = __( 'Resend Verification Email', 'droix-stripe-id-check' );
			$actions['dsic_cancel_verification'] = __( 'Cancel Verification', 'droix-stripe-id-check' );
		} elseif ( 'failed' === $status ) {
			// Verification failed - allow retry.
			$actions['dsic_request_verification'] = __( 'Request New Verification', 'droix-stripe-id-check' );
		}

		return $actions;
	}

	/**
	 * Handle request verification action.
	 *
	 * @since 0.0.1
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function handle_request_verification( WC_Order $order ): void {
		$result = $this->request_verification( $order->get_id() );

		if ( is_wp_error( $result ) ) {
			// Add admin notice for error.
			set_transient(
				'dsic_admin_notice_' . get_current_user_id(),
				array(
					'type'    => 'error',
					'message' => $result->get_error_message(),
				),
				30
			);
		}
	}

	/**
	 * Handle resend verification action.
	 *
	 * @since 0.0.1
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function handle_resend_verification( WC_Order $order ): void {
		$this->resend_verification_email( $order->get_id() );
	}

	/**
	 * Handle cancel verification action.
	 *
	 * @since 0.0.1
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function handle_cancel_verification( WC_Order $order ): void {
		$this->cancel_verification( $order->get_id() );
	}

	/**
	 * Request verification for an order.
	 *
	 * @since 0.0.1
	 * @param int $order_id Order ID.
	 * @return array|WP_Error Verification data or error.
	 */
	public function request_verification( int $order_id ) {
		DSIC_Logger::info( '=== Starting verification request for order #' . $order_id . ' ===' );

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			DSIC_Logger::error( 'Verification request failed: Invalid order #' . $order_id );
			return new WP_Error( 'dsic_invalid_order', __( 'Invalid order.', 'droix-stripe-id-check' ) );
		}

		DSIC_Logger::info( 'Order #' . $order_id . ' found. Customer: ' . $order->get_billing_email() );

		// Check if customer has any previously verified orders.
		// Stripe may automatically approve if customer was verified before.
		$customer_email        = $order->get_billing_email();
		$previous_verified_orders = wc_get_orders(
			array(
				'billing_email' => $customer_email,
				'meta_query'    => array(
					array(
						'key'   => self::META_VERIFICATION_STATUS,
						'value' => 'verified',
					),
				),
				'limit'         => 1,
				'exclude'       => array( $order_id ), // Exclude current order.
			)
		);

		if ( ! empty( $previous_verified_orders ) ) {
			$previous_order = $previous_verified_orders[0];
			DSIC_Logger::warning(
				sprintf(
					'Customer %s was previously verified on order #%d. Stripe may automatically approve this verification.',
					$customer_email,
					$previous_order->get_id()
				)
			);

			// Add admin note about previous verification.
			$order->add_order_note(
				sprintf(
					__( 'ℹ️ Note: Customer was previously verified on order #%d. Stripe may automatically approve this request.', 'droix-stripe-id-check' ),
					$previous_order->get_id()
				)
			);
			$order->save();
		}

		// Generate secure token.
		$token = wp_generate_password( 32, false );

		// Store token in order meta.
		$order->update_meta_data( self::META_VERIFICATION_TOKEN, $token );
		$order->update_meta_data( self::META_VERIFICATION_STATUS, 'pending' );
		$order->update_meta_data( self::META_VERIFICATION_REQUESTED, time() );

		// Increment attempts.
		$attempts = (int) $order->get_meta( self::META_VERIFICATION_ATTEMPTS );
		$order->update_meta_data( self::META_VERIFICATION_ATTEMPTS, $attempts + 1 );

		$order->save();

		// Build verification URL.
		$verification_url = add_query_arg(
			array(
				'wc-api'   => 'dsic_initiate_verification',
				'order_id' => $order_id,
				'token'    => $token,
			),
			home_url( '/' )
		);

		// Add detailed order note.
		$current_user = wp_get_current_user();
		$order_url    = $order->get_edit_order_url();
		$note_parts   = array(
			sprintf(
				/* translators: %s: Admin user name */
				__( '🔐 ID verification requested by %s', 'droix-stripe-id-check' ),
				$current_user->display_name
			),
			sprintf(
				/* translators: %s: Date and time */
				__( '📅 Date: %s', 'droix-stripe-id-check' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
			),
			sprintf(
				/* translators: %s: Customer email */
				__( '📧 Customer: %s', 'droix-stripe-id-check' ),
				$order->get_billing_email()
			),
			sprintf(
				/* translators: %s: Order URL */
				__( '🔗 Order: %s', 'droix-stripe-id-check' ),
				$order_url
			),
			__( '📋 Verification email sent to customer.', 'droix-stripe-id-check' ),
			sprintf(
				/* translators: %s: Verification URL */
				__( '🔗 Verification Link: %s', 'droix-stripe-id-check' ),
				$verification_url
			),
		);
		$order->add_order_note( implode( "\n", $note_parts ) );

		// Set order to on-hold for verification.
		// Don't change if already completed, cancelled, refunded, or failed.
		$skip_statuses = array( 'completed', 'cancelled', 'refunded', 'failed' );
		if ( ! $order->has_status( $skip_statuses ) && ! $order->has_status( 'on-hold' ) ) {
			$order->update_status( 'on-hold', __( 'Awaiting ID verification.', 'droix-stripe-id-check' ) );
		}

		// Ensure WooCommerce mailer is loaded (triggers woocommerce_email_classes filter).
		WC()->mailer();

		// Log Linnworks integration status before triggering action.
		$linnworks_enabled = class_exists( 'DSIC_Linnworks_API' ) && DSIC_Linnworks_API::is_enabled();
		DSIC_Logger::info( 'Linnworks integration enabled: ' . ( $linnworks_enabled ? 'YES' : 'NO' ) );

		// Trigger verification request email and other actions (like Linnworks lock).
		DSIC_Logger::info( 'Triggering dsic_verification_requested action for order #' . $order_id );
		do_action( 'dsic_verification_requested', $order_id, $verification_url );

		DSIC_Logger::info( '=== Verification request completed for order #' . $order_id . ' ===' );

		return array(
			'order_id'         => $order_id,
			'token'            => $token,
			'verification_url' => $verification_url,
		);
	}

	/**
	 * Resend verification email.
	 *
	 * @since 0.0.1
	 * @param int $order_id Order ID.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function resend_verification_email( int $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'dsic_invalid_order', __( 'Invalid order.', 'droix-stripe-id-check' ) );
		}

		$token = $order->get_meta( self::META_VERIFICATION_TOKEN );

		if ( empty( $token ) ) {
			return new WP_Error( 'dsic_no_token', __( 'No verification token found.', 'droix-stripe-id-check' ) );
		}

		// Build verification URL.
		$verification_url = add_query_arg(
			array(
				'wc-api'   => 'dsic_initiate_verification',
				'order_id' => $order_id,
				'token'    => $token,
			),
			home_url( '/' )
		);

		// Add order note.
		$order->add_order_note(
			sprintf(
				/* translators: %s: Admin user name */
				__( 'Verification email resent by %s.', 'droix-stripe-id-check' ),
				wp_get_current_user()->display_name
			)
		);

		// Ensure WooCommerce mailer is loaded (triggers woocommerce_email_classes filter).
		WC()->mailer();

		// Trigger verification request email.
		do_action( 'dsic_verification_requested', $order_id, $verification_url );

		DSIC_Logger::info( 'Verification email resent for order #' . $order_id );

		return true;
	}

	/**
	 * Cancel verification.
	 *
	 * @since 0.0.1
	 * @param int $order_id Order ID.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function cancel_verification( int $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			DSIC_Logger::error( 'Cancel verification failed: Invalid order #' . $order_id );
			return new WP_Error( 'dsic_invalid_order', __( 'Invalid order.', 'droix-stripe-id-check' ) );
		}

		DSIC_Logger::info( 'Cancelling verification for order #' . $order_id );

		$session_id = $order->get_meta( self::META_VERIFICATION_SESSION );

		// Cancel session in Stripe if exists.
		if ( ! empty( $session_id ) ) {
			$api = new DSIC_Stripe_API();
			$api->cancel_verification_session( $session_id );
			DSIC_Logger::info( 'Cancelled Stripe session ' . $session_id . ' for order #' . $order_id );
		}

		// Clear verification meta and checkout auto-verification flags so stale enforcement stops after cancellation.
		$order->delete_meta_data( self::META_VERIFICATION_TOKEN );
		$order->delete_meta_data( self::META_VERIFICATION_SESSION );
		$order->delete_meta_data( self::META_VERIFICATION_STATUS );
		$order->delete_meta_data( '_dsic_auto_verification_triggered' );
		$order->delete_meta_data( '_dsic_auto_verification_pending' );
		$order->delete_meta_data( '_dsic_auto_verification_reason' );
		$order->save();

		// Restore order status to processing if it was on-hold.
		if ( $order->has_status( 'on-hold' ) ) {
			$order->update_status( 'processing', __( 'ID verification cancelled - returning to processing.', 'droix-stripe-id-check' ) );
			DSIC_Logger::info( 'Order #' . $order_id . ' status changed from on-hold to processing' );
		}

		// Add order note.
		$current_user = wp_get_current_user();
		$note_parts   = array(
			sprintf(
				/* translators: %s: Admin user name */
				__( '⛔ ID verification cancelled by %s', 'droix-stripe-id-check' ),
				$current_user->display_name
			),
			sprintf(
				/* translators: %s: Date and time */
				__( '📅 Date: %s', 'droix-stripe-id-check' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
			),
			__( '📋 Order status restored to processing.', 'droix-stripe-id-check' ),
		);
		$order->add_order_note( implode( "\n", $note_parts ) );

		DSIC_Logger::info( 'Verification cancelled for order #' . $order_id );

		// Trigger cancelled action (for Linnworks unlock).
		DSIC_Logger::info( 'Triggering dsic_verification_cancelled action for order #' . $order_id );
		do_action( 'dsic_verification_cancelled', $order_id );

		return true;
	}

	/**
	 * Manually approve a failed verification.
	 *
	 * Allows admins to override a failed verification when they determine
	 * the customer is legitimate despite the automated check failing.
	 *
	 * @since 1.7.0
	 * @param int $order_id Order ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function approve_verification( int $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			DSIC_Logger::error( 'Manual approval failed: Invalid order #' . $order_id );
			return new WP_Error( 'dsic_invalid_order', __( 'Invalid order.', 'droix-stripe-id-check' ) );
		}

		$current_status = $order->get_meta( self::META_VERIFICATION_STATUS );

		// Only allow approval of failed verifications.
		if ( 'failed' !== $current_status ) {
			DSIC_Logger::error( 'Manual approval failed for order #' . $order_id . ': Status is not failed (was: ' . $current_status . ')' );
			return new WP_Error( 'dsic_invalid_status', __( 'Only failed verifications can be manually approved.', 'droix-stripe-id-check' ) );
		}

		// Get admin info for audit trail.
		$current_user = wp_get_current_user();
		$admin_name   = $current_user->display_name ?: $current_user->user_login;

		DSIC_Logger::info( 'Manual approval initiated for order #' . $order_id . ' by ' . $admin_name );

		// Update verification status.
		$order->update_meta_data( self::META_VERIFICATION_STATUS, 'verified' );
		$order->update_meta_data( self::META_VERIFICATION_COMPLETED, time() );
		$order->update_meta_data( '_dsic_manual_approval', true );
		$order->update_meta_data( '_dsic_manual_approval_by', $admin_name );
		$order->update_meta_data( '_dsic_manual_approval_date', time() );

		// Add detailed order note.
		$note_parts = array(
			__( '✅ ID Verification MANUALLY APPROVED', 'droix-stripe-id-check' ),
			sprintf(
				/* translators: %s: Admin user name */
				__( '📋 Approved by: %s', 'droix-stripe-id-check' ),
				$admin_name
			),
			sprintf(
				/* translators: %s: Date and time */
				__( '📅 Date: %s', 'droix-stripe-id-check' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
			),
		);
		$order->add_order_note( implode( "\n", $note_parts ) );

		// Change order status from on-hold to processing (if applicable).
		if ( 'on-hold' === $order->get_status() ) {
			$order->set_status( 'processing', __( 'ID verification manually approved.', 'droix-stripe-id-check' ) );
		}

		$order->save();

		DSIC_Logger::info( 'Verification manually approved for order #' . $order_id . ' by ' . $admin_name );

		// Trigger verification passed hook (sends confirmation email to customer).
		do_action( 'dsic_verification_passed', $order_id );

		// Trigger CRM notification.
		do_action( 'dsic_crm_notification', $order_id, 'manual_approval', '', array( 'approved_by' => $admin_name ) );

		return true;
	}

	/**
	 * Add verification meta box to order screen.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function add_meta_box(): void {
		// Determine screen ID with safe HPOS detection.
		$screen = 'shop_order';

		try {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
				&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$screen = wc_get_page_screen_id( 'shop-order' );
			}
		} catch ( \Exception $e ) {
			// Fall back to legacy screen if HPOS detection fails.
			DSIC_Logger::debug( 'HPOS detection failed: ' . $e->getMessage() );
		}

		add_meta_box(
			'dsic-verification-meta-box',
			__( 'Stripe ID Verification', 'droix-stripe-id-check' ),
			array( $this, 'render_meta_box' ),
			$screen,
			'side',
			'high'
		);
	}

	/**
	 * Render verification meta box.
	 *
	 * @since 0.0.1
	 * @param WP_Post|WC_Order $post_or_order Post or order object.
	 * @return void
	 */
	public function render_meta_box( $post_or_order ): void {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );

		if ( ! $order ) {
			echo '<p>' . esc_html__( 'Order not found.', 'droix-stripe-id-check' ) . '</p>';
			return;
		}

		// Check if plugin is enabled.
		if ( ! get_option( 'dsic_enabled', false ) ) {
			echo '<p style="color: #666;">' . esc_html__( 'Plugin is disabled. Enable it in settings.', 'droix-stripe-id-check' ) . '</p>';
			return;
		}

		$status             = $order->get_meta( self::META_VERIFICATION_STATUS );
		$session_id         = $order->get_meta( self::META_VERIFICATION_SESSION );
		$requested          = $order->get_meta( self::META_VERIFICATION_REQUESTED );
		$completed          = $order->get_meta( self::META_VERIFICATION_COMPLETED );
		$error              = $order->get_meta( self::META_VERIFICATION_ERROR );
		$attempts           = $order->get_meta( self::META_VERIFICATION_ATTEMPTS );
		$redaction_status   = $order->get_meta( self::META_DATA_REDACTION_STATUS );
		$redaction_requested = $order->get_meta( self::META_DATA_REDACTION_REQUESTED );

		wp_nonce_field( 'dsic_meta_box', 'dsic_meta_box_nonce' );
		?>
		<div class="dsic-meta-box">
			<?php if ( empty( $status ) ) : ?>
				<!-- No verification -->
				<p class="dsic-status dsic-status-none">
					<span class="dashicons dashicons-minus"></span>
					<?php esc_html_e( 'No verification requested', 'droix-stripe-id-check' ); ?>
				</p>
				<button type="button" class="button button-primary dsic-action-btn" data-action="request" data-order="<?php echo esc_attr( $order->get_id() ); ?>">
					<?php esc_html_e( 'Request Verification', 'droix-stripe-id-check' ); ?>
				</button>

			<?php elseif ( 'pending' === $status ) : ?>
				<!-- Pending verification -->
				<p class="dsic-status dsic-status-pending">
					<span class="dashicons dashicons-clock"></span>
					<?php esc_html_e( 'Pending', 'droix-stripe-id-check' ); ?>
				</p>
				<?php if ( $requested ) : ?>
					<p class="dsic-meta-info">
						<strong><?php esc_html_e( 'Requested:', 'droix-stripe-id-check' ); ?></strong><br>
						<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $requested ) ); ?>
					</p>
				<?php endif; ?>
				<div class="dsic-actions">
					<button type="button" class="button dsic-action-btn" data-action="resend" data-order="<?php echo esc_attr( $order->get_id() ); ?>">
						<?php esc_html_e( 'Resend Email', 'droix-stripe-id-check' ); ?>
					</button>
					<button type="button" class="button dsic-action-btn dsic-danger" data-action="cancel" data-order="<?php echo esc_attr( $order->get_id() ); ?>">
						<?php esc_html_e( 'Cancel', 'droix-stripe-id-check' ); ?>
					</button>
				</div>

			<?php elseif ( 'verified' === $status ) : ?>
				<!-- Verified -->
				<p class="dsic-status dsic-status-verified">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Verified', 'droix-stripe-id-check' ); ?>
				</p>
				<?php if ( $completed ) : ?>
					<p class="dsic-meta-info">
						<strong><?php esc_html_e( 'Verified:', 'droix-stripe-id-check' ); ?></strong><br>
						<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $completed ) ); ?>
					</p>
				<?php endif; ?>
				<?php if ( $session_id ) : ?>
					<p class="dsic-meta-info">
						<a href="<?php echo esc_url( $this->get_stripe_dashboard_url( $session_id ) ); ?>" target="_blank">
							<?php esc_html_e( 'View in Stripe Dashboard', 'droix-stripe-id-check' ); ?>
							<span class="dashicons dashicons-external"></span>
						</a>
					</p>
				<?php endif; ?>
				<?php $this->render_redaction_section( $order, $session_id, $redaction_status, $redaction_requested ); ?>

			<?php elseif ( 'failed' === $status ) : ?>
				<!-- Failed -->
				<p class="dsic-status dsic-status-failed">
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'Failed', 'droix-stripe-id-check' ); ?>
				</p>
				<?php if ( $error ) : ?>
					<p class="dsic-meta-info dsic-error-msg">
						<strong><?php esc_html_e( 'Reason:', 'droix-stripe-id-check' ); ?></strong><br>
						<?php echo esc_html( $error ); ?>
					</p>
				<?php endif; ?>
				<?php if ( $session_id ) : ?>
					<p class="dsic-meta-info">
						<a href="<?php echo esc_url( $this->get_stripe_dashboard_url( $session_id ) ); ?>" target="_blank">
							<?php esc_html_e( 'View in Stripe Dashboard', 'droix-stripe-id-check' ); ?>
							<span class="dashicons dashicons-external"></span>
						</a>
					</p>
				<?php endif; ?>
				<?php $this->render_redaction_section( $order, $session_id, $redaction_status, $redaction_requested ); ?>
				<div class="dsic-actions">
					<button type="button" class="button button-primary dsic-action-btn dsic-approve-btn" data-action="approve" data-order="<?php echo esc_attr( $order->get_id() ); ?>">
						<?php esc_html_e( 'Approve', 'droix-stripe-id-check' ); ?>
					</button>
					<button type="button" class="button dsic-action-btn" data-action="request" data-order="<?php echo esc_attr( $order->get_id() ); ?>">
						<?php esc_html_e( 'Request New Verification', 'droix-stripe-id-check' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<?php if ( $attempts && (int) $attempts > 1 ) : ?>
				<p class="dsic-meta-info dsic-attempts">
					<?php
					printf(
						/* translators: %d: Number of attempts */
						esc_html__( 'Attempts: %d', 'droix-stripe-id-check' ),
						(int) $attempts
					);
					?>
				</p>
			<?php endif; ?>

			<?php
			// Radar risk data display.
			$radar_risk_level       = $order->get_meta( '_dsic_radar_risk_level' );
			$radar_risk_score       = $order->get_meta( '_dsic_radar_risk_score' );
			$radar_early_warning    = $order->get_meta( '_dsic_radar_early_warning' );
			$radar_early_warn_type  = $order->get_meta( '_dsic_radar_early_warning_type' );

			if ( $radar_risk_level ) :
				$level_colors = array(
					'normal'   => '#00a32a',
					'elevated' => '#dba617',
					'highest'  => '#d63638',
				);
				$level_bg = array(
					'normal'   => '#edfaef',
					'elevated' => '#fcf9e8',
					'highest'  => '#fcf0f1',
				);
				$badge_color = $level_colors[ $radar_risk_level ] ?? '#646970';
				$badge_bg    = $level_bg[ $radar_risk_level ] ?? '#f0f0f1';
				?>
				<div class="dsic-radar-section" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #ddd;">
					<p class="dsic-meta-info" style="margin-bottom: 4px;">
						<strong><?php esc_html_e( 'Stripe Radar', 'droix-stripe-id-check' ); ?></strong>
					</p>
					<p class="dsic-meta-info" style="margin: 4px 0;">
						<?php esc_html_e( 'Risk Level:', 'droix-stripe-id-check' ); ?>
						<span style="display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; background: <?php echo esc_attr( $badge_bg ); ?>; color: <?php echo esc_attr( $badge_color ); ?>;">
							<?php echo esc_html( ucfirst( $radar_risk_level ) ); ?>
						</span>
					</p>
					<?php if ( '' !== $radar_risk_score && false !== $radar_risk_score ) : ?>
						<p class="dsic-meta-info" style="margin: 4px 0;">
							<?php
							printf(
								/* translators: %d: Risk score */
								esc_html__( 'Risk Score: %d/99', 'droix-stripe-id-check' ),
								(int) $radar_risk_score
							);
							?>
						</p>
					<?php endif; ?>
					<?php if ( $radar_early_warning ) : ?>
						<p class="dsic-meta-info" style="margin: 4px 0; color: #d63638;">
							<?php
							printf(
								/* translators: %s: Fraud type */
								esc_html__( 'Early Fraud Warning: %s', 'droix-stripe-id-check' ),
								esc_html( $radar_early_warn_type ?: $radar_early_warning )
							);
							?>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<style>
			.dsic-meta-box { padding: 5px 0; }
			.dsic-status { display: flex; align-items: center; gap: 8px; margin: 0 0 12px; padding: 8px 10px; border-radius: 4px; font-weight: 500; }
			.dsic-status .dashicons { font-size: 18px; width: 18px; height: 18px; }
			.dsic-status-none { background: #f0f0f1; color: #646970; }
			.dsic-status-pending { background: #fcf9e8; color: #996800; }
			.dsic-status-verified { background: #edfaef; color: #00a32a; }
			.dsic-status-failed { background: #fcf0f1; color: #d63638; }
			.dsic-meta-info { margin: 8px 0; font-size: 12px; color: #646970; }
			.dsic-meta-info a { text-decoration: none; }
			.dsic-meta-info .dashicons { font-size: 14px; width: 14px; height: 14px; vertical-align: middle; }
			.dsic-error-msg { color: #d63638; }
			.dsic-actions { display: flex; gap: 8px; flex-wrap: wrap; }
			.dsic-action-btn { margin-top: 8px !important; }
			.dsic-danger { color: #d63638 !important; border-color: #d63638 !important; }
			.dsic-approve-btn { background-color: #00a32a !important; border-color: #00a32a !important; color: #fff !important; }
			.dsic-approve-btn:hover { background-color: #008a20 !important; border-color: #008a20 !important; }
			.dsic-approve-btn:focus { box-shadow: 0 0 0 1px #fff, 0 0 0 3px #00a32a !important; }
			.dsic-attempts { font-style: italic; }
			.dsic-redaction-section { margin-top: 12px; padding-top: 12px; border-top: 1px solid #ddd; }
			.dsic-redaction-status { font-size: 11px; padding: 6px 8px; border-radius: 3px; margin-bottom: 8px; }
			.dsic-redaction-pending { background: #fcf9e8; color: #996800; }
			.dsic-redaction-complete { background: #edfaef; color: #00a32a; }
			.dsic-redact-btn { font-size: 11px !important; padding: 4px 8px !important; }
		</style>

		<script>
		jQuery(function($) {
			$('.dsic-action-btn').on('click', function() {
				var $btn = $(this);
				var action = $btn.data('action');
				var orderId = $btn.data('order');
				var originalText = $btn.text();

				// Confirmation dialog for approve action.
				if ( action === 'approve' ) {
					if ( ! confirm( '<?php echo esc_js( __( 'Are you sure you want to manually approve this verification? This will mark the order as verified and change status to Processing.', 'droix-stripe-id-check' ) ); ?>' ) ) {
						return;
					}
				}

				$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Processing...', 'droix-stripe-id-check' ) ); ?>');

				$.post(ajaxurl, {
					action: 'dsic_' + action + '_verification',
					order_id: orderId,
					nonce: '<?php echo esc_js( wp_create_nonce( 'dsic_nonce' ) ); ?>'
				}, function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || '<?php echo esc_js( __( 'An error occurred.', 'droix-stripe-id-check' ) ); ?>');
						$btn.prop('disabled', false).text(originalText);
					}
				});
			});

			$('.dsic-redact-btn').on('click', function() {
				var $btn = $(this);
				var orderId = $btn.data('order');

				if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete the verification data from Stripe? This action cannot be undone.', 'droix-stripe-id-check' ) ); ?>')) {
					return;
				}

				$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Requesting...', 'droix-stripe-id-check' ) ); ?>');

				$.post(ajaxurl, {
					action: 'dsic_redact_verification',
					order_id: orderId,
					nonce: '<?php echo esc_js( wp_create_nonce( 'dsic_nonce' ) ); ?>'
				}, function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || '<?php echo esc_js( __( 'An error occurred.', 'droix-stripe-id-check' ) ); ?>');
						$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Delete Data from Stripe', 'droix-stripe-id-check' ) ); ?>');
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Get Stripe dashboard URL for a session.
	 *
	 * @since 0.0.1
	 * @param string $session_id Stripe session ID.
	 * @return string Dashboard URL.
	 */
	private function get_stripe_dashboard_url( string $session_id ): string {
		$test_mode = get_option( 'dsic_test_mode', true );
		$base_url  = $test_mode
			? 'https://dashboard.stripe.com/test/identity/verification-sessions/'
			: 'https://dashboard.stripe.com/identity/verification-sessions/';

		return $base_url . $session_id;
	}

	/**
	 * Add verification column to orders list.
	 *
	 * @since 0.0.1
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_order_column( array $columns ): array {
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;

			// Add after order status.
			if ( 'order_status' === $key ) {
				$new_columns['dsic_verification'] = __( 'ID Check', 'droix-stripe-id-check' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render verification column content (legacy).
	 *
	 * @since 0.0.1
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_order_column( string $column, int $post_id ): void {
		if ( 'dsic_verification' !== $column ) {
			return;
		}

		$order = wc_get_order( $post_id );
		$this->render_verification_badge( $order );
	}

	/**
	 * Render verification column content (HPOS).
	 *
	 * @since 0.0.1
	 * @param string   $column Column name.
	 * @param WC_Order $order  Order object.
	 * @return void
	 */
	public function render_order_column_hpos( string $column, WC_Order $order ): void {
		if ( 'dsic_verification' !== $column ) {
			return;
		}

		$this->render_verification_badge( $order );
	}

	/**
	 * Render verification status badge.
	 *
	 * @since 0.0.1
	 * @param WC_Order|null $order Order object.
	 * @return void
	 */
	private function render_verification_badge( ?WC_Order $order ): void {
		if ( ! $order ) {
			echo '<span class="dsic-badge dsic-badge-none">—</span>';
			return;
		}

		$status = $order->get_meta( self::META_VERIFICATION_STATUS );

		$badges = array(
			'pending'  => array(
				'class' => 'dsic-badge-pending',
				'icon'  => 'clock',
				'title' => __( 'Pending', 'droix-stripe-id-check' ),
			),
			'verified' => array(
				'class' => 'dsic-badge-verified',
				'icon'  => 'yes',
				'title' => __( 'Verified', 'droix-stripe-id-check' ),
			),
			'failed'   => array(
				'class' => 'dsic-badge-failed',
				'icon'  => 'no',
				'title' => __( 'Failed', 'droix-stripe-id-check' ),
			),
		);

		if ( empty( $status ) || ! isset( $badges[ $status ] ) ) {
			echo '<span class="dsic-badge dsic-badge-none" title="' . esc_attr__( 'Not requested', 'droix-stripe-id-check' ) . '">—</span>';
			return;
		}

		$badge = $badges[ $status ];
		printf(
			'<span class="dsic-badge %s" title="%s"><span class="dashicons dashicons-%s"></span></span>',
			esc_attr( $badge['class'] ),
			esc_attr( $badge['title'] ),
			esc_attr( $badge['icon'] )
		);
	}

	/**
	 * AJAX handler for request verification.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function ajax_request_verification(): void {
		check_ajax_referer( 'dsic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'droix-stripe-id-check' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'droix-stripe-id-check' ) ) );
		}

		$result = $this->request_verification( $order_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Verification requested.', 'droix-stripe-id-check' ) ) );
	}

	/**
	 * AJAX handler for resend verification.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function ajax_resend_verification(): void {
		check_ajax_referer( 'dsic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'droix-stripe-id-check' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'droix-stripe-id-check' ) ) );
		}

		$result = $this->resend_verification_email( $order_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Email resent.', 'droix-stripe-id-check' ) ) );
	}

	/**
	 * AJAX handler for cancel verification.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function ajax_cancel_verification(): void {
		check_ajax_referer( 'dsic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'droix-stripe-id-check' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'droix-stripe-id-check' ) ) );
		}

		$result = $this->cancel_verification( $order_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Verification cancelled.', 'droix-stripe-id-check' ) ) );
	}

	/**
	 * AJAX handler for manual approval of verification.
	 *
	 * @since 1.7.0
	 * @return void
	 */
	public function ajax_approve_verification(): void {
		check_ajax_referer( 'dsic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'droix-stripe-id-check' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'droix-stripe-id-check' ) ) );
		}

		$result = $this->approve_verification( $order_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Verification approved successfully.', 'droix-stripe-id-check' ) ) );
	}

	/**
	 * Render redaction section in meta box.
	 *
	 * @since 0.5.6
	 * @param WC_Order    $order              Order object.
	 * @param string      $session_id         Stripe session ID.
	 * @param string      $redaction_status   Current redaction status.
	 * @param int|string  $redaction_requested Timestamp when redaction was requested.
	 * @return void
	 */
	private function render_redaction_section( WC_Order $order, string $session_id, string $redaction_status, $redaction_requested ): void {
		if ( empty( $session_id ) ) {
			return;
		}
		?>
		<div class="dsic-redaction-section">
			<p class="dsic-meta-info" style="margin-bottom: 6px;">
				<strong><?php esc_html_e( 'Data Management:', 'droix-stripe-id-check' ); ?></strong>
			</p>
			<?php if ( 'completed' === $redaction_status ) : ?>
				<p class="dsic-redaction-status dsic-redaction-complete">
					<span class="dashicons dashicons-yes" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
					<?php esc_html_e( 'Verification data deleted from Stripe', 'droix-stripe-id-check' ); ?>
				</p>
				<?php
				$redaction_completed = $order->get_meta( '_dsic_data_redaction_completed' );
				if ( $redaction_completed ) :
					?>
					<p class="dsic-meta-info" style="font-size: 11px;">
						<?php echo esc_html( wp_date( get_option( 'date_format' ), $redaction_completed ) ); ?>
					</p>
				<?php endif; ?>
			<?php elseif ( 'pending' === $redaction_status ) : ?>
				<p class="dsic-redaction-status dsic-redaction-pending">
					<span class="dashicons dashicons-clock" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
					<?php esc_html_e( 'Data deletion in progress...', 'droix-stripe-id-check' ); ?>
					<?php if ( $redaction_requested ) : ?>
						<br><small><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $redaction_requested ) ); ?></small>
					<?php endif; ?>
				</p>
			<?php elseif ( 'failed' === $redaction_status ) : ?>
				<p class="dsic-redaction-status dsic-redaction-failed" style="color: #d63638;">
					<span class="dashicons dashicons-warning" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
					<?php esc_html_e( 'Data deletion failed', 'droix-stripe-id-check' ); ?>
				</p>
				<button type="button" class="button button-secondary dsic-redact-btn" data-order="<?php echo esc_attr( $order->get_id() ); ?>">
					<span class="dashicons dashicons-update" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;"></span>
					<?php esc_html_e( 'Retry Deletion', 'droix-stripe-id-check' ); ?>
				</button>
			<?php else : ?>
				<button type="button" class="button dsic-redact-btn" data-order="<?php echo esc_attr( $order->get_id() ); ?>">
					<span class="dashicons dashicons-trash" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;"></span>
					<?php esc_html_e( 'Delete Verification Data', 'droix-stripe-id-check' ); ?>
				</button>
				<?php
				$retention_days = absint( get_option( 'dsic_redaction_days', '30' ) );
				$auto_enabled   = get_option( 'dsic_auto_redaction_enabled', '0' );
				if ( '1' === $auto_enabled ) :
					?>
					<p class="dsic-meta-info" style="margin-top: 4px; font-size: 11px;">
						<?php
						/* translators: %d: number of retention days */
						printf( esc_html__( 'Auto-deletes after %d days', 'droix-stripe-id-check' ), $retention_days );
						?>
					</p>
				<?php else : ?>
					<p class="dsic-meta-info" style="margin-top: 4px; font-size: 11px;">
						<?php esc_html_e( 'Auto-deletion disabled', 'droix-stripe-id-check' ); ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX handler for redact verification data.
	 *
	 * @since 0.5.6
	 * @return void
	 */
	public function ajax_redact_verification(): void {
		check_ajax_referer( 'dsic_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'droix-stripe-id-check' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'droix-stripe-id-check' ) ) );
		}

		$result = $this->redact_verification_data( $order_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Data deletion request sent to Stripe.', 'droix-stripe-id-check' ) ) );
	}

	/**
	 * Redact verification data from Stripe.
	 *
	 * Uses the DSIC_Auto_Redaction class for consistent handling.
	 *
	 * @since 0.5.6
	 * @since 1.7.0 Updated to use DSIC_Auto_Redaction::manual_redaction().
	 * @param int $order_id Order ID.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function redact_verification_data( int $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'dsic_invalid_order', __( 'Invalid order.', 'droix-stripe-id-check' ) );
		}

		$session_id = $order->get_meta( self::META_VERIFICATION_SESSION );

		if ( empty( $session_id ) ) {
			return new WP_Error( 'dsic_no_session', __( 'No verification session found.', 'droix-stripe-id-check' ) );
		}

		// Check if already redacted.
		$redaction_status = $order->get_meta( self::META_DATA_REDACTION_STATUS );
		if ( 'completed' === $redaction_status ) {
			return new WP_Error( 'dsic_already_redacted', __( 'Data has already been deleted.', 'droix-stripe-id-check' ) );
		}

		// Check if currently pending.
		if ( 'pending' === $redaction_status ) {
			return new WP_Error( 'dsic_redaction_pending', __( 'Data deletion is already in progress.', 'droix-stripe-id-check' ) );
		}

		// Use the auto-redaction class for consistent handling (v1.7.0+).
		// This ensures proper error handling, retry logic, and compliance logging.
		if ( class_exists( 'DSIC_Auto_Redaction' ) ) {
			DSIC_Auto_Redaction::manual_redaction( $order_id );
			DSIC_Logger::info( 'Manual redaction scheduled for order #' . $order_id );
		} else {
			// Fallback for if auto-redaction class not loaded.
			return new WP_Error( 'dsic_class_not_found', __( 'Auto-redaction class not available.', 'droix-stripe-id-check' ) );
		}

		return true;
	}
}
