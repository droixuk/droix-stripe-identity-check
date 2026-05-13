=== Stripe ID Check ===
Contributors: droix
Tags: woocommerce, stripe, identity verification, id check, kyc
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.10.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add Stripe Identity verification to WooCommerce for fraud-review orders, address mismatches, Radar signals, and high-value dispatches.

== Description ==

Stripe ID Check integrates Stripe Identity verification directly into WooCommerce so store teams can review risky orders before dispatch without building a custom KYC workflow.

DROIX built this plugin after dealing with scam attempts against high-value handheld gaming device orders. The goal is practical: keep genuine customers moving, put risky orders on hold, and give operators a clear verification trail before releasing stock.

For the core flow, merchants only need their own Stripe account with Identity enabled. There are no bundled DROIX credentials, no separate KYC vendor account, and no custom verification app to manage. Optional Linnworks and Slack integrations only need credentials if you choose to enable them.

The plugin is free and released under GPLv2 or later. Stripe Identity usage is billed separately by Stripe to your own Stripe account.

= Features =

* **WooCommerce Order Checks** - Request ID verification manually from order admin or with bulk actions
* **Stripe Identity Flow** - Send customers through Stripe's hosted verification experience
* **Address-Mismatch Automation** - Require verification when billing and shipping addresses are genuinely different
* **Fraud Signal Triggers** - Trigger checks from Stripe Radar risk levels, risk scores, and Early Fraud Warnings
* **Amount Thresholds** - Optionally request verification for orders above a configured value
* **Customer Communication** - Customizable request, passed, failed, CRM, and data-redaction emails
* **Real-Time Webhooks** - Update WooCommerce status when Stripe reports the verification result
* **Admin Dashboard** - Track requests, link clicks, pass rate, pending checks, failed checks, and verification history
* **Linnworks Integration** - Lock orders while verification is pending and unlock them when verification passes
* **Slack Notifications** - Send optional internal alerts for verification events
* **Data Redaction Tools** - Request Stripe Identity data redaction after your configured retention period
* **WooCommerce HPOS Compatible** - Works with High-Performance Order Storage
* **WPML/Polylang Compatible** - Translate customer-facing messages and email templates

= Requirements =

* WordPress 6.4 or higher
* WooCommerce 8.0 or higher
* PHP 8.0 or higher
* Stripe account with Identity enabled
* SSL certificate (HTTPS required for webhooks)
* Optional: Linnworks account for order lock/unlock automation

= Cost =

Stripe ID Check does not add a plugin fee. Stripe Identity usage is billed by Stripe to your own Stripe account.

Stripe pricing is localized and may change, so check the current Stripe Identity pricing page for your account region before rollout. Public examples as of 2026-05-13:

* UK: Stripe lists ID document + selfie verification at GBP 1.25 per completed verification, with the first 50 verifications free.
* US: Stripe lists ID document + selfie verification at US $1.50 per completed verification, with the first 50 verifications free.
* ID number lookup is charged separately by Stripe if you enable that verification method.

Current Stripe pricing:

* https://stripe.com/gb/identity
* https://stripe.com/us/identity

= How It Works =

1. Install and configure the plugin with your own Stripe API keys
2. Choose which orders should require identity verification
3. The plugin places matching orders on hold and sends the customer a secure Stripe Identity link
4. Customer completes verification via Stripe's hosted verification page
5. Stripe webhooks notify your store of the verification result
6. WooCommerce order status, emails, logs, and optional Linnworks or Slack actions update automatically

= Stripe Identity Verification =

Stripe Identity uses machine learning to verify government-issued IDs and match them with selfies. Supported document types:

* Passport
* Driver's License
* National ID Card

= Optional Integrations =

* **Stripe Radar** - Use payment risk signals and Early Fraud Warnings to trigger checks.
* **Linnworks** - Lock orders while verification is pending and unlock them after a pass.
* **Slack** - Notify internal teams when checks are triggered, passed, failed, or linked to Early Fraud Warnings.
* **Data Retention** - Schedule Stripe Identity data redaction after your configured retention period.

== Installation ==

