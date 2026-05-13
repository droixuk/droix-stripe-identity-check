# AGENTS.md - Stripe ID Check Plugin Development Guide

This file is the primary source of truth for all AI agents (Claude, Gemini, etc.) working on this project. 

**CRITICAL: Whenever an agent identifies new patterns, project details, or makes significant updates, those findings MUST be documented in this file to maintain a consistent context for future sessions. This includes keeping the "File Structure" section updated whenever new files or directories are added during feature implementation.**

---

## Project Overview

**Plugin Name:** Stripe ID Check
**Slug:** `droix-stripe-id-check`
**Text Domain:** `droix-stripe-id-check`
**Current Version:** 1.10.1
**Minimum PHP:** 8.0
**Minimum WordPress:** 6.4
**Minimum WooCommerce:** 8.0
**Author:** DROIX / Entertainment Gadgets LTD

This document provides instructions and best practices for developing the Stripe ID Check WordPress plugin.

---

## Core Architecture

### Asset Loading Architecture
**CRITICAL:** Assets must only load where required to prevent frontend/backend bloat.

| File | Loads Where | Hook Used |
|------|-------------|-----------|
| `admin/class-dsic-admin.php` | Plugin settings pages, ID Check page | `admin_enqueue_scripts` with screen check |
| `admin/class-dsic-dashboard-widget.php` | WordPress admin dashboard only | `admin_enqueue_scripts` with `index.php` check |
| `admin/class-dsic-order-handler.php` | Order edit pages | Inline scripts in meta box |
| `public/class-dsic-frontend.php` | My Account order pages only | `wp_enqueue_scripts` with endpoint check |
| `public/class-dsic-checkout.php` | Checkout page only | `wp_enqueue_scripts` with `is_checkout()` |

### Smart Checkout Verification
Triggered when shipping and billing addresses differ during checkout.
- **Logic:** Uses a similarity comparison algorithm (word-based) to identify genuinely different addresses while ignoring minor formatting differences.
- **Race Condition Handling:** Hooks into multiple gateway-specific and core filters (`woocommerce_payment_complete_order_status`, etc.) to ensure orders stay `on-hold` after payment only while `_dsic_verification_status` is still `pending`.
- **Cancellation Cleanup:** Both admin-side verification cancellation and Stripe's `identity.verification_session.canceled` webhook must clear `_dsic_auto_verification_triggered`, `_dsic_auto_verification_pending`, `_dsic_auto_verification_reason`, and the active verification session/status metadata so stale checkout enforcement does not push cancelled orders back to `on-hold`.
- **HPOS Support:** Fully compatible with WooCommerce High-Performance Order Storage.

### Linnworks Integration
Automatic order management in Linnworks based on verification status.
- **Actions:** Automatically "Locks" orders when verification is requested and "Unlocks" them when verification passes.
- **Manual Mode:** Admin can manually lock/unlock orders via the order edit meta box.
- **Audit Trail:** All actions are logged to `{prefix}_dsic_linnworks_log`.
- **References:** See `LINNWORKS-API.md` for full documentation.

### Compliance & Data Redaction
Automated GDPR compliance for sensitive ID data.
- **Auto-Redaction:** Deletes verification data from Stripe after a configurable retention period (default 30 days).
- **Scheduling:** Uses Action Scheduler for reliable background processing.
- **Reporting:** Provides a Compliance Audit log and exportable reports in the admin.

### REST API
Custom endpoints under `wp-json/dsic/v1/`.
- **Linnworks Endpoints:** `/linnworks/lock`, `/linnworks/unlock`, `/linnworks/status`.
- **Authentication:** Custom API key auth via `X-DSIC-API-Key` header only. Query/body API keys are intentionally not accepted.

---

## File Structure

