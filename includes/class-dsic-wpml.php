<?php
/**
 * WPML/Polylang Integration Class.
 *
 * Handles multilingual compatibility for email templates and strings.
 *
 * @package    DSIC
 * @subpackage DSIC/includes
 * @since      1.0.1
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_WPML
 *
 * Provides WPML and Polylang compatibility for the plugin.
 *
 * @since 1.0.1
 */
class DSIC_WPML {

	/**
	 * String context for email templates (customer-facing).
	 *
	 * @since 1.0.1
	 * @since 1.4.1 Renamed to clearly indicate customer emails.
	 * @var string
	 */
	const CONTEXT_EMAILS = 'Stripe ID Check - Emails';

	/**
	 * String context for frontend UI (customer-facing).
	 *
	 * @since 1.4.1
	 * @var string
	 */
	const CONTEXT_CUSTOMER = 'Stripe ID Check - Customer';

	/**
	 * String context for admin UI (backend only).
	 *
	 * @since 1.4.1
	 * @var string
	 */
	const CONTEXT_ADMIN = 'Stripe ID Check - Admin';

	/**
	 * Email types that need translation.
	 *
	 * @since 1.0.1
	 * @var array
	 */
	private static array $email_types = array(
		'verification_request',
		'verification_passed',
		'verification_failed',
		'data_redaction',
	);

	/**
	 * Initialize WPML integration.
	 *
	 * @since 1.0.1
	 * @return void
	 */
	public static function init(): void {
		// Register strings when email templates are saved.
		add_action( 'update_option', array( __CLASS__, 'register_string_on_save' ), 10, 3 );

		// Register existing strings on plugin activation or admin init.
		add_action( 'admin_init', array( __CLASS__, 'register_existing_strings' ), 20 );
	}

	/**
	 * Check if WPML String Translation is active.
	 *
	 * @since 1.0.1
	 * @return bool
	 */
	public static function is_wpml_active(): bool {
		return function_exists( 'icl_register_string' ) && function_exists( 'icl_t' );
	}

	/**
	 * Check if Polylang is active.
	 *
	 * @since 1.0.1
	 * @return bool
	 */
	public static function is_polylang_active(): bool {
		return function_exists( 'pll_register_string' ) && function_exists( 'pll__' );
	}

	/**
	 * Check if any translation plugin is active.
	 *
	 * @since 1.0.1
	 * @return bool
	 */
	public static function is_multilingual(): bool {
		return self::is_wpml_active() || self::is_polylang_active();
	}

	/**
	 * Get current language code.
	 *
	 * @since 1.0.1
	 * @return string Language code (e.g., 'en', 'de', 'fr').
	 */
	public static function get_current_language(): string {
		// WPML.
		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			return ICL_LANGUAGE_CODE;
		}

		// Polylang.
		if ( function_exists( 'pll_current_language' ) ) {
			$lang = pll_current_language();
			return $lang ? $lang : 'en';
		}