1. Upload the `droix-stripe-id-check` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to DROIX Plugins → Stripe ID Check to configure settings
4. Enter your own Stripe API keys (publishable key, secret key)
5. Configure your webhook in the Stripe Dashboard using the provided webhook URL
6. Subscribe to these events:
   - `identity.verification_session.verified` (verification passed)
   - `identity.verification_session.requires_input` (verification failed)
   - `identity.verification_session.canceled` (session cancelled)
   - `identity.verification_session.redacted` (data deleted)
7. Enter the webhook signing secret in plugin settings
8. Test the connection using the "Test Connection" button

= Stripe Dashboard Setup =

1. Log in to your Stripe Dashboard
2. Go to Developers → Webhooks
3. Click "Add endpoint"
4. Enter the webhook URL shown in the plugin settings
5. Select events: `identity.verification_session.verified`, `identity.verification_session.requires_input`, `identity.verification_session.canceled`, `identity.verification_session.redacted`
6. Copy the signing secret and enter it in plugin settings

== Frequently Asked Questions ==

= Do I need a Stripe account? =

Yes. You need your own Stripe account with Identity enabled. Stripe Identity may require additional verification of your business before it can be enabled.

= Do I need a separate KYC provider account? =

No. The core verification flow uses Stripe Identity through your own Stripe account.

= How much does it cost? =

The plugin is free. Stripe bills Identity usage separately. Stripe pricing is region-specific and may change. As public examples on 2026-05-13, Stripe listed ID document + selfie verification at GBP 1.25 in the UK and US $1.50 in the US, charged when the customer completes verification, with the first 50 verifications free. Check Stripe's current Identity pricing for your account region before rollout.

= What documents are supported? =

Stripe Identity supports passports, driver's licenses, and national ID cards from most countries.

= Is this GDPR compliant? =

The plugin includes functionality to request redaction of Stripe Identity verification data when it's no longer needed. You remain responsible for configuring retention, notices, access controls, and any other compliance requirements for your business.

= Does this include DROIX credentials or internal data? =

No. This public plugin does not include DROIX API keys, Stripe credentials, Linnworks credentials, Slack webhooks, customer data, or internal business data. Each merchant must configure their own credentials.

= Can I use this commercially? =

Yes. The plugin is licensed under GPLv2 or later. If it helps your store, optional support through DROIX.store is appreciated but not required.

= Does this work with WooCommerce HPOS? =

Yes, the plugin is fully compatible with WooCommerce High-Performance Order Storage.

= What happens if verification fails? =

When verification fails, the order remains on hold and you receive a notification. You can then decide whether to request a new verification or take other action.

== Screenshots ==

1. Verification dashboard showing request volume, link clicks, success rate, pending checks, failed checks, and verification history.
2. General settings for verification requirements, address-mismatch automation, checkout notices, and customer messaging.
3. API settings for live/test Stripe keys, webhook URL, connection testing, and optional Radar credentials.
4. Email template editor with visual editing, placeholders, test sends, and reset-to-default controls.
5. Data retention settings for automatic Stripe Identity data redaction and compliance reporting.
6. Linnworks integration settings for order locking, unlocking, credential testing, and external REST API access.
7. Slack notification settings for internal ID check event alerts.
8. Slack message feed showing triggered and passed ID check alerts with direct order and Stripe links.
9. Debug tools with system information and recent plugin logs for troubleshooting.

== Changelog ==

= 1.6.0 =
* **New Feature: Auto-Verification for Different Shipping Addresses**
  - Automatically trigger ID verification when customers select "Ship to a different address"
  - Order is placed on hold and verification request sent automatically
  - No admin action required - fully automated workflow
* **New: Checkout Warning Notice**
  - Warning message appears when customer checks "Ship to different address"
  - Customizable message in General Settings → Auto-Verification
  - WPML/Polylang translatable
  - Lightweight JavaScript with zero checkout performance impact
* **New: Thank You Page Confirmation**
  - Customers see styled notice explaining verification requirement
  - Customizable message in settings
  - WPML/Polylang translatable
* **New: Top-Level Admin Menu**
  - "ID Check" now appears as top-level WordPress admin menu
  - Uses ID card icon (dashicons-id-alt)
  - Still accessible via DROIX Plugins menu
* **Enhanced: CRM Notification for Auto-Triggered Verifications**
  - New "Auto-Triggered" status in admin emails
  - Displays reason (different shipping address)
  - Prominent "Review Order Now" button with order URL
* **Block Checkout Support**: Works with both classic and block checkout

