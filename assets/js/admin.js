/**
 * Admin JavaScript for Stripe ID Check plugin.
 *
 * @package DSIC
 * @since   0.0.1
 */

(function () {
	'use strict';

	/**
	 * DSIC Admin namespace.
	 */
	window.DSIC = window.DSIC || {};

	/**
	 * Initialize admin functionality.
	 */
	DSIC.init = function () {
		DSIC.initPasswordToggles();
		DSIC.initCopyButtons();
		DSIC.initConnectionTest();
		DSIC.initLinnworksConnectionTest();
		DSIC.initRadarConnectionTest();
		DSIC.initLinnworksTestPanel();
		DSIC.initLogControls();
		DSIC.initEmailTemplates();
		DSIC.initAccordions();
		DSIC.initPlaceholderClicks();
		DSIC.initDashboardRedact();
	};

	/**
	 * Initialize password visibility toggles.
	 */
	DSIC.initPasswordToggles = function () {
		const toggleButtons = document.querySelectorAll('.dsic-toggle-password');

		toggleButtons.forEach(function (button) {
			button.addEventListener('click', function () {
				const targetId = this.getAttribute('data-target');
				const input = document.getElementById(targetId);
				const icon = this.querySelector('.dashicons');

				if (!input) return;

				if (input.type === 'password') {
					input.type = 'text';
					icon.classList.remove('dashicons-visibility');
					icon.classList.add('dashicons-hidden');
				} else {
					input.type = 'password';
					icon.classList.remove('dashicons-hidden');
					icon.classList.add('dashicons-visibility');
				}
			});
		});
	};

	/**
	 * Initialize copy to clipboard buttons.
	 */
	DSIC.initCopyButtons = function () {
		const copyButtons = document.querySelectorAll('.dsic-copy-button');

		copyButtons.forEach(function (button) {
			button.addEventListener('click', function () {
				const targetId = this.getAttribute('data-copy');
				const target = document.getElementById(targetId);

				if (!target) return;

				const text = target.textContent || target.value;

				navigator.clipboard.writeText(text).then(function () {
					const originalText = button.innerHTML;
					button.innerHTML = '<span class="dashicons dashicons-yes"></span> ' +
						(dsic_ajax.strings.copied || 'Copied!');

					setTimeout(function () {
						button.innerHTML = originalText;
					}, 2000);
				}).catch(function (err) {
					console.error('Failed to copy:', err);
				});
			});
		});
	};

	/**
	 * Initialize connection test button.
	 */
	DSIC.initConnectionTest = function () {
		const testButton = document.getElementById('dsic-test-connection');
		const statusElement = document.getElementById('dsic-connection-status');

		if (!testButton || !statusElement) return;

		testButton.addEventListener('click', function () {
			// Check test mode toggle to determine which key to use.
			const testModeToggle = document.getElementById('dsic_test_mode');
			const isTestMode = testModeToggle ? testModeToggle.checked : false;

			// Get the appropriate secret key based on mode.
			const secretKeyInput = isTestMode
				? document.getElementById('dsic_test_secret_key')
				: document.getElementById('dsic_live_secret_key');
			const secretKey = secretKeyInput ? secretKeyInput.value : '';

			// Update UI to loading state.
			testButton.disabled = true;
			statusElement.className = 'dsic-status dsic-status-loading';
			statusElement.innerHTML = '<span class="dsic-spinner"></span> ' +
				(dsic_ajax.strings.testing || 'Testing...');

			// Make AJAX request.
			DSIC.ajax('test_connection', {
				secret_key: secretKey
			}).then(function (response) {
				statusElement.className = 'dsic-status dsic-status-success';
				let message = dsic_ajax.strings.success || 'Connection successful!';
				if (response.account_name) {
					message += ' (' + response.account_name + ')';
				}
				statusElement.textContent = message;
			}).catch(function (error) {
				statusElement.className = 'dsic-status dsic-status-error';
				statusElement.textContent = error.message || dsic_ajax.strings.error || 'Connection failed';
			}).finally(function () {
				testButton.disabled = false;
			});
		});
	};

	/**
	 * Initialize Linnworks connection test button.
	 */
	DSIC.initLinnworksConnectionTest = function () {
		const testButton = document.getElementById('dsic-test-linnworks-connection');
		const statusElement = document.getElementById('dsic-linnworks-connection-status');

		if (!testButton || !statusElement) return;

		testButton.addEventListener('click', function () {
			// Update UI to loading state.
			testButton.disabled = true;
			statusElement.className = 'dsic-status dsic-status-loading';
			statusElement.innerHTML = '<span class="dsic-spinner"></span> ' +
				(dsic_ajax.strings.testing || 'Testing...');

			// Make AJAX request.
			DSIC.ajax('linnworks_test_connection', {}).then(function (response) {
				statusElement.className = 'dsic-status dsic-status-success';
				statusElement.textContent = response.message || 'Connection successful!';
			}).catch(function (error) {
				statusElement.className = 'dsic-status dsic-status-error';
				statusElement.textContent = error.message || 'Connection failed';
			}).finally(function () {
				testButton.disabled = false;
			});
		});
	};

	/**
	 * Initialize Radar connection test button.
	 */
	DSIC.initRadarConnectionTest = function () {
		const testButton = document.getElementById('dsic-test-radar-connection');
		const statusElement = document.getElementById('dsic-radar-connection-status');

		if (!testButton || !statusElement) return;

		testButton.addEventListener('click', function () {
			// Update UI to loading state.
			testButton.disabled = true;
			statusElement.className = 'dsic-status dsic-status-loading';
			statusElement.innerHTML = '<span class="dsic-spinner"></span> ' +
				(dsic_ajax.strings.testing || 'Testing...');

			// Make AJAX request.
			DSIC.ajax('test_radar_connection', {}).then(function (response) {
				statusElement.className = 'dsic-status dsic-status-success';
				statusElement.textContent = response.message || 'Connection successful!';
			}).catch(function (error) {
				statusElement.className = 'dsic-status dsic-status-error';
				statusElement.textContent = error.message || 'Connection failed';
			}).finally(function () {
				testButton.disabled = false;
			});
		});
	};

	/**
	 * Initialize Linnworks test lock/unlock functionality.
	 */
	DSIC.initLinnworksTestPanel = function () {
		const orderIdInput = document.getElementById('dsic_test_order_id');
		const outputElement = document.getElementById('dsic-linnworks-test-output');
		const searchButton = document.getElementById('dsic-test-linnworks-search');
		const lockButton = document.getElementById('dsic-test-linnworks-lock');
		const unlockButton = document.getElementById('dsic-test-linnworks-unlock');
		const clearButton = document.getElementById('dsic-clear-test-output');

		if (!outputElement) return;

		/**
		 * Get current timestamp.
		 */
		function getTimestamp() {
			const now = new Date();
			return now.toLocaleTimeString('en-GB', { hour12: false }) + '.' +
				String(now.getMilliseconds()).padStart(3, '0');
		}

		/**
		 * Log message to output panel.
		 */
		function log(message, type) {
			type = type || 'info';
			const colors = {
				info: '#50c878',
				error: '#ff6b6b',
				warning: '#ffd93d',
				success: '#50c878',
				request: '#6bb3ff',
				response: '#c792ea'
			};
			const color = colors[type] || colors.info;
			const timestamp = getTimestamp();
			const line = '<span style="color: #888;">[' + timestamp + ']</span> ' +
				'<span style="color: ' + color + ';">' + DSIC.escapeHtml(message) + '</span>\n';
			outputElement.innerHTML += line;
			outputElement.scrollTop = outputElement.scrollHeight;
		}

		/**
		 * Log JSON data to output panel.
		 */
		function logJson(label, data, type) {
			type = type || 'response';
			const colors = {
				request: '#6bb3ff',
				response: '#c792ea',
				error: '#ff6b6b'
			};
			const color = colors[type] || colors.response;
			const timestamp = getTimestamp();
			const jsonStr = JSON.stringify(data, null, 2);
			const line = '<span style="color: #888;">[' + timestamp + ']</span> ' +
				'<span style="color: ' + color + ';">' + DSIC.escapeHtml(label) + ':</span>\n' +
				'<span style="color: #aaa;">' + DSIC.escapeHtml(jsonStr) + '</span>\n';
			outputElement.innerHTML += line;
			outputElement.scrollTop = outputElement.scrollHeight;
		}

		/**
		 * Get order reference from input.
		 */
		function getOrderRef() {
			const orderRef = orderIdInput ? orderIdInput.value.trim() : '';
			if (!orderRef) {
				log('Error: Please enter an order reference number', 'error');
				if (orderIdInput) orderIdInput.focus();
				return null;
			}
			return orderRef;
		}

		/**
		 * Disable/enable all test buttons.
		 */
		function setButtonsDisabled(disabled) {
			[searchButton, lockButton, unlockButton].forEach(function (btn) {
				if (btn) btn.disabled = disabled;
			});
		}

		// Search button handler.
		if (searchButton) {
			searchButton.addEventListener('click', function () {
				const orderRef = getOrderRef();
				if (!orderRef) return;

				log('─'.repeat(50), 'info');
				log('SEARCH ORDER: ' + orderRef, 'request');
				setButtonsDisabled(true);

				DSIC.ajax('linnworks_test_search', { order_id: orderRef })
					.then(function (response) {
						log('Search completed successfully', 'success');
						logJson('Linnworks Order Data', response.order_data || response, 'response');
						if (response.linnworks_id) {
							log('Linnworks Order ID: ' + response.linnworks_id, 'success');
						}
					})
					.catch(function (error) {
						log('Search failed: ' + (error.message || 'Unknown error'), 'error');
						if (error.details) {
							logJson('Error Details', error.details, 'error');
						}
					})
					.finally(function () {
						setButtonsDisabled(false);
					});
			});
		}

		// Lock button handler.
		if (lockButton) {
			lockButton.addEventListener('click', function () {
				const orderRef = getOrderRef();
				if (!orderRef) return;

				log('─'.repeat(50), 'info');
				log('LOCK ORDER: ' + orderRef, 'request');
				setButtonsDisabled(true);

				DSIC.ajax('linnworks_test_lock', { order_id: orderRef })
					.then(function (response) {
						log('Lock completed successfully', 'success');
						logJson('Response', response, 'response');
						if (response.linnworks_id) {
							log('Linnworks Order ID: ' + response.linnworks_id, 'success');
						}
						log('Order ' + orderRef + ' is now LOCKED in Linnworks', 'success');
					})
					.catch(function (error) {
						log('Lock failed: ' + (error.message || 'Unknown error'), 'error');
						if (error.details) {
							logJson('Error Details', error.details, 'error');
						}
					})
					.finally(function () {
						setButtonsDisabled(false);
					});
			});
		}

		// Unlock button handler.
		if (unlockButton) {
			unlockButton.addEventListener('click', function () {
				const orderRef = getOrderRef();
				if (!orderRef) return;

				log('─'.repeat(50), 'info');
				log('UNLOCK ORDER: ' + orderRef, 'request');
				setButtonsDisabled(true);

				DSIC.ajax('linnworks_test_unlock', { order_id: orderRef })
					.then(function (response) {
						log('Unlock completed successfully', 'success');
						logJson('Response', response, 'response');
						if (response.linnworks_id) {
							log('Linnworks Order ID: ' + response.linnworks_id, 'success');
						}
						log('Order ' + orderRef + ' is now UNLOCKED in Linnworks', 'success');
					})
					.catch(function (error) {
						log('Unlock failed: ' + (error.message || 'Unknown error'), 'error');
						if (error.details) {
							logJson('Error Details', error.details, 'error');
						}
					})
					.finally(function () {
						setButtonsDisabled(false);
					});
			});
		}

		// Clear button handler.
		if (clearButton) {
			clearButton.addEventListener('click', function () {
				outputElement.innerHTML = '<span style="color: #888;">// Output cleared...</span>\n';
			});
		}
	};

	/**
	 * Escape HTML entities.
	 */
	DSIC.escapeHtml = function (text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	};

	/**
	 * Initialize log control buttons.
	 */
	DSIC.initLogControls = function () {
		const refreshButton = document.getElementById('dsic-refresh-logs');
		const clearButton = document.getElementById('dsic-clear-logs');
		const logContent = document.getElementById('dsic-log-content');

		if (refreshButton && logContent) {
			refreshButton.addEventListener('click', function () {
				// Reload the page to refresh logs.
				window.location.reload();
			});
		}

		if (clearButton && logContent) {
			clearButton.addEventListener('click', function () {
				if (!confirm(dsic_ajax.strings.confirm_clear || 'Are you sure you want to clear all logs?')) {
					return;
				}

				clearButton.disabled = true;

				DSIC.ajax('clear_logs', {}).then(function () {
					logContent.value = dsic_ajax.strings.logs_cleared || 'Logs cleared successfully.';
				}).catch(function (error) {
					alert(error.message || 'Failed to clear logs.');
				}).finally(function () {
					clearButton.disabled = false;
				});
			});
		}
	};

	/**
	 * Make AJAX request.
	 *
	 * @param {string} action - Action name (without dsic_ prefix).
	 * @param {Object} data   - Request data.
	 * @returns {Promise}
	 */
	DSIC.ajax = function (action, data) {
		const formData = new URLSearchParams();
		formData.append('action', 'dsic_' + action);
		formData.append('nonce', dsic_ajax.nonce);

		for (const key in data) {
			if (data.hasOwnProperty(key)) {
				formData.append(key, data[key]);
			}
		}

		return fetch(dsic_ajax.ajax_url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: formData
		})
		.then(function (response) {
			return response.json();
		})
		.then(function (data) {
			if (!data.success) {
				throw new Error(data.data && data.data.message ? data.data.message : 'Unknown error');
			}
			return data.data;
		});
	};

	/**
	 * Initialize email template controls.
	 */
	DSIC.initEmailTemplates = function () {
		// Send test email buttons.
		const testEmailButtons = document.querySelectorAll('.dsic-send-test-email');
		testEmailButtons.forEach(function (button) {
			button.addEventListener('click', function () {
				const emailType = this.getAttribute('data-type');
				const originalText = this.innerHTML;

				// Get recipient from the input field.
				const recipientInput = document.getElementById('dsic_test_email_' + emailType);
				let recipient = recipientInput ? recipientInput.value : '';

				// Validate email.
				if (!recipient || !DSIC.validateEmail(recipient)) {
					DSIC.notify(dsic_ajax.strings.invalid_email || 'Please enter a valid email address.', 'error');
					if (recipientInput) recipientInput.focus();
					return;
				}

				this.disabled = true;
				this.innerHTML = '<span class="dsic-spinner"></span> ' +
					(dsic_ajax.strings.sending || 'Sending...');

				DSIC.ajax('send_test_email', {
					email_type: emailType,
					recipient: recipient
				}).then(function (response) {
					DSIC.notify(response.message || 'Test email sent!', 'success');
				}).catch(function (error) {
					DSIC.notify(error.message || 'Failed to send test email.', 'error');
				}).finally(function () {
					button.disabled = false;
					button.innerHTML = originalText;
				});
			});
		});

		// Reset template buttons.
		const resetButtons = document.querySelectorAll('.dsic-reset-template');
		resetButtons.forEach(function (button) {
			button.addEventListener('click', function () {
				if (!confirm(dsic_ajax.strings.confirm_reset || 'Are you sure you want to reset this template to default?')) {
					return;
				}

				const emailType = this.getAttribute('data-type');
				const originalText = this.innerHTML;

				this.disabled = true;
				this.innerHTML = '<span class="dsic-spinner"></span> ' +
					(dsic_ajax.strings.resetting || 'Resetting...');

				DSIC.ajax('reset_email_template', {
					email_type: emailType
				}).then(function (response) {
					// Notify then reload — TinyMCE editors may not be initialised for collapsed
					// accordion sections, so in-place updates are unreliable. Reload guarantees
					// the fresh server-rendered content is shown.
					DSIC.notify(response.message || 'Template reset to default.', 'success');
					setTimeout(function () { location.reload(); }, 800);
				}).catch(function (error) {
					DSIC.notify(error.message || 'Failed to reset template.', 'error');
				}).finally(function () {
					button.disabled = false;
					button.innerHTML = originalText;
				});
			});
		});

		// Reset All Templates button.
		const resetAllButton = document.getElementById('dsic-reset-all-templates');
		if (resetAllButton) {
			resetAllButton.addEventListener('click', function () {
				if (!confirm(dsic_ajax.strings.confirm_reset_all || 'Are you sure you want to reset ALL email templates to their default values? This will overwrite any customizations you have made.')) {
					return;
				}

				const originalText = this.innerHTML;

				this.disabled = true;
				this.innerHTML = '<span class="dsic-spinner"></span> ' +
					(dsic_ajax.strings.resetting || 'Resetting...');

				DSIC.ajax('reset_all_email_templates', {}).then(function (response) {
					// Notify then reload — TinyMCE editors may not be initialised for collapsed
					// accordion sections, so in-place updates are unreliable. Reload guarantees
					// the fresh server-rendered content is shown.
					DSIC.notify(response.message || 'All templates reset to defaults.', 'success');
					setTimeout(function () { location.reload(); }, 800);
				}).catch(function (error) {
					DSIC.notify(error.message || 'Failed to reset templates.', 'error');
				}).finally(function () {
					resetAllButton.disabled = false;
					resetAllButton.innerHTML = originalText;
				});
			});
		}
	};

	/**
	 * Initialize accordions.
	 */
	DSIC.initAccordions = function () {
		const accordions = document.querySelectorAll('.dsic-accordion');

		accordions.forEach(function (accordion) {
			const header = accordion.querySelector('.dsic-accordion-header');

			if (!header) return;

			header.addEventListener('click', function () {
				// Toggle current accordion.
				accordion.classList.toggle('is-open');

				// Update ARIA attributes for accessibility.
				const content = accordion.querySelector('.dsic-accordion-content');
				const isOpen = accordion.classList.contains('is-open');

				header.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
				if (content) {
					content.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
				}
			});

			// Set initial ARIA attributes.
			const content = accordion.querySelector('.dsic-accordion-content');
			header.setAttribute('aria-expanded', 'false');
			if (content) {
				content.setAttribute('aria-hidden', 'true');
			}
		});
	};

	/**
	 * Initialize placeholder click-to-insert functionality.
	 */
	DSIC.initPlaceholderClicks = function () {
		const placeholderCodes = document.querySelectorAll('.dsic-placeholder-code');

		placeholderCodes.forEach(function (code) {
			code.addEventListener('click', function () {
				const placeholder = this.textContent;
				const editorId = this.getAttribute('data-editor');

				if (!editorId) return;

				// Insert into TinyMCE editor or textarea.
				DSIC.insertPlaceholder(placeholder, editorId);

				// Visual feedback.
				this.style.background = '#fff3cd';
				setTimeout(function () {
					code.style.background = '';
				}, 300);
			});
		});
	};

	/**
	 * Insert placeholder at cursor position in TinyMCE editor or textarea.
	 *
	 * @param {string} placeholder - The placeholder text to insert.
	 * @param {string} editorId    - The ID of the editor/textarea.
	 */
	DSIC.insertPlaceholder = function (placeholder, editorId) {
		if (typeof tinymce !== 'undefined') {
			var editor = tinymce.get(editorId);
			if (editor && !editor.isHidden()) {
				editor.execCommand('mceInsertContent', false, placeholder);
				return;
			}
		}

		// Fallback to textarea.
		var textarea = document.getElementById(editorId);
		if (textarea) {
			var cursorPos = textarea.selectionStart;
			var textBefore = textarea.value.substring(0, cursorPos);
			var textAfter = textarea.value.substring(cursorPos);
			textarea.value = textBefore + placeholder + textAfter;
			textarea.selectionStart = textarea.selectionEnd = cursorPos + placeholder.length;
			textarea.focus();
		}
	};

	/**
	 * Initialize dashboard redact buttons.
	 */
	DSIC.initDashboardRedact = function () {
		const redactButtons = document.querySelectorAll('.dsic-dashboard-redact-btn');

		redactButtons.forEach(function (button) {
			button.addEventListener('click', function () {
				const orderId = this.getAttribute('data-order');
				const confirmMessage = dsic_ajax.strings.confirm_redact ||
					'Are you sure you want to delete the verification data from Stripe? This action cannot be undone.';

				if (!confirm(confirmMessage)) {
					return;
				}

				const originalHtml = this.innerHTML;
				const btn = this;

				btn.disabled = true;
				btn.innerHTML = '<span class="dsic-spinner"></span>';

				DSIC.ajax('redact_verification', {
					order_id: orderId
				}).then(function (response) {
					DSIC.notify(response.message || 'Data deletion request sent.', 'success');
					// Replace button with checkmark.
					const badge = document.createElement('span');
					badge.className = 'dsic-redacted-badge';
					badge.title = dsic_ajax.strings.data_deleted || 'Data deleted';
					badge.innerHTML = '<span class="dashicons dashicons-yes"></span>';
					btn.parentNode.replaceChild(badge, btn);
				}).catch(function (error) {
					DSIC.notify(error.message || 'Failed to request data deletion.', 'error');
					btn.disabled = false;
					btn.innerHTML = originalHtml;
				});
			});
		});
	};

	/**
	 * Validate email address.
	 *
	 * @param {string} email - Email address to validate.
	 * @returns {boolean} True if valid.
	 */
	DSIC.validateEmail = function (email) {
		var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		return re.test(email);
	};

	/**
	 * Show notification.
	 *
	 * @param {string} message - Notification message.
	 * @param {string} type    - Notification type (success, error, warning).
	 */
	DSIC.notify = function (message, type) {
		type = type || 'success';

		// Add icon based on type.
		let icon = '';
		if (type === 'success') {
			icon = '<span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 20px; vertical-align: middle; margin-right: 5px;"></span>';
		} else if (type === 'error') {
			icon = '<span class="dashicons dashicons-warning" style="color: #dc3232; font-size: 20px; vertical-align: middle; margin-right: 5px;"></span>';
		} else if (type === 'warning') {
			icon = '<span class="dashicons dashicons-info" style="color: #f56e28; font-size: 20px; vertical-align: middle; margin-right: 5px;"></span>';
		}

		const notice = document.createElement('div');
		notice.className = 'notice notice-' + type + ' is-dismissible';
		notice.style.padding = '12px';
		notice.innerHTML = '<p style="margin: 0.5em 0; font-size: 14px;">' + icon + message + '</p>' +
			'<button type="button" class="notice-dismiss">' +
			'<span class="screen-reader-text">Dismiss this notice.</span>' +
			'</button>';

		const wrap = document.querySelector('.wrap');
		if (wrap) {
			// Remove any existing notifications of the same type.
			const existingNotices = wrap.querySelectorAll('.notice.notice-' + type);
			existingNotices.forEach(function(existing) {
				existing.remove();
			});

			wrap.insertBefore(notice, wrap.firstChild);

			// Add dismiss handler.
			notice.querySelector('.notice-dismiss').addEventListener('click', function () {
				notice.remove();
			});

			// Auto-dismiss after 5 seconds.
			setTimeout(function () {
				if (notice.parentNode) {
					notice.remove();
				}
			}, 5000);

			// Scroll to top to show the notification.
			window.scrollTo({ top: 0, behavior: 'smooth' });
		}
	};

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', DSIC.init);
	} else {
		DSIC.init();
	}

})();
