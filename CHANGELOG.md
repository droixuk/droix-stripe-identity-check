# Changelog

All notable changes to the Stripe ID Check plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Added a public GitHub `README.md` explaining the DROIX use case, free non-commercial intent, plugin features, privacy model, and optional support via DROIX.store.

### Changed
- Updated WordPress.org-style `readme.txt` and plugin metadata for clearer public distribution messaging.
- Disabled bundled amount-threshold currency rates; multi-currency threshold checks now require rates supplied via `dsic_amount_threshold_exchange_rates` to avoid stale conversion data.
- Defaulted Slack notification events to off for new installs so merchants opt in before sending order details to Slack.

### Security
- Stopped rendering saved Stripe, Radar, Linnworks, and Slack secrets back into admin form fields; blank secret fields now preserve existing saved values.
- Changed the external Linnworks REST API key to header-only authentication with hashed storage, one-time display on generation, legacy plaintext migration, and failed-attempt throttling.
- Moved plugin debug logs out of the public uploads path and added redaction for common API keys, webhook secrets, tokens, Slack webhook URLs, and email addresses.
- Removed private GitHub compare links from the changelog and documented public-repository preparation requirements in `AGENTS.md`.

---

## [1.10.1] - 2026-04-03

### Fixed
- **Stale auto-verification hold enforcement after cancellation**: Orders could still be pushed back to `on-hold` after an ID check was cancelled because checkout enforcement only exempted `verified` orders and the Stripe `identity.verification_session.canceled` webhook did not clear the active verification state. Hold enforcement now applies only while `_dsic_verification_status` is `pending`, and both admin cancellation and the Stripe cancel webhook clear the active verification and checkout auto-verification flags before restoring the order flow.

---

## [1.9.6] - 2026-03-13

### Added
- **Separate Radar webhook secret**: New Live/Test Webhook Secret fields under Settings → Radar → Stripe API Keys (Radar Account). Required when Radar runs on a different Stripe account than Identity — each Stripe account issues its own webhook signing secret. `DSIC_Stripe_API::get_radar_webhook_secret()` falls back to the main webhook secret if no Radar-specific one is configured. `verify_webhook_signature()` now tries the Identity secret first, then the Radar secret, accepting whichever matches.

---

## [1.9.5] - 2026-03-13

### Fixed
- **Slack test button broken**: "Test Connection" button on the Slack settings tab halted on "Testing…" due to `dsicAdmin is not defined` JS error. The inline script was referencing `dsicAdmin.nonce` instead of the correct localized object `dsic_ajax.nonce`.

---

## [1.9.4] - 2026-03-13

### Added
- **`radar.early_fraud_warning.updated` webhook handler**: New handler for Stripe's `radar.early_fraud_warning.updated` event. Records the updated `fraud_type` and `status` as an order note (audit trail only). Does not re-trigger verification — the order is already in progress or completed. Requires the Early Fraud Warnings feature to be enabled.

---

## [1.9.3] - 2026-03-13

### Added
- **Slack notifications**: New configurable Slack webhook integration (Settings → Slack tab). Per-event toggles for: ID check triggered (with inferred trigger reason), ID check passed, ID check failed, and Early Fraud Warning received. Messages use Slack Block Kit with buttons linking to the WooCommerce order and Stripe dashboard.
- **Already-dispatched warning in CRM notification email**: When an Early Fraud Warning arrives and the WC order is already in `processing` or `completed` status, a prominent red banner is shown at the top of the CRM notification email: "!!! IMPORTANT — ORDER MAY ALREADY BE DISPATCHED !!!"

### Fixed
- **EFW Linnworks lock skip**: Early Fraud Warnings were incorrectly skipping the Linnworks lock because `on_verification_requested()` saw the `_dsic_auto_verification_triggered` flag (which EFW also sets) and bailed. EFW now sets a new `_dsic_efw_triggered` meta key, and the Linnworks skip condition is updated to only skip when `_dsic_auto_verification_triggered` is set AND `_dsic_efw_triggered` is NOT set. This means checkout-time auto-verifications (order not yet in Linnworks) still skip the lock, but EFW-triggered verifications (order may be days old) correctly attempt to lock.
- **Template reset not clearing WC email settings**: After "Reset All Defaults", the WooCommerce → Settings → Emails page still showed stale subject/heading values. Root cause: WC stores subject/heading overrides in `woocommerce_dsic_{type}_settings` when edited via its own settings page, and the plugin's reset only updated `dsic_email_*` options. Fix: both `ajax_reset_email_template()` and `ajax_reset_all_email_templates()` now also call `delete_option( 'woocommerce_dsic_{type}_settings' )` so WC falls back to the class defaults.

### Changed
- Added clarifying note near Reset buttons on the Email Templates settings tab: "Note: Template changes made via WooCommerce → Settings → Emails are separate from the customizations managed here."

---

## [1.9.2] - 2026-03-11

### Fixed
- **Radar thank-you page notice not appearing**: When the Stripe charge ID wasn't available at `woocommerce_payment_complete` time (causing the Radar check to be deferred to Action Scheduler), the thank-you page notice was never shown even though the verification email was sent. The notice now attempts the Radar check synchronously on the thank-you page — by this point the PaymentIntent always has a `latest_charge` — so the notice displays in real-time for customers.

---

## [1.9.1] - 2026-03-11

### Changed
- **Default customer-facing messages rewritten**: All default checkout notice, thank-you page messages, and email templates have been rewritten with a warm, reassuring, and gently humorous tone. The new copy explains *why* the check is happening (payment provider fraud prevention, not suspicion of the customer), creates continuity between the thank-you page and the email, replaces "we will contact you" passivity with "reply to this email" proactive guidance, and removes all "ACTION REQUIRED" / "placed on hold" anxiety-inducing language. Existing saved settings are unaffected — only the defaults for new installs and blank options have changed.

---

## [1.9.0] - 2026-03-11

### Added
- **Radar minimum order amount**: New setting to skip Radar fraud checks for orders below a configurable amount. Set to 0 to check all orders (default). Prevents unnecessary ID verification friction on low-value orders.
- **Radar thank you page message**: Configurable message shown on the order confirmation page when Stripe Radar triggers ID verification. Explains clearly that the order is on hold for a payment provider security check. Displayed independently of the address-mismatch notice (works even when address-mismatch auto-verify is disabled).

### Changed
- Address-mismatch thank you notice no longer shows when Radar specifically triggered the verification — the Radar-specific message is shown instead, avoiding duplicate notices.