= 1.5.0 =
* **New Feature: Linnworks Integration** - Automatically lock/unlock orders in Linnworks based on ID verification status
  - Auto-lock orders when verification is requested (configurable)
  - Auto-unlock orders when verification passes (configurable)
  - Manual lock/unlock from WooCommerce admin with order notes
* **New: External REST API** - Trigger lock/unlock from external systems
  - `POST /wp-json/dsic/v1/linnworks/lock` - Lock an order by WC order ID
  - `POST /wp-json/dsic/v1/linnworks/unlock` - Unlock an order by WC order ID
  - `GET /wp-json/dsic/v1/linnworks/status` - Check integration status
  - `GET /wp-json/dsic/v1/linnworks/order/{id}` - Get order verification status
  - API key authentication for external access
* **New: Linnworks Settings Tab** - Configure Linnworks credentials and automation
  - Application ID, Secret, and Installation Token fields
  - Connection test button
  - Auto-lock and auto-unlock toggles
  - API key generation for external access
  - Full API documentation with example requests/responses
* **New: Linnworks Operation Log** - Track all Linnworks API operations
  - Statistics overview (today, week, month)
  - Recent failures alert panel
  - Filterable log table with pagination
  - Response time tracking
  - Request/response data inspection modal
* **Database**: New `{prefix}dsic_linnworks_log` table for operation logging
* Retry logic (2 attempts) for Linnworks API operations
* Detailed order notes for lock/unlock operations

= 1.4.1 =
* **WPML String Translation Categorization** - Strings now organized into separate domains for easy filtering
  - **"Stripe ID Check - Emails"**: All customer email templates (Subject, Heading, Body)
  - **"Stripe ID Check - Customer"**: Frontend UI strings (verification page, progress steps, buttons)
  - **"Stripe ID Check - Admin"**: Backend-only labels (optional to translate)
* Filter by domain in WPML String Translation to focus on customer-facing content only
* Simplified frontend string keys (removed "Frontend: " prefix)
* Updated verification status template version to 1.4.1

= 1.4.0 =
* **BREAKING: Email Template Architecture Rewrite** - Emails now use database templates from settings
  - Email Templates in plugin settings now control ACTUAL email content (not just test emails)
  - All customer emails (Request, Passed, Failed, Data Redaction) use customizable WYSIWYG templates
  - Test emails now match exactly what customers receive
  - Removed dependency on PHP template files for email content
* **New Email Helper Class** - `DSIC_Email_Helper` provides centralized email building
  - Template retrieval from database with WPML translation support
  - Shortcode processing with order context
  - HTML-to-plain-text conversion for multipart emails
  - Default template content for fresh installations
* **New Shortcodes for Email Templates**:
  - `[dsic_verification_url]` - Raw verification URL (not as button)
  - `[dsic_failure_reason]` - Failure reason for failed verification emails
  - `[dsic_verification_result]` - Verification result status (verified/failed)
  - `[dsic_support_email]` - Support/CRM email address from settings
* **Simplified WooCommerce Email Settings** - Reduced to Enable/Disable and Email Type only
  - Subject, heading, and body are now managed in plugin settings with WYSIWYG editor
  - Full shortcode support in all email fields
* **WPML Compatibility** - All email templates remain fully translatable via WPML String Translation

= 1.3.1 =
* **UI Consolidation**: Merged Statistics tab into Dashboard tab
  - Dashboard now shows all stats (Success Rate circle, Period breakdown, Links Clicked)
  - Removed redundant Statistics tab
  - Cleaner navigation with fewer tabs
* **WPML Frontend Strings**: All customer-facing verification page strings now register with WPML String Translation
  - 18 frontend strings available for translation in WPML String Translation
  - Includes progress steps, status messages, verification instructions, button text
  - Strings appear under "Stripe ID Check" context in String Translation
* **Dashboard Widget**: Updated link to point to Dashboard tab instead of removed Statistics tab
* **Code Cleanup**: Removed unused Export to CSV and Refresh Statistics functionality

= 1.3.0 =
* **New Feature**: Email Verification - Verify customer email ownership via code
  - New "Verify Email Ownership" toggle in General Settings → Verification Options
  - Sends verification code to customer's email before ID verification
  - Enabled by default for enhanced security
