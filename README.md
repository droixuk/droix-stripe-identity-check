# Stripe ID Check for WooCommerce

Stripe ID Check is a WooCommerce plugin for requesting and managing Stripe Identity verification on orders that need extra fraud review.

DROIX built this plugin after dealing with scam attempts against high-value handheld gaming device orders on our ecommerce stores. It helped us reduce risk while keeping the verification flow clear for genuine customers, so we are sharing it for other ecommerce operators who face similar problems.

## What It Does

- Request Stripe Identity checks from the WooCommerce order screen.
- Automatically request verification for different billing and shipping addresses.
- Trigger verification from Stripe Radar risk signals and Early Fraud Warnings.
- Optionally trigger verification for orders over a configured amount threshold.
- Put orders on hold while verification is pending, then move verified orders forward.
- Send customizable customer and CRM emails.
- Redact Stripe Identity verification data after a configurable retention period.
- Show admin dashboard statistics and order-level verification status.
- Support WooCommerce HPOS, WPML, and Polylang.
- Integrate with Linnworks to lock orders while checks are pending and unlock them when verification passes.
- Send optional Slack notifications for verification events.

## Requirements

- WordPress 6.4 or later
- WooCommerce 8.0 or later
- PHP 8.0 or later
- Stripe account with Identity enabled
- HTTPS for webhook delivery
- Optional: Linnworks credentials for order locking

## Setup

1. Install the plugin in `wp-content/plugins/droix-stripe-id-check`.
2. Activate it in WordPress.
3. Open **ID Check** or **DROIX Plugins -> Stripe ID Check** in wp-admin.
4. Add your own Stripe test/live API keys and webhook signing secrets.
5. Configure the Stripe webhook endpoint shown in the plugin settings.
6. Subscribe to the Stripe Identity events listed in the settings page.
7. Optional: enable Radar, amount threshold, Slack, data redaction, and Linnworks features.

## Security And Privacy

This repository does not include DROIX API keys, Stripe credentials, Linnworks credentials, Slack webhooks, customer data, or internal business data. Each merchant must configure their own credentials in WordPress.

The plugin sends customer name, email, phone when enabled, and order metadata to Stripe Identity so Stripe can perform verification. Government ID documents and selfies are handled by Stripe; they are not stored by this plugin. The plugin can request Stripe data redaction after a configurable retention period.

Admin logs and notifications may include order metadata. Review your own retention, access controls, and privacy obligations before enabling debug logging, CRM emails, Slack notifications, or external REST API access.

## License

This plugin is released under GPLv2 or later.

We are sharing it for free because fraud prevention tooling should be more accessible to ecommerce teams. If it helps your store, we would appreciate support through our marketplaces linked from [DROIX.store](https://DROIX.store).