---

## [1.8.1] - 2026-03-11

### Fixed
- **Radar connection test button**: Added missing AJAX handler and JavaScript wiring so the "Test Radar Connection" button now works correctly
- **Radar API key UX**: Clarified settings description to explain that separate Radar keys are only needed if using a different Stripe account for payments

---

## [1.8.0] - 2026-02-17

### Added
- **Stripe Radar Fraud Detection**: Automatically trigger ID verification when Stripe Radar flags a payment as high risk
  - Supports both Risk Level (all accounts) and Risk Score (Radar for Fraud Teams) detection modes
  - Configurable thresholds for both risk level (elevated/highest) and risk score (0-99)
  - Early Fraud Warning support for retroactive issuer alerts
  - Delayed retry mechanism (Action Scheduler) when charge data isn't immediately available
  - Separate Radar API credentials for charge/payment data access (optional, falls back to Identity keys)
- **Radar risk data display**: Order meta box shows risk level badge, risk score, and early fraud warning status
- **New Stripe API methods**: `get_charge()` and `get_payment_intent_charge()` for Radar integration
- **New webhook handler**: `radar.early_fraud_warning.created` event processing
- **Fraud Detection settings section**: General tab controls for Radar check configuration
- **Radar API credentials section**: API tab with separate test/live keys and connection test

---

## [1.7.1] - 2026-01-11

### Fixed
- **Address Comparison False Positives**: Fixed auto-verification triggering incorrectly for same address entered differently
  - Previous logic compared addresses field-by-field (address_1 vs address_1, etc.)
  - Caused false positives when customers distributed same address across different fields
  - Example: "Cockhill Road, 1 Willow Grove" vs "1 Willow Grove, Cockhill Road, Buncrana"
  - Now uses content-based similarity matching (85% threshold)
  - Combines all address fields and compares word content instead of field positions
  - Strict matching still enforced for country and postcode
  - Added detailed debug logging for address comparison decisions

---

## [1.7.0] - 2026-01-08

### Fixed
- **CRITICAL: Plugin Activation Error**: Fixed fatal error during activation - "Class DSIC_Logger not found"
  - Added class existence checks before calling logger in activation code paths
  - Fixed in `DSIC_Compliance_Report::create_table()` (line 53)
  - Fixed in `DSIC_Auto_Redaction::schedule_daily_check()` (lines 45, 52, 66)
  - Logger class may not be loaded during activation hook execution
  - Plugin now activates successfully on fresh installations via WP-CLI and admin UI

### Added
- **Automatic Data Redaction System**: GDPR-compliant automatic deletion of verification data from Stripe
  - Configurable retention period (30-365 days)
  - Daily batch processing with staggered execution (prevents API rate limits)
  - Action Scheduler integration with WP-Cron fallback
  - Automatic retry on failure (up to 3 attempts)
  - Customer email notification after deletion
  - Comprehensive compliance audit log with CSV export
- **Data Retention Settings Tab**: New admin settings page for auto-redaction configuration
  - Enable/disable auto-redaction
  - Set retention period (days)
  - Configure daily batch size (10-50 orders)
  - Set processing schedule time
  - Toggle customer notifications
  - Visual status indicators showing next scheduled run
- **GDPR Compliance Report Dashboard**: Dedicated compliance audit log page
  - Real-time statistics (today, this month, all time)
  - Filter by status (completed, failed, pending) and date range
  - Export to CSV for compliance audits
  - Shows retention days, verification dates, and error details
  - Direct link from Data Retention settings
- **Enhanced Manual Redaction**: Improved manual data deletion from order page
  - Shows redaction status (completed, pending, failed)
  - Retry button for failed redactions
  - Displays completion date and retention days
  - Integrated with auto-redaction system for consistent handling
  - Real-time status updates in order meta box
- **Compliance Logging**: Automatic audit trail for all redaction activities
  - Custom database table `wp_dsic_compliance_log`
  - Tracks order ID, action, status, timestamp, and details
  - Indexed for fast queries and reporting
  - Permanent record for regulatory compliance

### Changed
- **Redaction Status Values**: Updated from 'redacted' to 'completed' for consistency
  - Order meta now uses 'completed', 'pending', 'failed' status values
  - Old 'redacted' status still supported for backward compatibility
- **Auto-Deletion Messaging**: Order meta box now shows configurable retention period
  - Displays actual retention days from settings (e.g., "Auto-deletes after 30 days")
  - Shows "Auto-deletion disabled" when feature is off
  - Updates dynamically based on current settings

### Technical Details
- **New Classes**:
  - `DSIC_Auto_Redaction`: Core redaction logic, scheduling, error handling
  - `DSIC_Compliance_Report`: Audit log queries, statistics, CSV export
- **New Files**:
  - `includes/class-dsic-auto-redaction.php` (375 lines)
  - `includes/class-dsic-compliance-report.php` (205 lines)
  - `admin/partials/compliance-report-page.php` (compliance dashboard template)
- **Database Changes**:
  - New table: `wp_dsic_compliance_log` with indexes on order_id, action, status, created_at
  - New order meta keys: `_dsic_data_redaction_status`, `_dsic_data_redaction_requested`, `_dsic_data_redaction_completed`, `_dsic_redaction_email_sent`, `_dsic_redaction_error_count`, `_dsic_redaction_error_message`
  - New options: `dsic_auto_redaction_enabled`, `dsic_redaction_days`, `dsic_redaction_batch_size`, `dsic_redaction_schedule_time`, `dsic_redaction_notify_customer`
- **Hooks & Actions**:
  - `dsic_daily_redaction_check`: Daily scheduled action to find eligible orders
  - `dsic_redact_order_data`: Individual order redaction action (staggered)
  - `dsic_data_redaction_complete`: Fired when redaction completes (triggers customer email)
- **Action Scheduler Integration**:
  - Uses `as_schedule_recurring_action()` for daily checks
  - Uses `as_schedule_single_action()` for individual redaction tasks
  - Falls back to WP-Cron if Action Scheduler unavailable
  - Supports retry on transient failures
- **Order Handler Updates**:
  - `redact_verification_data()` now calls `DSIC_Auto_Redaction::manual_redaction()`
  - Enhanced `render_redaction_section()` with status-specific UI
  - Shows retry button for failed redactions
  - Displays completion timestamps
- **Uninstall Cleanup**:
  - Drops `wp_dsic_compliance_log` table if "Delete data on uninstall" enabled
  - Removes all redaction options and order meta
  - Unschedules all cron events (both WP-Cron and Action Scheduler)