* **New Feature**: Phone Verification - Verify customer phone number via SMS
  - New "Verify Phone Number" toggle in General Settings → Verification Options
  - Sends SMS verification code to customer's phone
  - Disabled by default (requires customer phone in E.164 format)
* **UX Improvement**: Verification Options moved from API Settings to General Settings tab
* **Improved**: Test Email feedback now shows success/failure with detailed error messages
  - Clear confirmation when test email is sent successfully
  - Detailed error messages if email fails (e.g., SMTP configuration issues)
  - Validates email address and template before attempting to send
* Enhanced verification logging to include email/phone verification status

= 1.2.1 =
* **Bug Fix**: Test Connection button now works correctly with dual API keys
  - JavaScript now reads correct input field based on Test Mode toggle
  - PHP fallback now uses correct option names (`dsic_live_secret_key` / `dsic_test_secret_key`)
  - Fixed "API secret key is required" error when keys were saved but connection test failed

= 1.2.0 =
* **Dual API Key Support** - Separate Test and Live API key sets
  - Live API Keys section (Publishable Key, Secret Key, Webhook Secret)
  - Test API Keys section (Publishable Key, Secret Key, Webhook Secret)
  - Automatic key selection based on Test Mode toggle
  - Visual mode indicator badges (Production/Sandbox)
* **Test Mode Relocated** - Moved from General tab to API Settings tab
  - Toggle between Test Mode and Live Mode in one place
  - Mode indicator badge shows current active environment
* **Removed OAuth 2.0** - Simplified authentication to use standard API keys
  - Removed Stripe Apps OAuth integration (overly complex for typical use)
  - Clean, straightforward API key configuration

= 1.1.2 =
* **Bug Fix**: Fixed API Settings tab not loading (empty page)
  - OAuth class methods were being called as static but are instance methods
  - Now properly uses singleton instance for OAuth method calls

= 1.1.1 =
* **Bug Fix**: Fixed settings reset when saving different tabs
  - Settings on one tab no longer reset when saving another tab
  - Tab-aware form handling prevents cross-tab interference
* **Bug Fix**: Fixed default values on fresh install
  - Enable Plugin now defaults to Active
  - Email templates now default to Enabled
* **Bug Fix**: Fixed settings persistence on plugin update
  - Consistent use of '1'/'0' strings for checkbox values
  - Proper use of add_option vs update_option preserves user settings

= 1.1.0 =
* **Stripe OAuth 2.0 Integration**:
  - One-click Stripe account connection via OAuth 2.0
  - Secure token storage with AES-256-CBC encryption
  - Automatic token refresh before expiration
  - Live and Test mode connection support
  - Easy disconnect functionality
  - Fallback to manual API keys when OAuth not connected
* **UI Improvements**:
  - New "Stripe Connection" section in API Settings
  - Visual connection status display
  - OAuth configuration panel for advanced users

= 1.0.1 =
* **WPML/Polylang Compatibility**:
  - Added DSIC_WPML class for multilingual integration
  - Custom email templates registered with WPML String Translation
  - Email templates can be translated for each language
  - Order language detection for proper email translation
  - My Account verification status fully translatable
  - Compatible with both WPML and Polylang

= 1.0.0 =
* **Production Release** - First stable release
* Complete Stripe Identity verification integration for WooCommerce
* One-click verification requests from order admin
* Automated customer email notifications (request, passed, failed, data deletion)
* Real-time webhook status updates
* Dashboard widget and statistics tracking
* GDPR-compliant data management with manual deletion option
* WooCommerce HPOS compatible
* Customizable email templates with visual editor

= 0.5.7 =
* Added Data Deletion Confirmation email template:
  - New customizable email sent when verification data is deleted from Stripe
  - Supports both manual deletion and automatic 48-hour deletion
  - Full template editor with placeholders in Email Templates tab
* Fixed admin assets loading on WooCommerce > ID Check page
* Fixed email template settings not being preserved on plugin update
* Added CLAUDE.md development guide with asset loading documentation

= 0.5.6 =
* Admin UI for manual data deletion from Stripe:
  - "Delete Data from Stripe" button in order meta box for verified/failed orders
  - Data deletion status tracking (pending/completed)
  - Webhook handling for `identity.verification_session.redacted` event
* New customer email notification when data is deleted
* Auto-deletion notice: "Data auto-deletes after 48 hours"
* Added "ID Check" submenu under WooCommerce menu (below Payments)