		return 'en';
	}

	/**
	 * Get order language.
	 *
	 * @since 1.0.1
	 * @param WC_Order|int $order Order object or ID.
	 * @return string Language code.
	 */
	public static function get_order_language( $order ): string {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order instanceof WC_Order ) {
			return self::get_current_language();
		}

		// WPML stores language in order meta.
		$wpml_lang = $order->get_meta( 'wpml_language' );
		if ( $wpml_lang ) {
			return $wpml_lang;
		}

		// Polylang stores language differently.
		if ( function_exists( 'pll_get_post_language' ) ) {
			$pll_lang = pll_get_post_language( $order->get_id() );
			if ( $pll_lang ) {
				return $pll_lang;
			}
		}

		// Fallback to current language.
		return self::get_current_language();
	}

	/**
	 * Register a string for translation.
	 *
	 * @since 1.0.1
	 * @since 1.4.1 Added $context parameter for categorization.
	 * @param string $name    String name/identifier.
	 * @param string $value   String value.
	 * @param string $context Context/domain for categorization (default: emails).
	 * @return void
	 */
	public static function register_string( string $name, string $value, string $context = '' ): void {
		if ( empty( $value ) ) {
			return;
		}

		// Default to emails context for backwards compatibility.
		if ( empty( $context ) ) {
			$context = self::CONTEXT_EMAILS;
		}

		// WPML String Translation.
		if ( function_exists( 'icl_register_string' ) ) {
			icl_register_string( $context, $name, $value );
		}

		// Polylang.
		if ( function_exists( 'pll_register_string' ) ) {
			pll_register_string( $name, $value, $context, true );
		}
	}

	/**
	 * Get translated string.
	 *
	 * @since 1.0.1
	 * @since 1.4.1 Added $context parameter.
	 * @param string $name    String name/identifier.
	 * @param string $value   Original string value.
	 * @param string $context Context/domain for categorization (default: emails).
	 * @return string Translated string.
	 */
	public static function translate_string( string $name, string $value, string $context = '' ): string {
		if ( empty( $value ) ) {
			return $value;
		}

		// Default to emails context for backwards compatibility.
		if ( empty( $context ) ) {
			$context = self::CONTEXT_EMAILS;
		}

		// WPML String Translation.
		if ( function_exists( 'icl_t' ) ) {
			$translated = icl_t( $context, $name, $value );
			if ( $translated !== $value ) {
				return $translated;
			}
		}

		// Polylang.
		if ( function_exists( 'pll__' ) ) {
			$translated = pll__( $value );
			if ( $translated !== $value ) {
				return $translated;
			}
		}

		return $value;
	}

	/**
	 * Get translated string for a specific language.
	 *
	 * @since 1.0.1
	 * @since 1.4.1 Added $context parameter.
	 * @param string $name     String name/identifier.
	 * @param string $value    Original string value.
	 * @param string $language Target language code.
	 * @param string $context  Context/domain for categorization (default: emails).
	 * @return string Translated string.
	 */
	public static function translate_string_to_language( string $name, string $value, string $language, string $context = '' ): string {
		if ( empty( $value ) || empty( $language ) ) {
			return $value;
		}

		// Default to emails context for backwards compatibility.
		if ( empty( $context ) ) {
			$context = self::CONTEXT_EMAILS;
		}

		// WPML - switch language context temporarily.
		if ( function_exists( 'icl_t' ) && class_exists( 'SitePress' ) ) {
			global $sitepress;
			if ( $sitepress ) {
				$current_lang = $sitepress->get_current_language();
				$sitepress->switch_lang( $language );
				$translated = icl_t( $context, $name, $value );
				$sitepress->switch_lang( $current_lang );
				return $translated;
			}
		}

		// Polylang - use pll_translate_string if available.
		if ( function_exists( 'pll_translate_string' ) ) {
			return pll_translate_string( $value, $language );
		}

		return self::translate_string( $name, $value, $context );
	}

	/**
	 * Register string when an option is saved.
	 *
	 * @since 1.0.1
	 * @since 1.6.0 Added support for auto-verification options.
	 * @param string $option    Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 * @return void
	 */
	public static function register_string_on_save( string $option, $old_value, $new_value ): void {
		if ( ! self::is_multilingual() ) {
			return;
		}

		// Handle auto-verification options.
		if ( 'dsic_auto_verify_checkout_message' === $option && is_string( $new_value ) && ! empty( $new_value ) ) {
			self::register_string( 'Auto Verify: Checkout Message', $new_value, self::CONTEXT_CUSTOMER );
			DSIC_Logger::debug( 'WPML: Registered auto-verify checkout message' );
			return;
		}

		if ( 'dsic_auto_verify_thankyou_message' === $option && is_string( $new_value ) && ! empty( $new_value ) ) {
			self::register_string( 'Auto Verify: Thank You Message', $new_value, self::CONTEXT_CUSTOMER );
			DSIC_Logger::debug( 'WPML: Registered auto-verify thank you message' );
			return;
		}

		if ( 'dsic_radar_thankyou_message' === $option && is_string( $new_value ) && ! empty( $new_value ) ) {
			self::register_string( 'Radar: Thank You Message', $new_value, self::CONTEXT_CUSTOMER );
			DSIC_Logger::debug( 'WPML: Registered Radar thank you message' );
			return;
		}

		// Check if this is one of our email template options.
		if ( strpos( $option, 'dsic_email_' ) !== 0 ) {
			return;
		}

		// Extract the string name from option name.
		$string_name = self::option_to_string_name( $option );
		if ( $string_name && is_string( $new_value ) && ! empty( $new_value ) ) {
			self::register_string( $string_name, $new_value );
			DSIC_Logger::debug( 'WPML: Registered string "' . $string_name . '"' );
		}
	}

	/**
	 * Register all existing email template strings.
	 *
	 * @since 1.0.1
	 * @return void
	 */
	public static function register_existing_strings(): void {
		if ( ! self::is_multilingual() ) {
			return;
		}

		// Only run once per request.
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;

		// Register email template strings.
		$fields = array( 'subject', 'heading', 'body' );

		foreach ( self::$email_types as $type ) {
			foreach ( $fields as $field ) {
				$option_name = 'dsic_email_' . $type . '_' . $field;
				$value       = get_option( $option_name, '' );

				if ( ! empty( $value ) ) {
					$string_name = self::option_to_string_name( $option_name );
					self::register_string( $string_name, $value );
				}
			}
		}

		// Register frontend strings for customer-facing pages.
		self::register_frontend_strings();
	}

	/**
	 * Register frontend strings for WPML String Translation.
	 *
	 * These strings appear on customer-facing pages (My Account, order view).
	 * Registered under CONTEXT_CUSTOMER for easy filtering in WPML.
	 *
	 * @since 1.3.1
	 * @since 1.4.1 Uses CONTEXT_CUSTOMER for separate domain.
	 * @return void
	 */
	public static function register_frontend_strings(): void {
		$frontend_strings = array(
			// Section title.
			'Title'                    => 'Identity Verification',

			// Progress steps.
			'Step Requested'           => 'Requested',
			'Step In Progress'         => 'In Progress',
			'Step Complete'            => 'Complete',

			// Status labels.
			'Status Awaiting'          => 'Awaiting Verification',
			'Status Verified'          => 'Verified Successfully',
			'Status Failed'            => 'Verification Failed',

			// Pending status content.
			'Pending Message'          => 'To process your order, we need to verify your identity. This is a quick and secure process powered by Stripe.',
			'What You Need'            => 'What you\'ll need:',
			'Need ID'                  => 'A valid government-issued ID (passport, driving licence, or ID card)',
			'Need Camera'              => 'A device with a camera',
			'Need Time'                => 'About 2-3 minutes',
			'Verify Button'            => 'Verify My Identity',

			// Verified status content.
			'Verified Message'         => 'Your identity has been successfully verified. Your order is now being processed.',

			// Failed status content.
			'Failed Message'           => 'Unfortunately, we were unable to verify your identity.',
			'Reason Label'             => 'Reason:',
			'Contact Support'          => 'Please contact our support team for assistance with your order.',

			// Timestamps.
			'Requested Label'          => 'Requested:',
			'Verified Label'           => 'Verified:',
			'Failed Label'             => 'Failed:',
		);

		foreach ( $frontend_strings as $name => $value ) {
			self::register_string( $name, $value, self::CONTEXT_CUSTOMER );
		}

		// Register auto-verification strings.
		self::register_auto_verify_strings();
	}

	/**
	 * Register auto-verification strings for WPML String Translation.
	 *
	 * These strings appear during checkout and on the thank you page.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public static function register_auto_verify_strings(): void {
		// Register the customizable messages from settings.
		$checkout_message = get_option( 'dsic_auto_verify_checkout_message', '' );
		$thankyou_message = get_option( 'dsic_auto_verify_thankyou_message', '' );

		if ( ! empty( $checkout_message ) ) {
			self::register_string( 'Auto Verify: Checkout Message', $checkout_message, self::CONTEXT_CUSTOMER );
		}

		if ( ! empty( $thankyou_message ) ) {
			self::register_string( 'Auto Verify: Thank You Message', $thankyou_message, self::CONTEXT_CUSTOMER );
		}

		// Register Radar thank you message.
		$radar_thankyou_message = get_option( 'dsic_radar_thankyou_message', '' );
		if ( ! empty( $radar_thankyou_message ) ) {
			self::register_string( 'Radar: Thank You Message', $radar_thankyou_message, self::CONTEXT_CUSTOMER );
		}
	}

	/**
	 * Get translated auto-verification checkout message.
	 *
	 * @since 1.6.0
	 * @return string Translated checkout message.
	 */
	public static function get_auto_verify_checkout_message(): string {
		$settings = new DSIC_Settings();
		$default  = $settings->get_default_checkout_message();
		$message  = get_option( 'dsic_auto_verify_checkout_message', $default );

		if ( ! self::is_multilingual() ) {
			return $message;
		}

		return self::translate_string( 'Auto Verify: Checkout Message', $message, self::CONTEXT_CUSTOMER );
	}

	/**
	 * Get translated auto-verification thank you message.
	 *
	 * @since 1.6.0
	 * @return string Translated thank you message.
	 */
	public static function get_auto_verify_thankyou_message(): string {
		$settings = new DSIC_Settings();
		$default  = $settings->get_default_thankyou_message();
		$message  = get_option( 'dsic_auto_verify_thankyou_message', $default );

		if ( ! self::is_multilingual() ) {
			return $message;
		}

		return self::translate_string( 'Auto Verify: Thank You Message', $message, self::CONTEXT_CUSTOMER );
	}

	/**
	 * Get translated Radar thank you page message.
	 *
	 * @since 1.9.0
	 * @return string Translated Radar thank you message.
	 */
	public static function get_radar_thankyou_message(): string {
		$settings = new DSIC_Settings();
		$default  = $settings->get_default_radar_thankyou_message();
		$message  = get_option( 'dsic_radar_thankyou_message', $default );

		if ( ! self::is_multilingual() ) {
			return $message;
		}

		return self::translate_string( 'Radar: Thank You Message', $message, self::CONTEXT_CUSTOMER );
	}

	/**
	 * Get translated frontend string.
	 *
	 * @since 1.3.1
	 * @since 1.4.1 Uses CONTEXT_CUSTOMER, simplified key names.
	 * @param string $key String key (e.g., 'Title', 'Verify Button').
	 * @param string $default Default value if translation not found.
	 * @return string Translated string.
	 */
	public static function get_frontend_string( string $key, string $default = '' ): string {
		if ( ! self::is_multilingual() ) {
			return $default;
		}

		return self::translate_string( $key, $default, self::CONTEXT_CUSTOMER );
	}

	/**
	 * Convert option name to WPML string name.
	 *
	 * @since 1.0.1
	 * @param string $option_name Option name.
	 * @return string String name for WPML.
	 */
	private static function option_to_string_name( string $option_name ): string {
		// dsic_email_verification_request_subject -> Email: Verification Request - Subject
		$name = str_replace( 'dsic_email_', '', $option_name );
		$parts = explode( '_', $name );

		if ( count( $parts ) < 2 ) {
			return $option_name;
		}

		// Last part is the field (subject, heading, body).
		$field = ucfirst( array_pop( $parts ) );

		// Remaining parts are the email type.
		$type = ucwords( str_replace( '_', ' ', implode( '_', $parts ) ) );

		return 'Email: ' . $type . ' - ' . $field;
	}

	/**
	 * Get translated email template for an order.
	 *
	 * @since 1.0.1
	 * @since 1.4.1 Explicitly uses CONTEXT_EMAILS.
	 * @param string       $type  Email type (verification_request, etc.).
	 * @param string       $field Field name (subject, heading, body).
	 * @param WC_Order|int $order Order object or ID for language detection.
	 * @return string Translated value.
	 */
	public static function get_translated_email_field( string $type, string $field, $order ): string {
		$option_name = 'dsic_email_' . $type . '_' . $field;
		$value       = get_option( $option_name, '' );

		if ( empty( $value ) ) {
			return $value;
		}

		if ( ! self::is_multilingual() ) {
			return $value;
		}

		$language    = self::get_order_language( $order );
		$string_name = self::option_to_string_name( $option_name );

		return self::translate_string_to_language( $string_name, $value, $language, self::CONTEXT_EMAILS );
	}

	/**
	 * Switch to order language context.
	 *
	 * Use this before sending emails to ensure all strings are in the order's language.
	 *
	 * @since 1.0.1
	 * @param WC_Order|int $order Order object or ID.
	 * @return string|null Previous language code (for restoration).
	 */
	public static function switch_to_order_language( $order ): ?string {
		$order_lang = self::get_order_language( $order );

		// WPML.
		if ( class_exists( 'SitePress' ) ) {
			global $sitepress;
			if ( $sitepress ) {
				$current = $sitepress->get_current_language();
				$sitepress->switch_lang( $order_lang );
				return $current;
			}
		}

		// Polylang.
		if ( function_exists( 'pll_current_language' ) && function_exists( 'PLL' ) ) {
			$current = pll_current_language();
			// Polylang doesn't have a simple switch_lang, but WooCommerce handles it.
			return $current;
		}

		return null;
	}

	/**
	 * Restore language context.
	 *
	 * @since 1.0.1
	 * @param string|null $language Language code to restore.
	 * @return void
	 */
	public static function restore_language( ?string $language ): void {
		if ( empty( $language ) ) {
			return;
		}

		// WPML.
		if ( class_exists( 'SitePress' ) ) {
			global $sitepress;
			if ( $sitepress ) {
				$sitepress->switch_lang( $language );
			}
		}
	}

	/**
	 * Get list of available languages.
	 *
	 * @since 1.0.1
	 * @return array Array of language codes and names.
	 */
	public static function get_available_languages(): array {
		$languages = array();

		// WPML.
		if ( function_exists( 'icl_get_languages' ) ) {
			$wpml_langs = icl_get_languages( 'skip_missing=0' );
			if ( is_array( $wpml_langs ) ) {
				foreach ( $wpml_langs as $lang ) {
					$languages[ $lang['code'] ] = $lang['native_name'];
				}
			}
		}

		// Polylang.
		if ( function_exists( 'pll_languages_list' ) ) {
			$pll_langs = pll_languages_list( array( 'fields' => array() ) );
			if ( is_array( $pll_langs ) ) {
				foreach ( $pll_langs as $lang ) {
					$languages[ $lang->slug ] = $lang->name;
				}
			}
		}

		return $languages;
	}
}