- **Menu Changes**:
  - New hidden menu item: "GDPR Compliance Report" (`admin.php?page=dsic-compliance-report`)
  - Accessible via Data Retention settings page

### GDPR Compliance
- **Article 17 (Right to Erasure)**: Automatic deletion of personal data after retention period
- **Article 5 (Data Minimization)**: Only retains data as long as necessary
- **Article 30 (Records of Processing)**: Complete audit log of all deletions
- **Article 13/14 (Transparency)**: Customer notifications about data deletion

---

## [1.6.3] - 2026-01-08

### Fixed
- **CRITICAL: Stripe API Parameter Error**: Fixed verification session creation failing with "Received unknown parameter: options[expires_after_seconds]"
  - Removed invalid `expires_after_seconds` parameter from Stripe Identity API call
  - This parameter doesn't exist in Stripe Identity API (only available for Checkout Sessions and Payment Links)
  - **Why it was there**: Likely added to try to automatically delete verification data after 30 days for GDPR compliance
  - **Correct approach**: Stripe Identity doesn't auto-delete data - you must manually call the redaction API
  - Plugin already has `redact_verification_session()` method for manual redaction
  - This was causing all verification links to fail with "Unable to start verification" error since approximately Jan 6, 2026
  - Verification sessions now create successfully and redirect customers to Stripe

### Important: Data Retention Notes
- **Session Expiry**: Verification sessions have a fixed 90-day URL expiry (cannot be customized)
- **Data Retention**: Customer verification data is NOT automatically deleted by Stripe
- **Manual Redaction Required**: Use the redaction button in order admin or call redaction API manually
- **Future Enhancement**: Automatic redaction after X days could be implemented via WordPress cron

### Technical Details
- Removed lines 304-307 in `class-dsic-stripe-api.php` that set invalid `options[expires_after_seconds]` parameter
- Updated log message to remove reference to expiry days setting
- The `dsic_session_expiry_days` setting is now ignored (Stripe uses fixed 90-day expiry)

---

## [1.6.2] - 2026-01-08

### Added
- **Verification Link in Order Notes**: Order notes now include the customer verification link
  - Customer Service can click the link to test if it's working
  - CS can copy the URL to send manually to customers if needed
  - Appears after "Verification email sent to customer" message
  - Format: `🔗 Verification Link: [full URL]`

### Fixed
- **Address Comparison for Auto-Verification**: Fixed issue where verification was triggered even when billing and shipping addresses were identical
  - Classic checkout now verifies addresses actually differ (not just checkbox state)
  - Added robust address normalization to handle minor formatting differences
  - Addresses are now compared after removing extra whitespace and punctuation
  - Prevents false positives when customers check "Ship to different address" but enter the same address
  - Improves customer experience by only triggering verification when genuinely needed
- **Linnworks Auto-Lock for Auto-Triggered Verifications**: Fixed Linnworks attempting to lock orders that haven't synced yet
  - Linnworks integration now skips auto-lock when verification is auto-triggered (different shipping address)
  - Orders auto-triggered for verification are immediately placed on hold before Linnworks sync
  - Prevents API errors when trying to lock non-existent Linnworks orders
  - Manual verification requests still trigger Linnworks auto-lock as expected