= 0.5.5 =
* Added privacy notice to passed/failed verification emails:
  - 48-hour automatic data deletion notice
  - Option to request manual deletion via support
  - Link to Stripe's privacy KB

= 0.5.4 =
* Improved Verification Request email:
  - Added glasses tip: Remove glasses for selfie if ID shows you without glasses
  - Added 48-hour automatic data deletion notice (Stripe policy)
  - Added link to Stripe's privacy KB for transparency
* Improved Verification Failed email:
  - Changed "Try Again" to "Please do not attempt verification again"
  - Instructs customers to wait for support team to contact them
  - Explains next steps: team review → identify issue → contact with instructions

= 0.5.3 =
* Expanded error code handling for Stripe Identity webhook events
* Added support for manual review/override reasons from Stripe dashboard
* New error messages: document_invalid, document_fraudulent, selfie_manipulated, etc.
* Manual admin override in Stripe dashboard now properly updates order to on-hold

= 0.5.2 =
* Re-enabled dashboard widget for ID Verification Stats
* Widget shows: Verified/Pending/Failed counts, success rate, period stats, recent verifications

= 0.5.1 =
* Order status flow improvements:
  - Request verification now always sets order to "on-hold"
  - Passed verification changes order to "processing"
  - Failed verification keeps/sets order to "on-hold"
* Skips status change for completed, cancelled, refunded orders

= 0.5.0 =
* Track when customer clicks verification link
* Clear stats cache on link click

= 0.4.2 =
* Fixed: Phone number validation error - only sends phone to Stripe if in E.164 format (+country code)
* Prevents "is not a valid phone number" errors from local phone formats

= 0.4.1 =
* Fixed: Stripe API error "Received unknown parameter: options[document][allowed_document_types]"
* Corrected API parameter name from `allowed_document_types` to `allowed_types`

= 0.4.0 =
* Customer Verification Status Section on My Account order view page
* Visual progress tracker (Requested → In Progress → Complete)
* "Verify My Identity" button for pending verifications
* Timestamps for request and completion
* Mobile-responsive design
* After Stripe verification, customers redirect to their order page
* CSS only loads on relevant pages (no frontend bloat)
* Template can be overridden in theme

= 0.3.9 =
* Configurable Verification Options in API Settings tab
* Require Selfie Check, ID Number Check, Live Capture toggles
* Allowed Document Types selection (Driving Licence, ID Card, Passport)
* Customer email/phone included in Stripe verification

= 0.3.8 =
* Fixed: Verification request emails not sending from admin
* Added WC()->mailer() call before triggering email actions

= 0.3.7 =
* Email Templates tab redesign with better UX
* Click-to-insert placeholders in email editor
* Test email functionality per template
* Improved default email templates

= 0.3.6 =
* Added Setup Help accordions in API Settings tab
* Step-by-step guides for: Getting API Keys, Setting up Webhook, Getting Webhook Secret, Enabling Stripe Identity
* Improved onboarding experience with direct links to Stripe Dashboard

= 0.3.5 =
* Removed excessive debug logging from 0.3.4
* Plugin no longer outputs to debug.log during normal operation
* Clean production-ready code

= 0.3.4 =
* Critical Bug Fix: Fixed root cause of white screen of death
* Email class files now load when WooCommerce mailer initializes (WC_Email available)
* Deferred email class loading to woocommerce_email_classes filter callback

= 0.3.3 =
* Critical Bug Fix: Fixed white screen of death caused by email class registration
* Moved WooCommerce email filter registration inside dsic_init() to ensure classes are loaded first

= 0.3.2 =
* Bug Fixes & Feature Enhancements
* Fixed potential fatal error in HPOS detection for order meta box
* Added nonce verification to stats refresh action
* Added Email Templates tab with WYSIWYG editor for all email types
* Added Send Test Email functionality for each email template
* Added Reset to Default button for email templates
* Added Export to CSV functionality for verification statistics
* Added available shortcodes reference panel in email editor
* Improved button styling with loading spinners

