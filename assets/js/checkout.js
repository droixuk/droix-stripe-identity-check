/**
 * DSIC Checkout Script
 *
 * Handles dynamic notice display when "Ship to different address" is checked.
 *
 * @package DSIC
 * @since   1.6.0
 */

( function( $ ) {
	'use strict';

	/**
	 * DSIC Checkout Notice Handler
	 */
	const DSIC_Checkout = {
		/**
		 * Notice element ID.
		 */
		noticeId: 'dsic-address-verification-notice',

		/**
		 * Checkbox selector.
		 */
		checkboxSelector: '#ship-to-different-address-checkbox',

		/**
		 * Container selector for notice placement.
		 */
		containerSelector: '.woocommerce-shipping-fields',

		/**
		 * Initialize the checkout notice handler.
		 */
		init: function() {
			// Check if configuration exists and is enabled.
			if ( typeof dsic_checkout === 'undefined' || ! dsic_checkout.enabled ) {
				return;
			}

			// Check if checkbox exists (some themes may not have it).
			if ( ! $( this.checkboxSelector ).length ) {
				return;
			}

			this.bindEvents();
			this.checkInitialState();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			var self = this;

			// Listen for checkbox changes.
			$( document.body ).on( 'change', this.checkboxSelector, function( e ) {
				self.toggleNotice( e.target.checked );
			} );

			// Handle WooCommerce AJAX checkout updates.
			$( document.body ).on( 'updated_checkout', function() {
				self.checkInitialState();
			} );

			// Handle checkout fragments refresh.
			$( document.body ).on( 'checkout_error', function() {
				self.checkInitialState();
			} );
		},

		/**
		 * Check and apply initial state on page load.
		 */
		checkInitialState: function() {
			var $checkbox = $( this.checkboxSelector );

			if ( $checkbox.length ) {
				this.toggleNotice( $checkbox.is( ':checked' ) );
			}
		},

		/**
		 * Toggle notice visibility.
		 *
		 * @param {boolean} show Whether to show or hide the notice.
		 */
		toggleNotice: function( show ) {
			if ( show ) {
				this.showNotice();
			} else {
				this.hideNotice();
			}
		},

		/**
		 * Show the verification notice.
		 */
		showNotice: function() {
			// Don't add duplicate notices.
			if ( $( '#' + this.noticeId ).length ) {
				return;
			}

			var $notice = $( '<div>', {
				id: this.noticeId,
				'class': 'dsic-checkout-notice woocommerce-info',
				role: 'alert',
				'aria-live': 'polite'
			} );

			// Add icon and message.
			$notice.html(
				'<span class="dsic-notice-icon" aria-hidden="true"></span>' +
				'<span class="dsic-notice-text">' + this.escapeHtml( dsic_checkout.message ) + '</span>'
			);

			// Insert before the shipping fields container.
			var $container = $( this.containerSelector );
			if ( $container.length ) {
				$container.before( $notice );

				// Animate in.
				$notice.hide().slideDown( 200 );
			}
		},

		/**
		 * Hide the verification notice.
		 */
		hideNotice: function() {
			var $notice = $( '#' + this.noticeId );

			if ( $notice.length ) {
				$notice.slideUp( 200, function() {
					$( this ).remove();
				} );
			}
		},

		/**
		 * Escape HTML to prevent XSS.
		 *
		 * @param {string} text Text to escape.
		 * @return {string} Escaped text.
		 */
		escapeHtml: function( text ) {
			var div = document.createElement( 'div' );
			div.textContent = text;
			return div.innerHTML;
		}
	};

	/**
	 * Initialize on document ready.
	 */
	$( document ).ready( function() {
		DSIC_Checkout.init();
	} );

} )( jQuery );