### Technical Details
- Enhanced `maybe_mark_for_verification()` to call `addresses_differ()` for validation
- Added `normalize_address_field()` method for consistent address comparison
- Removes common punctuation (., , -, #) that don't affect address meaning
- Normalizes whitespace (trim and collapse multiple spaces)
- Case-insensitive comparison for all address fields
- Added verification URL to order notes in `DSIC_Order_Handler::request_verification()`
- Added check in `DSIC_Linnworks_Integration::on_verification_requested()` to detect `_dsic_auto_verification_triggered` meta
- Linnworks auto-lock is skipped for auto-triggered orders to prevent sync errors

---

## [1.6.0] - 2025-12-24

### Added
- **Auto-Verification for Different Shipping Addresses**: Automatically trigger ID verification when customers select "Ship to a different address"
  - New setting toggle in General Settings → Auto-Verification
  - Customizable checkout warning message (WPML translatable)
  - Customizable thank you page confirmation message (WPML translatable)
  - Lightweight JavaScript notice that appears/disappears with checkbox
  - Zero performance impact on checkout (client-side only until order placement)
- **Top-Level Admin Menu**: "ID Check" now appears as a top-level WordPress admin menu
  - Positioned at priority 56 (after Payments)
  - Uses `dashicons-id-alt` (ID card icon)
  - Still available under DROIX Plugins menu as secondary access point
- **CRM Notification for Auto-Triggered Verifications**: Admin receives CRM email when auto-verification triggers
  - New `auto_triggered` status with blue styling
  - Displays reason (different shipping address selected)
  - Prominent "Review Order Now" button with order edit URL
  - Full details in both HTML and plain text email templates
- **Thank You Page Notice**: Customers see a styled notice when their order triggers auto-verification
  - Explains verification requirement and email instructions
  - Links well with verification request email

### Changed
- Orders with different billing/shipping addresses are automatically placed on hold
- Verification request is automatically sent to customer (no admin action required)
- Checkout class loads on both frontend and backend for proper order processing

### Technical Details
- New files: `public/class-dsic-checkout.php`, `assets/js/checkout.js`, `assets/css/checkout.css`
- Modified: Settings, WPML strings, menu registration, CRM email templates
- Block checkout support via `woocommerce_store_api_checkout_order_processed` hook
- HPOS-compatible order edit URLs in CRM notifications

---

## [1.5.8] - 2025-12-20

### Changed
- **Clickable Stripe URL in Order Notes**: The Stripe dashboard link in verification passed/failed order notes is now a clickable hyperlink
  - Admins can click directly to open the Stripe verification session in a new tab
  - Shows "View in Stripe Dashboard" instead of raw URL

---

## [1.5.7] - 2025-12-20

### Fixed
- **CRITICAL: Stripe API Error "unknown parameter: options[email]"**: Removed invalid `options[email]` parameter
  - Stripe Identity API does not support `options[email]` or `options[phone]` parameters
  - Customer email is always passed via `provided_details[email]` to pre-fill the form
  - Phone number is passed via `provided_details[phone]` when enabled

### Changed
- **Renamed "Verify Phone Number" to "Pre-fill Phone Number"**: Clarified what this setting actually does
  - Pre-fills the customer's phone in the verification form (requires E.164 format like +447123456789)
  - This is NOT a separate phone verification step, just form pre-population

### Removed
- **"Verify Email Ownership" setting**: This feature was never supported by Stripe Identity API

---

## [1.5.6] - 2025-12-20

### Fixed
- **Exclude Cloned Orders from Linnworks Search**: Orders with `SubSource: API_CLONE` are now skipped
  - Prevents locking cloned orders instead of originals (e.g., CLONE-1272317 vs 1272317)
  - New `find_matching_order()` method filters results and prefers exact reference matches
  - Logs when cloned orders are skipped for debugging

---

## [1.5.5] - 2025-12-18

### Added
- **WC Order URL in Linnworks Notes**: Lock/unlock notes now include clickable WC order admin URL
- **Stripe URL in Lock Notes**: Lock notes now include Stripe Identity session URL (previously only in unlock)
- **Note Preservation**: Linnworks notes are now appended instead of replaced
  - New `get_order_notes()` method fetches existing notes before adding new ones
  - Existing order notes are preserved when adding verification lock/unlock notes

### Changed
- Enhanced Linnworks note format now includes both WC admin URL and Stripe dashboard URL
- Example lock note: `[LOCKED] ID verification pending | Date: 2025-12-18 14:30:00 | WC Order: #123 | Customer: email | WC URL: https://... | Stripe: https://... | Requested by: Admin | (Stripe ID Check Plugin)`

---

## [1.5.4] - 2025-12-18

### Added
- **Enhanced Linnworks Order Notes**: Notes added to Linnworks orders now include detailed information
  - Lock note includes: Date/time, WC Order ID, Customer email, Requested by (admin name)
  - Unlock note includes: Date/time, WC Order ID, Customer email, Stripe session URL
  - Format: `[LOCKED/UNLOCKED] Reason | Date: YYYY-MM-DD HH:MM:SS | WC Order: #123 | Customer: email | Stripe: URL`

---

## [1.5.3] - 2025-12-18

### Fixed
- **CRITICAL: Linnworks Integration Never Enabled**: Fixed `is_enabled()` check that always returned false
  - Settings page saves option as `'1'`/`'0'` but check compared against `'yes'`/`'no'`
  - This caused auto-lock/unlock to never work even when integration was enabled in settings
  - Manual lock/unlock worked because AJAX handlers don't use this check

### Added
- **Order Status Restore on Cancel**: When verification is cancelled, order status returns to "processing"
  - Previously orders stayed on-hold after cancellation
  - Linnworks lock is also removed when verification is cancelled

---

## [1.5.2] - 2025-12-18

### Fixed
- **Logging Always Works**: Errors and warnings are now ALWAYS logged regardless of debug mode setting
  - Previously, all logging was suppressed when debug mode was off
  - Critical errors were silently ignored on production systems
- **Linnworks Unlock on Cancel**: Cancelling ID verification now properly unlocks the order in Linnworks
  - New `dsic_verification_cancelled` action hook triggers automatic unlock
  - Order note added when Linnworks unlock occurs after cancellation
- **Enhanced Order Notes**: All verification events now include detailed information for CS team
  - Verification request: includes admin name, date/time, customer email, order URL
  - Verification passed: includes date/time, customer info, Stripe dashboard URL
  - Verification failed: includes error reason, error code, Stripe dashboard URL
  - Linnworks lock/unlock: includes date/time, Linnworks ID, reason

### Added
- **Comprehensive Logging**: Added detailed logging throughout verification workflow
  - Logs when Linnworks integration is enabled/disabled during init
  - Logs before and after triggering `dsic_verification_requested` action
  - Logs Linnworks auto-lock setting status
  - Logs all major steps in verification request flow
- **Linnworks Cancel Handler**: New `on_verification_cancelled()` method to unlock orders when verification is cancelled

### Changed
- Logger now distinguishes between always-log levels (ERROR, WARNING) and debug-only levels (INFO, DEBUG)

---

## [1.5.1] - 2025-12-18

### Changed
- **Enhanced Order Notes**: All order notes now include detailed information for CS team
  - Request verification: admin name, date/time, customer email, order edit URL
  - Verification passed: date/time, customer name and email, Stripe dashboard URL
  - Verification failed: error reason, error code, Stripe dashboard URL
  - Linnworks lock: date/time, reason, Linnworks ID
  - Linnworks unlock: date/time, reason, Linnworks ID, Stripe session URL

---

## [1.5.0] - 2025-12-18

### Added
- **Linnworks Integration**: Automatic order locking/unlocking based on ID verification status
  - Auto-lock orders in Linnworks when ID verification is requested
  - Auto-unlock orders when verification passes
  - Test connection functionality in settings
  - Test lock/unlock panel for existing orders
  - Full API logging to database table

---

## [1.3.0] - 2025-12-17

### Added
- **Email Verification Option**: Verify customer email ownership via verification code
  - New "Verify Email Ownership" toggle in General Settings → Verification Options
  - Sends verification code to customer's email before ID verification
  - Enabled by default for enhanced security
  - Uses `options[email]` Stripe API parameter
- **Phone Verification Option**: Verify customer phone number via SMS
  - New "Verify Phone Number" toggle in General Settings → Verification Options
  - Sends SMS verification code to customer's phone
  - Disabled by default (requires customer phone in E.164 format)
  - Uses `options[phone]` Stripe API parameter

### Changed
- **Verification Options moved** from API Settings tab to General Settings tab for better UX
- Enhanced verification session logging to include email/phone verification status
- Default value for email verification is enabled (recommended by Stripe)

### Improved
- **Test Email feedback** now shows detailed success/failure messages
  - Clear confirmation when test email is sent successfully
  - Detailed error messages if email fails (captures wp_mail errors)
  - Validates email address and template body before sending

---

## [1.2.1] - 2025-12-16

### Fixed
- **Test Connection Button**: Fixed connection test not working with dual API keys
  - JavaScript now reads correct input field based on Test Mode toggle state
  - PHP AJAX handler now falls back to correct option names (`dsic_live_secret_key` / `dsic_test_secret_key`)
  - Previously used deprecated option name `dsic_stripe_secret_key` which no longer exists
  - Resolves "API secret key is required" error when keys were saved but connection test failed

---

## [1.2.0] - 2025-12-16

### Added
- **Dual API Key Support**: Separate Test and Live API key sets
  - Live API Keys section (Publishable Key, Secret Key, Webhook Secret)
  - Test API Keys section (Publishable Key, Secret Key, Webhook Secret)
  - Automatic key selection based on Test Mode toggle
  - Visual mode indicator badges (Production/Sandbox)
- **Test Mode in API Settings**: Moved from General tab to API Settings tab
  - Toggle between Test Mode and Live Mode
  - Mode indicator badge shows current active environment
- **New API Methods**:
  - `DSIC_Stripe_API::get_publishable_key()` - Returns active publishable key
  - `DSIC_Stripe_API::get_webhook_secret()` - Returns active webhook secret
  - `DSIC_Stripe_API::is_test_mode()` - Returns current mode status

### Removed
- **OAuth 2.0 Integration**: Removed Stripe Apps OAuth authentication
  - OAuth was overly complex for typical WordPress plugin use case
  - Most plugins use simple API key authentication (industry standard)
  - Removed `DSIC_Stripe_OAuth` class and all related code
  - Removed OAuth UI from API Settings tab
  - Removed OAuth CSS styles and JavaScript handlers

### Changed
- **API Settings Tab**: Complete redesign with dual key architecture
  - Mode toggle at top with active mode indicator
  - Separate sections for Live and Test credentials
  - Webhook configuration section with generated URL
  - Test Connection validates currently active key set
- **Stripe API class**: Automatically selects correct API keys based on mode
- **Webhook Handler**: Uses `DSIC_Stripe_API::get_webhook_secret()` for signature verification
- **Settings class**: Returns active keys based on mode in `get_settings()`

---

## [1.1.1] - 2025-12-16

### Fixed
- **Settings Reset Bug**: Fixed issue where settings on one tab would reset when saving another tab
  - Added tab-aware form handling that only updates settings for the current tab
  - Added hidden field to track which tab is being saved
- **Default Values**: Fixed default values on fresh plugin install
  - Enable Plugin now correctly defaults to Active (was inactive)
  - Email templates now correctly default to Enabled
- **Settings Persistence**: Fixed settings sometimes resetting after plugin update
  - Changed all checkbox default values to use consistent '1'/'0' strings instead of boolean true/false
  - Ensured `add_option()` is used (not `update_option()`) to preserve existing user settings
  - Fixed `ensure_email_template_defaults()` to use string values
  - Fixed `ajax_reset_email_templates()` to use string values

### Changed
- Added `get_bool_option()` helper method in `DSIC_Settings` for consistent boolean option handling
- Updated `get_settings()` method to use helper with correct default values
- Updated `get_email_template()` method to use helper for enabled status

---

## [1.1.0] - 2025-12-16

### Added
- **Stripe OAuth 2.0 Integration**
  - New `DSIC_Stripe_OAuth` class for OAuth 2.0 authentication flow
  - One-click Stripe account connection without manual API key management
  - Secure token storage using AES-256-CBC encryption
  - Automatic token refresh before expiration via WordPress cron
  - Support for both Live and Test mode connections
  - OAuth state validation using WordPress transients
  - Disconnect functionality to revoke OAuth access

- **OAuth Settings UI**
  - New "Stripe Connection" section at top of API Settings tab
  - Visual connection status display (connected/disconnected states)
  - "Connect Live Account" and "Connect Test Account" buttons
  - Account ID and mode display when connected
  - Token expiration information
  - OAuth Configuration panel for Client ID and Redirect URI
  - Collapsible advanced settings section

- **Stripe API Enhancement**
  - Automatic OAuth token usage when connected
  - Fallback to manual API keys when OAuth not available
  - `is_using_oauth()` method to check authentication mode

### Changed
- `DSIC_Stripe_API` constructor now prioritizes OAuth tokens over manual keys
- API Settings tab reorganized with OAuth section above manual keys
- Manual API keys section explains it's not used when OAuth connected

### Technical Details
- OAuth authorization URL: `https://marketplace.stripe.com/oauth/v2/authorize`
- Token endpoint: `https://api.stripe.com/v1/oauth/token`
- Deauthorization endpoint: `https://api.stripe.com/v1/oauth/deauthorize`
- Encryption key derived from WordPress AUTH_KEY and SECURE_AUTH_KEY
- Token refresh scheduled 5 minutes before expiration
- State parameter expires after 10 minutes

---

## [1.0.1] - 2025-12-16

### Added
- **WPML/Polylang Multilingual Support**
  - New `DSIC_WPML` class for multilingual integration
  - Automatic registration of email template strings with WPML String Translation
  - Support for Polylang string registration
  - Order language detection from WPML/Polylang order meta
  - `get_translated_email_template()` method for language-aware email content
  - Language context switching helpers for email sending

### Compatibility
- **WPML Multilingual CMS** - Full compatibility
  - Email templates translatable via WPML String Translation
  - Order language respected when sending emails
  - Frontend verification status translatable

- **Polylang** - Full compatibility
  - String registration via `pll_register_string()`
  - Translation retrieval via `pll__()`

### Technical Details
- Strings registered: Email subject, heading, and body for all 4 email types
- String context: "Stripe ID Check" in WPML String Translation
- Order language sources: `wpml_language` meta, Polylang post language

---

## [1.0.0] - 2025-12-15

### 🎉 Production Release

First stable release of Stripe ID Check for WooCommerce.

### Features
- **Complete Stripe Identity Integration**
  - One-click verification requests from WooCommerce orders
  - Secure JIT (Just-In-Time) verification session creation
  - Real-time webhook status updates

- **Customer Email Notifications**
  - Verification request with secure link
  - Verification passed confirmation
  - Verification failed with support guidance
  - Data deletion confirmation

- **Admin Dashboard**
  - Quick stats overview (verified, pending, failed, success rate)
  - All verification checks table with status tracking
  - Delete data button for GDPR compliance
  - Export to CSV functionality

- **GDPR Compliance**
  - Manual data deletion from Stripe
  - Automatic 48-hour data deletion notices
  - Customer notification on data deletion

- **Customizable Email Templates**
  - Visual editor with HTML support
  - Click-to-insert placeholders
  - Send test emails
  - Reset to defaults

- **WooCommerce Integration**
  - HPOS (High-Performance Order Storage) compatible
  - Order meta box with verification status
  - Order actions dropdown integration
  - ID Check column in orders list

- **WordPress Dashboard Widget**
  - Quick verification stats
  - Recent verifications list
  - Success rate visualization

---

## [0.5.7] - 2025-12-15

### Added
- **Data Deletion Confirmation Email Template**
  - New customizable email template in Email Templates tab
  - Sent when verification data is deleted from Stripe (manual or automatic 48-hour)
  - Full editor with placeholders support
  - Lists deleted data types and privacy information

- **Development Documentation**
  - Added CLAUDE.md development guide
  - Asset loading architecture documentation
  - Screen ID reference for admin pages

### Fixed
- **Admin Assets Loading**
  - Fixed admin.js not loading on WooCommerce > ID Check page
  - Added `woocommerce_page_dsic-id-check` to plugin screens array

- **Email Template Settings Preservation**
  - Fixed email template settings being reset on plugin update
  - Changed `update_option` to `add_option` in ensure_email_template_defaults()
  - Settings now only populate if they don't exist

---

## [0.5.6] - 2025-12-15

### Added
- **Admin UI for Manual Data Deletion**
  - "Delete Data from Stripe" button in order meta box for verified/failed orders
  - Data deletion status tracking (pending/completed)
  - Confirmation dialog before deletion
  - Visual status indicators in meta box

- **Webhook Handling for Data Redaction**
  - Support for `identity.verification_session.redacted` webhook event
  - Automatic status update when Stripe confirms data deletion
  - Order note added when redaction is complete

- **Customer Email Notification**
  - New email template for data deletion confirmation
  - Lists what data was deleted (ID documents, selfie, extracted info)
  - Links to Stripe's privacy information

- **WooCommerce Menu Integration**
  - Added "ID Check" submenu under WooCommerce menu (below Payments)
  - Quick access to ID Check settings from WooCommerce menu

---

## [0.5.5] - 2025-12-15

### Added
- **Privacy Notice in Verification Emails**
  - 48-hour automatic data deletion notice in passed/failed emails
  - Option for customers to request manual deletion via support
  - Link to Stripe's privacy knowledge base

---

## [0.5.4] - 2025-12-15

### Changed
- **Improved Verification Request Email**
  - Added glasses tip: If ID shows you without glasses, remove glasses for selfie
  - Added 48-hour automatic data deletion notice (Stripe policy)
  - Added link to Stripe's privacy knowledge base for transparency
  - Helps improve verification success rates and builds customer trust

- **Improved Verification Failed Email**
  - Removed "Try Again" option - customers should NOT retry on their own
  - Added clear instruction: "Please wait for us to contact you"
  - Added "What happens next" section explaining support team review process
  - Prevents wasted verification attempts and reduces customer frustration

---

## [0.5.3] - 2025-12-15

### Added
- **Expanded Webhook Error Handling**
  - Support for manual review/override from Stripe dashboard
  - When admin overrides verification status in Stripe, order updates accordingly
  - New error codes supported:
    - `document_invalid` - Document is invalid
    - `document_fraudulent` - Document appears fraudulent
    - `document_incomplete` - Document is incomplete
    - `document_failed_copy` - Document is a copy/screenshot
    - `document_failed_greyscale` - Document not in color
    - `document_not_readable` - Document not readable
    - `selfie_manipulated` - Selfie appears manipulated
    - `id_number_mismatch` - ID number doesn't match
    - `under_supported_age` - Person under supported age
    - `country_not_supported` - Document country not supported
    - `fraudulent` - Flagged as fraudulent by admin

---

## [0.5.2] - 2025-12-15

### Added
- **Dashboard Widget Re-enabled**
  - ID Verification Stats widget now appears on WordPress admin dashboard
  - Shows: Verified, Pending, Failed counts
  - Success rate progress bar
  - Period stats table (Today, This Week, This Month, Last 30 Days)
  - Recent verifications list with links to orders

---

## [0.5.1] - 2025-12-15

### Changed
- **Order Status Flow Improvements**
  - Request verification now ALWAYS sets order to "on-hold" (unless completed/cancelled/refunded)
  - Passed verification changes order from "on-hold" to "processing"
  - Failed verification ensures order is set to "on-hold"
  - Prevents status changes for finalized orders (completed, cancelled, refunded, failed)

---

## [0.5.0] - 2025-12-15

### Added
- Track when customer clicks verification link (`_dsic_link_clicked` meta)
- Clear stats cache when verification link is clicked

---

## [0.4.2] - 2025-12-15

### Fixed
- **Phone Number Validation Error**
  - Fixed "is not a valid phone number" error when customer phone is in local format
  - Phone now only sent to Stripe if in E.164 format (starts with +)
  - Prevents API errors from non-international phone formats (e.g., 07477023030)

---

## [0.4.1] - 2025-12-15

### Fixed
- **Stripe API Parameter Error**
  - Fixed "Received unknown parameter: options[document][allowed_document_types]" error
  - Corrected parameter name from `allowed_document_types` to `allowed_types` per Stripe API spec
  - Verification sessions now create successfully

---

## [0.4.0] - 2025-12-15

### Added
- **Customer Verification Status Section**
  - New verification status section on customer's My Account order view page
  - Visual progress tracker showing: Requested → In Progress → Complete
  - Status badges with color-coded states (pending, verified, failed)
  - "Verify My Identity" button for pending verifications
  - "What you'll need" checklist with ID requirements
  - Timestamps showing when verification was requested and completed
  - Support for guest customers via thank you page
  - Fully responsive design for mobile devices
  - Accessibility features (keyboard navigation, reduced motion support)

- **New Files**
  - `public/class-dsic-frontend.php` - Frontend functionality class
  - `templates/myaccount/verification-status.php` - Overridable template
  - `assets/css/frontend.css` - Customer-facing styles

### Changed
- **Improved Return URL Flow**
  - After completing Stripe verification, customers now redirect to their order page
  - Verification status section shows the result in context of their order
  - Better integrated experience instead of standalone result pages

### Notes
- Template can be overridden by copying to: `yourtheme/woocommerce/myaccount/verification-status.php`
- Styles follow WooCommerce conventions and work with most themes
- Supports WCAG 2.1 AA accessibility standards

---

## [0.3.9] - 2025-12-15

### Added
- **Configurable Verification Options** in API Settings tab
  - Require Selfie Check toggle (compare selfie to ID photo)
  - Require ID Number Check toggle (verify ID number on document)
  - Require Live Capture toggle (prevent image uploads, enforce camera capture)
  - Allowed Document Types checkboxes (Driving Licence, ID Card, Passport)
- Customer email and phone automatically included in Stripe verification for improved outcomes
- New "Verification Options" section with clear descriptions

### Changed
- Stripe API now uses configurable verification settings instead of hardcoded values
- Enhanced logging to show verification options used when creating sessions

---

## [0.3.8] - 2025-12-15

### Fixed
- **Critical: Verification request emails not sending**
  - Email classes were not loaded during AJAX requests because WooCommerce mailer wasn't initialized
  - Added `WC()->mailer()` call before triggering email actions to ensure email classes are loaded
  - Affects: Request Verification and Resend Email buttons in order edit page

---

## [0.3.7] - 2025-12-15

### Added
- **Email Templates Tab Redesign**
  - New template section design with Active/Inactive status badges
  - Available Placeholders section below each email content editor
  - Grid layout for placeholders with descriptions
  - Click-to-insert functionality: clicking a placeholder inserts it at cursor position in TinyMCE editor
  - Test Email input field per template with Send Test Email button
  - Field descriptions for Subject, Heading, and Content fields

### Changed
- **Improved Default Email Templates**
  - Verification Request: Added detailed instructions, "What you'll need" list, "Why do we require ID verification?" section
  - Verification Passed: Added "What happens next?" section, Order Details list
  - Verification Failed: Added detailed reasons list, "What are your options?" section with numbered steps
  - All templates now use more professional formatting with HTML structure

### Notes
- Email Templates tab design now matches droix-woo-deposits plugin pattern
- Better user experience for template customization

---

## [0.3.6] - 2025-12-15

### Added
- **Setup Help Accordions** in API Settings tab
  - Step-by-step guide: How to get Stripe API Keys
  - Step-by-step guide: How to set up the Webhook
  - Step-by-step guide: How to get the Webhook Secret
  - Step-by-step guide: How to enable Stripe Identity
  - Accordion CSS styles with smooth animations
  - Accordion JavaScript with accessibility (ARIA attributes)
  - Visual notes with info/warning styling
  - Direct links to Stripe Dashboard sections

### Notes
- Improved onboarding experience for new users
- All instructions accessible directly within plugin settings

---

## [0.3.5] - 2025-12-15

### Changed
- Removed excessive debug logging added in 0.3.4
- Plugin no longer outputs to debug.log during normal operation
- Uses plugin's own DSIC_Logger for important events (writes to wp-content/uploads/dsic-logs/)

### Notes
- Clean production-ready code without debug noise

---

## [0.3.4] - 2025-12-15

### Fixed
- **Critical Bug: White Screen of Death (Root Cause)**
  - Email class files were being loaded too early at `plugins_loaded` time
  - `WC_Email` parent class is only available when WooCommerce mailer initializes
  - Deferred email class file loading to `woocommerce_email_classes` filter callback
  - Email classes now load at the correct time when `WC_Email` is guaranteed to exist

### Notes
- Root cause fix for WSOD - email classes must extend `WC_Email` which loads later

---

## [0.3.3] - 2025-12-15

### Fixed
- **Critical Bug: White Screen of Death**
  - Moved `woocommerce_email_classes` filter registration inside `dsic_init()`
  - Email classes are now guaranteed to be loaded before filter is registered
  - Prevents fatal error when WooCommerce initializes emails before plugin is fully loaded

### Notes
- Hotfix release addressing critical activation issue in 0.3.2

---

## [0.3.2] - 2025-12-15

### Added
- **Email Templates Tab**
  - WYSIWYG editor (wp_editor) for all email templates
  - Subject line and heading customization
  - Available shortcodes reference panel with tooltips
  - Enable/disable toggle for each email type

- **Send Test Email Functionality**
  - Test email button for each template type
  - Email recipient prompt with current user default
  - Uses sample order data for realistic preview
  - WooCommerce email header/footer styling

- **Reset to Default Functionality**
  - Reset button for each email template
  - Restores subject, heading, and body to defaults
  - AJAX-powered instant reset without page reload

- **Export to CSV**
  - Export all verification data to CSV file
  - Includes order details, status, timestamps, and error messages
  - Auto-generated filename with date

### Fixed
- **HPOS Detection Bug**
  - Wrapped HPOS detection in try-catch to prevent fatal errors
  - Uses safer `OrderUtil::custom_orders_table_usage_is_enabled()` method
  - Falls back to legacy screen ID on error

- **Stats Refresh Security**
  - Added nonce verification to stats refresh action
  - Prevents unauthorized cache clearing

### Changed
- Improved button styling with loading spinners during AJAX operations
- Added new localized strings for JavaScript

### Notes
- Post Phase 4 improvements - Bug fixes and feature enhancements
- All Priority 1 bugs fixed
- All Priority 2 features implemented

---

## [0.3.1] - 2025-12-15

### Added
- **Statistics System**
  - `DSIC_Stats` class for comprehensive statistics tracking
  - Statistics tab in settings page with detailed reports
  - Success rate visualization with circular progress indicator
  - Period-based statistics (Today, This Week, This Month, Last 30 Days)
  - Recent verifications table with order links
  - Average verification time calculation
  - Automatic cache clearing on status changes
  - HPOS-compatible database queries

- **Dashboard Widget**
  - `DSIC_Dashboard_Widget` for WordPress admin dashboard
  - Quick stats summary (Verified, Pending, Failed)
  - Success rate progress bar
  - Period comparison table
  - Recent verifications list
  - Link to full statistics page

- **Bulk Actions**
  - `DSIC_Bulk_Actions` for order list bulk operations
  - Request ID Verification (bulk)
  - Resend Verification Email (bulk)
  - Cancel Verification (bulk)
  - Result notices with processed/skipped/error counts
  - HPOS-compatible hooks

- **CSS Enhancements**
  - Dashboard widget styles
  - Statistics page styles
  - Success rate circle visualization
  - Responsive grid layouts

### Changed
- Main plugin file updated to initialize Stats, Dashboard Widget, and Bulk Actions

### Notes
- Phase 4 complete - Dashboard, Stats & Polish
- All four development phases complete
- Plugin is feature-complete for v1.0.0 release

---

## [0.2.1] - 2025-12-15

### Added
- **Order Integration**
  - `DSIC_Order_Handler` - Full order integration class
  - Order actions dropdown (Request/Resend/Cancel verification)
  - Order meta box showing verification status and actions
  - ID Check column in orders list (HPOS compatible)
  - Status badges with icons for pending/verified/failed states
  - Verification attempts tracking

- **Webhook & Verification Endpoints**
  - `DSIC_Webhook_Handler` - Complete webhook handling
  - JIT (Just-In-Time) verification endpoint via WooCommerce API
  - Secure token-based verification URL generation
  - Return URL handler for post-verification
  - REST API webhook endpoint (`dsic/v1/webhook`)
  - Stripe signature verification with timestamp tolerance
  - Automatic order status updates on verification completion
  - Human-readable error messages for all Stripe error codes

- **Verification Flow**
  - Customer clicks email link → validates token → creates/reuses Stripe session → redirects to Stripe
  - Automatic session reuse for incomplete verifications
  - Custom display pages for success/failure/processing states

### Changed
- Main plugin file updated to initialize Order Handler and Webhook Handler

### Notes
- Phase 3 complete - Order Integration & Verification Workflow
- Full verification flow now operational
- Ready for Phase 4 development

---

## [0.1.1] - 2025-12-15

### Added
- **Email System**
  - `DSIC_Email_Verification_Request` - Customer verification request email
  - `DSIC_Email_Verification_Passed` - Customer verification success email
  - `DSIC_Email_Verification_Failed` - Customer verification failure email
  - `DSIC_Email_CRM_Notification` - Admin/CRM notification email

- **Email Templates**
  - HTML templates with WooCommerce styling
  - Plain text templates for all emails
  - Verification button with customizable styling
  - Order details integration

- **Shortcodes System**
  - `DSIC_Shortcodes` - 15 shortcodes for email content
  - Customer shortcodes: name, first_name, last_name, email
  - Address shortcodes: billing_address, shipping_address
  - Order shortcodes: number, date, total, items, admin_url
  - Verification shortcodes: verification_link, verification_status
  - Site shortcodes: site_name, site_url
  - Context-based processing with order data

### Notes
- Phase 2 complete - Email System & Templates
- All 4 email types fully functional

---

## [0.0.1] - 2025-12-15

### Added
- **Plugin Foundation**
  - Main plugin file with WooCommerce dependency check
  - HPOS (High-Performance Order Storage) compatibility declaration
  - Plugin activation/deactivation hooks
  - Settings link on plugins page

- **Core Classes**
  - `DSIC_Loader` - Hook registration system
  - `DSIC_Activator` - Activation logic with default options
  - `DSIC_Deactivator` - Deactivation cleanup
  - `DSIC_i18n` - Internationalization support
  - `DSIC_Logger` - Debug logging with file management

- **Admin Interface**
  - `DSIC_Admin` - Admin asset management
  - `DSIC_Menu` - DROIX Plugins menu registration
  - `DSIC_Settings` - Settings registration and validation
  - Settings page with three tabs (General, API, Debug)
  - Toggle switches for boolean settings
  - Password visibility toggles for API keys
  - Copy-to-clipboard for webhook URL

- **Stripe API Integration**
  - `DSIC_Stripe_API` - Full API wrapper class
  - Connection testing functionality
  - Verification session create/get/cancel/redact methods
  - Proper error handling and logging
  - API version pinning (2024-06-20)

- **AJAX Functionality**
  - Connection test endpoint with nonce verification
  - Clear logs endpoint

- **Assets**
  - Admin CSS with toggle switches, badges, and responsive design
  - Admin JavaScript with AJAX helpers and UI interactions

- **Documentation**
  - CLAUDE.md development guidelines
  - readme.txt with installation instructions
  - CHANGELOG.md version tracking

- **Security**
  - Nonce verification on all AJAX requests
  - Capability checks (manage_woocommerce)
  - Input sanitization and output escaping
  - Direct access prevention on all PHP files

### Notes
- Phase 1 complete - Foundation & Stripe Integration
- Not ready for production use
- Requires WooCommerce 8.0+, WordPress 6.4+, PHP 8.0+

---

## Version History Summary

| Version | Date | Phase | Description |
|---------|------|-------|-------------|
| 1.2.1 | 2025-12-16 | 5 | Bug Fix: Test Connection with Dual API Keys |
| 1.2.0 | 2025-12-16 | 5 | Dual API Keys, Test Mode in API Settings, OAuth Removed |
| 1.1.1 | 2025-12-16 | 5 | Settings Reset & Persistence Bug Fixes |
| 1.1.0 | 2025-12-16 | 5 | Stripe OAuth 2.0 Integration (later removed) |
| 1.0.1 | 2025-12-16 | 5 | WPML/Polylang Multilingual Support |
| 1.0.0 | 2025-12-15 | 5 | Production Release |
| 0.5.7 | 2025-12-15 | 4+ | Data Deletion Email Template |
| 0.5.6 | 2025-12-15 | 4+ | Manual Data Deletion UI |
| 0.5.5 | 2025-12-15 | 4+ | Privacy Notices in Emails |
| 0.5.4 | 2025-12-15 | 4+ | Improved Email Templates |
| 0.5.3 | 2025-12-15 | 4+ | Expanded Webhook Error Handling |
| 0.5.2 | 2025-12-15 | 4+ | Dashboard Widget Re-enabled |
| 0.5.1 | 2025-12-15 | 4+ | Order Status Flow Improvements |
| 0.5.0 | 2025-12-15 | 4+ | Track Link Clicks |
| 0.4.2 | 2025-12-15 | 4+ | Phone Number Validation Fix |
| 0.4.1 | 2025-12-15 | 4+ | Stripe API Parameter Fix |
| 0.4.0 | 2025-12-15 | 4+ | Customer Verification Status Section |
| 0.3.9 | 2025-12-15 | 4+ | Configurable Verification Options |
| 0.3.8 | 2025-12-15 | 4+ | Email Sending Fix |
| 0.3.7 | 2025-12-15 | 4+ | Email Templates Tab Redesign |
| 0.3.6 | 2025-12-15 | 4+ | Setup Help Accordions |
| 0.3.5 | 2025-12-15 | 4+ | Removed debug logging |
| 0.3.4 | 2025-12-15 | 4+ | Critical Bug Fix: WSOD root cause |
| 0.3.3 | 2025-12-15 | 4+ | Critical Bug Fix: WSOD on activation |
| 0.3.2 | 2025-12-15 | 4+ | Bug Fixes & Feature Enhancements |
| 0.3.1 | 2025-12-15 | 4 | Dashboard, Stats & Polish |
| 0.2.1 | 2025-12-15 | 3 | Order Integration & Verification Workflow |
| 0.1.1 | 2025-12-15 | 2 | Email System & Templates |
| 0.0.1 | 2025-12-15 | 1 | Foundation & Stripe Integration |

---

## Versioning Guide

- **0.0.x** - Phase 1: Foundation & Stripe Integration
- **0.1.x** - Phase 2: Email System & Templates
- **0.2.x** - Phase 3: Order Integration & Verification Workflow
- **0.3.x** - Phase 4: Dashboard, Stats & Polish
- **1.0.0** - First production-ready release