= 0.3.1 =
* Phase 4: Dashboard, Stats & Polish
* Added DSIC_Stats class for verification statistics
* Added WordPress dashboard widget for quick stats overview
* Added Statistics tab to settings page with detailed reports
* Added bulk actions for orders (Request/Resend/Cancel verification)
* Added success rate visualization with circular progress
* Added period-based statistics (Today, This Week, This Month, Last 30 Days)
* Added recent verifications table with links to orders
* Added automatic stats cache clearing on status changes

= 0.2.1 =
* Phase 3: Order Integration & Verification Workflow
* Added DSIC_Order_Handler class with full order integration
* Added order actions dropdown (Request/Resend/Cancel verification)
* Added order meta box showing verification status
* Added ID Check column in orders list (HPOS compatible)
* Added DSIC_Webhook_Handler for webhook processing
* Added JIT verification endpoint via WooCommerce API
* Added REST API webhook endpoint with signature verification
* Added automatic order status updates on verification
* Added custom display pages for success/failure/processing states

= 0.1.1 =
* Phase 2: Email System & Templates
* Added 4 email classes (Request, Passed, Failed, CRM)
* Added HTML and plain text email templates
* Added DSIC_Shortcodes class with 15 shortcodes
* Customer, address, order, verification, and site shortcodes

= 0.0.1 =
* Initial development release
* Plugin foundation and structure
* Stripe API integration
* Settings page with API configuration
* Connection testing functionality
* Debug logging system

== Upgrade Notice ==

= 1.4.1 =
WPML translations now organized by domain. Filter by "Stripe ID Check - Emails" or "Stripe ID Check - Customer" in WPML String Translation to translate only customer-facing content. Previously registered strings will need re-registration - visit plugin settings once after updating.

= 1.4.0 =
Major update: Email templates in plugin settings now control actual email content. Test emails match what customers receive. New shortcodes available. Existing template customizations in settings will now be used for real emails.

= 1.3.1 =
UI consolidation: Statistics tab merged into Dashboard. WPML support added for all frontend verification strings. Visit WPML String Translation to translate customer-facing content.

= 1.3.0 =
New feature: Email and phone verification options. Verify customer email ownership and phone number during ID verification. Email verification is enabled by default.

= 1.2.1 =
Bug fix: Test Connection button now works correctly with dual API keys. Fixes "API secret key is required" error.

= 1.2.0 =
Major update: Dual API key support with separate Test and Live credentials. Test Mode moved to API Settings tab. OAuth removed in favor of simpler API key authentication. After upgrading, you may need to re-enter your API keys in the new format.

= 0.4.1 =
Bug fix: Fixed Stripe API parameter error that prevented verification sessions from being created.

= 0.4.0 =
New feature: Customer verification status section on My Account order page. Customers can now view and initiate verification directly from their order page.

= 0.3.9 =
New feature: Configurable verification options (selfie, ID number, live capture, document types).

= 0.3.8 =
Bug fix: Verification request emails now send correctly from admin order page.

= 0.3.7 =
Email templates tab redesigned with better UX and click-to-insert placeholders.

= 0.3.6 =
New feature: Setup help accordions with step-by-step Stripe configuration guides.

= 0.3.5 =
Code cleanup: Removed debug logging. Production-ready.

= 0.3.4 =
Critical bug fix: Root cause fix for white screen of death. Email classes now load at correct time.

= 0.3.3 =
Critical bug fix: Resolves white screen of death on activation. All users should upgrade.

= 0.3.2 =
Bug fixes and new features: WYSIWYG email template editor, test email functionality, CSV export.

= 0.3.1 =
Phase 4 complete. Dashboard widget, statistics, and bulk actions added.

= 0.2.1 =
Phase 3 complete. Full verification workflow now operational.

= 0.1.1 =
Phase 2 complete. Email system and templates added.

= 0.0.1 =
Initial development release. Not recommended for production use.

== Privacy Policy ==

This plugin sends customer data to Stripe for identity verification purposes. This includes:

* Customer name
* Customer email address
* Customer phone number when phone pre-fill is enabled
* Order information (for metadata)

Stripe processes this data according to their privacy policy: https://stripe.com/privacy

Government ID documents and selfies are processed directly by Stripe and are not stored by this plugin on your WordPress site.

For GDPR compliance, you can redact verification session data through the Stripe Dashboard or programmatically through the plugin.

Internal CRM emails, Slack notifications, admin order notes, and logs may include order metadata. Review your own access controls and data retention settings before enabling optional integrations.