```
droix-stripe-id-check/
├── droix-stripe-id-check.php          # Main plugin file
├── uninstall.php                       # Clean uninstall handler
├── README.md                           # Public GitHub readme
├── readme.txt                          # WordPress readme
├── .gitignore                          # Public-release ignore rules
├── CHANGELOG.md                        # Version history
├── CLAUDE.md                           # Reference to AGENTS.md
├── GEMINI.md                           # Reference to AGENTS.md
├── AGENTS.md                           # This file
├── LINNWORKS-API.md                    # Linnworks integration docs
├── docs/
│   └── screenshots/                    # README UI screenshots
│       ├── dashboard.png
│       ├── general-settings.png
│       ├── api-settings.png
│       ├── email-templates.png
│       ├── data-retention.png
│       ├── linnworks-integration.png
│       ├── slack-notifications.png
│       ├── slack-message.png
│       └── debug-tools.png
├── assets/
│   ├── css/
│   │   ├── admin.css                   # Admin styles
│   │   ├── dashboard-widget.css        # Widget styles
│   │   ├── frontend.css                # Frontend styles
│   │   └── checkout.css                # Checkout specific styles
│   └── js/
│       ├── admin.js                    # Admin scripts
│       └── checkout.js                 # Checkout address monitoring JS
├── includes/
│   ├── class-dsic-loader.php           # Hook loader
│   ├── class-dsic-activator.php        # Activation logic
│   ├── class-dsic-deactivator.php      # Deactivation logic
│   ├── class-dsic-i18n.php             # Internationalization
│   ├── class-dsic-logger.php           # Standard debug logging
│   ├── class-dsic-shortcodes.php       # Email shortcodes
│   ├── class-dsic-stats.php            # Statistics tracking
│   ├── class-dsic-wpml.php             # WPML/Polylang integration
│   ├── class-dsic-compliance-report.php # GDPR audit and reporting
│   ├── class-dsic-auto-redaction.php   # Stripe data retention handling
│   ├── class-dsic-radar-check.php      # Stripe Radar fraud detection
│   ├── class-dsic-email-helper.php     # Email formatting utilities
│   ├── class-dsic-linnworks-integration.php # Linnworks logic bridge
│   ├── class-dsic-linnworks-logger.php # Dedicated Linnworks audit logger
│   ├── class-dsic-slack.php            # Optional Slack notifications
│   └── class-dsic-amount-threshold-check.php # Order amount threshold trigger
├── admin/
│   ├── class-dsic-admin.php            # Admin asset/screen management
│   ├── class-dsic-settings.php         # Settings registration & tabs
│   ├── class-dsic-menu.php             # Admin menu registration
│   ├── class-dsic-order-handler.php    # Order meta boxes & manual actions
│   ├── class-dsic-dashboard-widget.php # Dashboard stats widget
│   ├── class-dsic-bulk-actions.php     # Bulk verification handlers
│   └── partials/
│       ├── settings-page.php           # Settings template
│       ├── settings-api.php            # API settings tab
│       ├── settings-emails.php         # Email settings tab
│       ├── settings-stats.php          # Stats tab
│       ├── compliance-report-page.php  # GDPR audit UI
│       └── linnworks-log-page.php      # Linnworks audit UI
├── api/
│   ├── class-dsic-stripe-api.php       # Stripe API wrapper
│   ├── class-dsic-linnworks-api.php    # Linnworks API wrapper
│   ├── class-dsic-rest-api.php         # WP REST API registration
│   └── class-dsic-webhook-handler.php  # Stripe Webhook endpoint
├── public/
│   ├── class-dsic-frontend.php         # Customer account verification UI
│   └── class-dsic-checkout.php         # Address mismatch detection logic
├── emails/
│   ├── class-dsic-email-verification-request.php
│   ├── class-dsic-email-verification-passed.php
│   ├── class-dsic-email-verification-failed.php
│   ├── class-dsic-email-crm-notification.php
│   └── class-dsic-email-data-redaction.php
├── templates/
│   ├── myaccount/
│   │   └── verification-status.php     # Customer order status template
│   └── emails/
│       ├── verification-request.php
│       ├── verification-passed.php
│       ├── verification-failed.php
│       ├── crm-notification.php
│       ├── data-redaction.php
│       └── plain/
│           └── [plain text versions]
└── languages/
    └── droix-stripe-id-check.pot       # Translation template
```

---

## Coding Standards

### PHP Standards
1. **Follow WordPress Coding Standards** (WPCS)
2. **Class naming:** `DSIC_Class_Name` (prefix all classes with DSIC_)
3. **Function naming:** `dsic_function_name` (prefix all global functions)
4. **Hook naming:** `dsic_hook_name` (prefix all custom hooks)
5. **Option naming:** `dsic_option_name` (prefix all options)
6. **Meta key naming:** `_dsic_meta_key` (underscore prefix for hidden meta)

### Order Meta Keys
```php
// Verification meta
const META_VERIFICATION_TOKEN        = '_dsic_verification_token';
const META_VERIFICATION_SESSION      = '_dsic_verification_session_id';
const META_VERIFICATION_STATUS       = '_dsic_verification_status';
const META_VERIFICATION_REQUESTED    = '_dsic_verification_requested';
const META_VERIFICATION_LINK_CLICKED = '_dsic_link_clicked';
const META_VERIFICATION_COMPLETED    = '_dsic_verification_completed';
const META_VERIFICATION_ERROR        = '_dsic_verification_error_msg';
const META_VERIFICATION_ATTEMPTS     = '_dsic_verification_attempts';

// Checkout meta
const META_AUTO_VERIFICATION_PENDING   = '_dsic_auto_verification_pending';
const META_AUTO_VERIFICATION_TRIGGERED = '_dsic_auto_verification_triggered';

// Radar meta
const META_RADAR_CHECKED                     = '_dsic_radar_checked';              // timestamp, set after Radar score is fetched
const META_RADAR_RISK_LEVEL                  = '_dsic_radar_risk_level';
const META_RADAR_RISK_SCORE                  = '_dsic_radar_risk_score';
const META_RADAR_RETRY_ATTEMPTS              = '_dsic_radar_retry_attempts';
const META_RADAR_EARLY_WARNING               = '_dsic_radar_early_warning';
const META_RADAR_EARLY_WARNING_TYPE          = '_dsic_radar_early_warning_type';
const META_RADAR_VERIFICATION_TRIGGERED      = '_dsic_radar_verification_triggered'; // timestamp, only set by Radar (not address-mismatch); used to show Radar-specific thank-you message
const META_EFW_TRIGGERED                     = '_dsic_efw_triggered';                // timestamp, set by handle_early_fraud_warning(); distinguishes EFW from checkout-time auto-verify so Linnworks lock is NOT skipped

// Amount threshold meta (v1.10.0+)
const META_AMOUNT_THRESHOLD_TRIGGERED        = '_dsic_amount_threshold_triggered';   // timestamp, set by DSIC_Amount_Threshold_Check; used to show threshold-specific thank-you notice and guard checkout notice

// Redaction meta
const META_DATA_REDACTION_STATUS     = '_dsic_data_redaction_status';
const META_DATA_REDACTION_REQUESTED  = '_dsic_data_redaction_requested';
```

