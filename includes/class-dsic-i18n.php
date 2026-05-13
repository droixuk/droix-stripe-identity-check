<?php
/**
 * Internationalization class.
 *
 * Handles loading of the plugin text domain.
 *
 * @package    DSIC
 * @subpackage DSIC/includes
 * @since      0.0.1
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class DSIC_i18n
 *
 * @since 0.0.1
 */
class DSIC_i18n {

	/**
	 * Load the plugin text domain.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function load_plugin_textdomain(): void {
		load_plugin_textdomain(
			'droix-stripe-id-check',
			false,
			dirname( DSIC_PLUGIN_BASENAME ) . '/languages/'
		);
	}
}