---

## Security Requirements

### ALWAYS Implement
1. **Nonce verification** on all form submissions and AJAX requests.
2. **Capability checks** before any action (`manage_woocommerce`).
3. **Input sanitization** - ALWAYS sanitize user input.
4. **Output escaping** - ALWAYS escape output.
5. **SQL preparation** for any database queries.
6. **API Key Authentication** for REST endpoints.
7. **Secret hygiene for public releases** - never render saved API keys, webhook secrets, Slack URLs, or tokens back into HTML. Show placeholders/masked state only and preserve saved values when replacement fields are blank.
8. **REST API key storage** - store external REST API keys as hashes. Only reveal a generated key once and require `X-DSIC-API-Key`; do not accept query/body API keys.
9. **Log redaction** - redact keys, webhook URLs, tokens, and customer email addresses before writing logs.

### Public Repository Preparation
- Prefer publishing a fresh clean repository snapshot rather than exposing private commit history.
- Do not include local agent/tooling files such as `.claude/`, private notes, export archives, logs, or deployment ZIPs.
- `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, `CHANGELOG.md`, and `LINNWORKS-API.md` are development docs and are excluded from deployment ZIPs; review them before including them in any public source release.

---

## WooCommerce Integration

### HPOS Compatibility (REQUIRED)
Always use WooCommerce CRUD methods:
```php
// CORRECT
$order->get_meta('_dsic_verification_status');
$order->update_meta_data('_dsic_verification_status', 'pending');
$order->save();

// INCORRECT - DO NOT USE
get_post_meta($order_id, '_dsic_verification_status', true);
```

---

## WPML/Polylang Integration
The plugin is fully compatible with WPML and Polylang.
- **String Registration:** Email templates and frontend messages are registered with WPML String Translation.
- **Language Detection:** Detects order language from `wpml_language` meta.
- **Context:** Always use the "Stripe ID Check" context for string translations.

---

## Semantic Versioning

This plugin follows **Semantic Versioning** (SemVer): `MAJOR.MINOR.PATCH`

| Type | When to Increment | Example |
|------|-------------------|---------|
| **MAJOR** | Breaking changes, incompatible API changes | 1.0.0 → 2.0.0 |
| **MINOR** | New features, backwards-compatible | 1.7.0 → 1.8.0 |
| **PATCH** | Bug fixes, backwards-compatible | 1.7.0 → 1.7.1 |

### Version Update Locations
When releasing a new version, update ALL of these:
1. `droix-stripe-id-check.php` - Plugin header `Version:` and `DSIC_VERSION` constant
2. `readme.txt` - `Stable tag:` header
3. `AGENTS.md` - `Current Version:` in Project Overview
4. `CHANGELOG.md` - Add new version entry

---

## Deployment Packaging

When creating deployment ZIP packages, use the plugin-deployer agent with these requirements:

### ZIP Naming Convention
**Format:** `droix-stripe-id-check-{version}.zip`
**Example:** `droix-stripe-id-check-1.7.1.zip`

The version MUST be extracted from the main plugin file header (`droix-stripe-id-check.php`).

### Files to Exclude
- `.git/` and all git files (`.gitignore`, `.gitattributes`)
- Development docs (`CLAUDE.md`, `GEMINI.md`, `AGENTS.md`, `CHANGELOG.md`, `LINNWORKS-API.md`)
- IDE files (`.vscode/`, `.idea/`, `*.swp`, `.DS_Store`)
- Build dependencies (`node_modules/`, `vendor/`)
- Test files and logs

### ZIP Structure
```
droix-stripe-id-check-1.7.1.zip
└── droix-stripe-id-check/
    ├── droix-stripe-id-check.php
    ├── readme.txt
    ├── uninstall.php
    └── [all production files]
```

---

## Maintenance & Instruction Updates
**This file (`AGENTS.md`) is the primary source for agent instructions.**
1. **Continuous Maintenance:** All findings, architectural updates, and project patterns must be maintained in this file.
2. **Agent Responsibility:** Whenever an agent (Claude, Gemini, etc.) works on the project, it should check this file for guidance and update it if new relevant technical information is discovered.
3. **Default Reference:** `CLAUDE.md` and `GEMINI.md` are configured to point to this file.

*Last Updated: 2026-05-13 (Ver 1.10.1 - Added public README screenshot assets including Slack message feed and documented docs/screenshots structure)*
