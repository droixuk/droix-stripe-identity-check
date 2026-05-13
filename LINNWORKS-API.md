# Linnworks API Integration Documentation

## Overview

The Stripe ID Check plugin provides comprehensive Linnworks integration for automatic order management based on verification status. This document covers both direct PHP API usage and REST API endpoints.

**Key Features:**
- Automatic order locking/unlocking based on verification status
- Manual controls via order meta box
- External API access via REST endpoints
- Retry logic with exponential backoff
- Comprehensive logging and audit trail
- WooCommerce order reference matching

---

## Configuration

### Settings Location
WooCommerce → Settings → Stripe ID Check → Linnworks Integration

### Required Credentials
1. **Application ID** - Your Linnworks application identifier
2. **Application Secret** - Your Linnworks application secret key
3. **Authorization Token** - Your Linnworks authorization token

### REST API Key
Generate an API key in the Linnworks Integration settings for external access to REST endpoints.

**API Key Format:** `dsic_[48 random hex characters]`

The key is shown once when generated and then stored as a hash. Generate a new key if you need to copy it again.

---

## Direct PHP API Usage

### Class: `DSIC_Linnworks_API`

**Location:** `api/class-dsic-linnworks-api.php`

### Initialization

```php
// Using settings from database
$api = new DSIC_Linnworks_API();

// Using custom credentials
$api = new DSIC_Linnworks_API(
    'your-application-id',
    'your-application-secret',
    'your-auth-token'
);
```

### Configuration Methods

#### `is_configured()`
Check if API credentials are configured.

```php
$api = new DSIC_Linnworks_API();
if ( $api->is_configured() ) {
    // Credentials are set
}
```

**Returns:** `bool`

#### `is_enabled()` (static)
Check if Linnworks integration is enabled globally.

```php
if ( DSIC_Linnworks_API::is_enabled() ) {
    // Integration is enabled in settings
}
```

**Returns:** `bool`

### Authentication

#### `authenticate()`
Authenticate with Linnworks and retrieve session token.

```php
$api = new DSIC_Linnworks_API();
if ( $api->authenticate() ) {
    // Successfully authenticated
    // Session token and locality are now set
} else {
    $error = $api->get_last_error();
    error_log( "Auth failed: $error" );
}
```

**Returns:** `bool`
**Side Effects:** Sets `$this->session_token` and `$this->locality`

### Order Search

#### `search_order( string $reference )`
Search for an order by WooCommerce order reference.

```php
$result = $api->search_order( 'WC-12345' );

if ( is_wp_error( $result ) ) {
    echo $result->get_error_message();
} else {
    print_r( $result );
}
```

**Parameters:**
- `$reference` (string) - WooCommerce order reference number

**Returns:** `array|WP_Error`

**Success Response Structure:**
```php
[
    'found'        => true,
    'is_open'      => true,              // false if order is processed
    'linnworks_id' => 'uuid-string',     // Linnworks order ID
    'reference'    => 'WC-12345',        // Matched reference
    'status'       => 'open',            // 'open' or 'processed'
    'raw_data'     => [ ... ]            // Full Linnworks order data
]
```

**Not Found Response:**
```php
[
    'found'        => false,
    'is_open'      => null,
    'linnworks_id' => null,
    'reference'    => 'WC-12345',
    'status'       => null,
    'raw_data'     => []
]
```

**Important Notes:**
- Automatically excludes cloned orders (SubSource: API_CLONE)
- Prefers exact reference match over general search
- Uses internal retry logic

### Order Locking

#### `lock_order( string $linnworks_order_id, bool $add_note = true, int $wc_order_id = 0 )`
Lock an order in Linnworks to prevent processing.

```php
$result = $api->lock_order( 'uuid-string', true, 12345 );

if ( is_wp_error( $result ) ) {
    echo $result->get_error_message();
} else {
    // Order locked successfully
}
```

**Parameters:**
- `$linnworks_order_id` (string) - Linnworks order UUID
- `$add_note` (bool) - Whether to add note to order (default: true)
- `$wc_order_id` (int) - WooCommerce order ID for note reference (default: 0)

**Returns:** `array|WP_Error`

**Note Content** (when `$add_note = true`):
```
[STRIPE ID CHECK] Order locked - awaiting verification
Date: 2026-01-08 12:00:00
WC Order: #12345
Customer: customer@example.com
WC URL: https://example.com/wp-admin/post.php?post=12345&action=edit
Stripe URL: [verification session URL]
Requested by: admin_username
```

#### `lock_order_by_wc_id( int $wc_order_id, string $trigger_source = 'auto' )`
High-level method to lock order using WooCommerce order ID.

```php
$result = $api->lock_order_by_wc_id( 12345, 'manual' );

if ( $result['success'] ) {
    echo "Order locked: " . $result['linnworks_id'];
} else {
    echo "Lock failed after {$result['attempts']} attempts: {$result['error']}";
}
```

**Parameters:**
- `$wc_order_id` (int) - WooCommerce order ID
- `$trigger_source` (string) - 'manual', 'auto', or 'api' (default: 'auto')

**Returns:** `array`

**Response Structure:**
```php
[
    'success'      => true,              // or false
    'wc_order_id'  => 12345,
    'linnworks_id' => 'uuid-string',     // null if failed
    'action'       => 'lock',
    'attempts'     => 1,                 // Number of retry attempts (max 3)
    'error'        => null               // Error message if failed
]
```

**Retry Logic:**
- Automatically retries up to 2 times on failure
- Exponential backoff: 2 seconds, 4 seconds
- Logs all attempts to database

### Order Unlocking

#### `unlock_order( string $linnworks_order_id, bool $add_note = true, string $reason = '', int $wc_order_id = 0 )`
Unlock an order in Linnworks to allow processing.

```php
$result = $api->unlock_order(
    'uuid-string',
    true,
    'ID verification passed',
    12345
);

if ( is_wp_error( $result ) ) {
    echo $result->get_error_message();
} else {
    // Order unlocked successfully
}
```

**Parameters:**
- `$linnworks_order_id` (string) - Linnworks order UUID
- `$add_note` (bool) - Whether to add note to order (default: true)
- `$reason` (string) - Reason for unlocking (default: '')
- `$wc_order_id` (int) - WooCommerce order ID for note reference (default: 0)

**Returns:** `array|WP_Error`

**Note Content** (when `$add_note = true`):
```
[STRIPE ID CHECK] Order unlocked - ready for processing
Reason: ID verification passed
Date: 2026-01-08 12:00:00
WC Order: #12345
Customer: customer@example.com
WC URL: https://example.com/wp-admin/post.php?post=12345&action=edit
Stripe URL: [verification session URL]
Requested by: admin_username
```

#### `unlock_order_by_wc_id( int $wc_order_id, string $trigger_source = 'auto', string $reason = '' )`
High-level method to unlock order using WooCommerce order ID.

```php
$result = $api->unlock_order_by_wc_id(
    12345,
    'manual',
    'Customer verified via phone'
);

if ( $result['success'] ) {
    echo "Order unlocked: " . $result['linnworks_id'];
} else {
    echo "Unlock failed after {$result['attempts']} attempts: {$result['error']}";
}
```

**Parameters:**
- `$wc_order_id` (int) - WooCommerce order ID
- `$trigger_source` (string) - 'manual', 'auto', or 'api' (default: 'auto')
- `$reason` (string) - Reason for unlocking (default: '')

**Returns:** `array` (same structure as `lock_order_by_wc_id`)

### Order Notes Management

#### `get_order_notes( string $linnworks_order_id )`
Retrieve all notes for an order.

```php
$notes = $api->get_order_notes( 'uuid-string' );

foreach ( $notes as $note ) {
    echo $note['Note'];
    echo $note['NoteDateTime'];
}
```

**Parameters:**
- `$linnworks_order_id` (string) - Linnworks order UUID

**Returns:** `array` - Array of note objects from Linnworks

#### `add_order_note( string $linnworks_order_id, string $note )`
Add a note to an order (preserves existing notes).

```php
$result = $api->add_order_note(
    'uuid-string',
    'Customer contacted for additional verification'
);

if ( is_wp_error( $result ) ) {
    echo $result->get_error_message();
}
```

**Parameters:**
- `$linnworks_order_id` (string) - Linnworks order UUID
- `$note` (string) - Note content to add

**Returns:** `array|WP_Error`

**Important:** This method fetches existing notes first and appends the new note to preserve note history.

### Connection Testing

#### `test_connection()`
Test API connection and credentials.

```php
$result = $api->test_connection();

if ( $result['success'] ) {
    echo "Connected to {$result['locality']} in {$result['response_time']}ms";
} else {
    echo "Connection failed: {$result['message']}";
}
```

**Returns:** `array`

**Response Structure:**
```php
[
    'success'       => true,             // or false
    'message'       => 'Connected successfully',
    'locality'      => 'eu',             // 'eu', 'us', 'aus', etc.
    'response_time' => 245               // milliseconds
]
```

### Error Handling

#### `get_last_error()`
Get the last error message.

```php
$api->lock_order( 'invalid-uuid' );
echo $api->get_last_error();
```

**Returns:** `string` - Error message or empty string

#### `get_last_response()`
Get the raw last API response for debugging.

```php
$api->search_order( 'WC-12345' );
print_r( $api->get_last_response() );
```

**Returns:** `mixed` - Raw API response body

---

## REST API Endpoints

**Base URL:** `https://your-site.com/wp-json/dsic/v1/`

**Namespace:** `dsic/v1`

### Authentication

All endpoints require API key authentication. Provide the key via the HTTP header:

```
X-DSIC-API-Key: dsic_your_48_character_hex_key_here
```

Query string and request-body API keys are intentionally not accepted because they are more likely to leak through server, proxy, and analytics logs.

### Endpoint: Lock Order

**POST** `/dsic/v1/linnworks/lock`

Lock an order in Linnworks.

**Request Parameters:**
- `order_id` (integer, required) - WooCommerce order ID

**cURL Example:**
```bash
curl -X POST https://your-site.com/wp-json/dsic/v1/linnworks/lock \
  -H "X-DSIC-API-Key: dsic_your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{"order_id": 12345}'
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Order #12345 locked successfully in Linnworks.",
  "wc_order_id": 12345,
  "linnworks_id": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
  "timestamp": "2026-01-08T12:34:56+00:00"
}
```

**Error Response (400/500):**
```json
{
  "success": false,
  "message": "Failed to lock order: Order not found in Linnworks",
  "wc_order_id": 12345,
  "timestamp": "2026-01-08T12:34:56+00:00"
}
```

### Endpoint: Unlock Order

**POST** `/dsic/v1/linnworks/unlock`

Unlock an order in Linnworks.

**Request Parameters:**
- `order_id` (integer, required) - WooCommerce order ID
- `reason` (string, optional) - Reason for unlocking

**cURL Example:**
```bash
curl -X POST https://your-site.com/wp-json/dsic/v1/linnworks/unlock \
  -H "X-DSIC-API-Key: dsic_your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "order_id": 12345,
    "reason": "ID verification completed successfully"
  }'
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Order #12345 unlocked successfully in Linnworks.",
  "wc_order_id": 12345,
  "linnworks_id": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
  "timestamp": "2026-01-08T12:34:56+00:00"
}
```

### Endpoint: Integration Status

**GET** `/dsic/v1/linnworks/status`

Check Linnworks integration status and connection health.

**cURL Example:**
```bash
curl -X GET https://your-site.com/wp-json/dsic/v1/linnworks/status \
  -H "X-DSIC-API-Key: dsic_your_api_key_here"
```

**Success Response (200):**
```json
{
  "enabled": true,
  "configured": true,
  "connected": true,
  "connection": {
    "success": true,
    "message": "Connected to Linnworks successfully.",
    "locality": "eu",
    "response_time": 234
  },
  "stats_today": {
    "total_locks": 5,
    "total_unlocks": 3,
    "failed_locks": 0,
    "failed_unlocks": 0
  },
  "timestamp": "2026-01-08T12:34:56+00:00"
}
```

**Disabled Response:**
```json
{
  "enabled": false,
  "configured": false,
  "connected": false,
  "message": "Linnworks integration is not enabled.",
  "timestamp": "2026-01-08T12:34:56+00:00"
}
```

### Endpoint: Order Status

**GET** `/dsic/v1/linnworks/order/{order_id}`

Get verification status and Linnworks history for a specific order.

**Path Parameters:**
- `order_id` (integer) - WooCommerce order ID

**cURL Example:**
```bash
curl -X GET https://your-site.com/wp-json/dsic/v1/linnworks/order/12345 \
  -H "X-DSIC-API-Key: dsic_your_api_key_here"
```

**Success Response (200):**
```json
{
  "wc_order_id": 12345,
  "verification_status": "verified",
  "verification_requested": "2026-01-08 10:00:00",
  "verification_completed": "2026-01-08 11:30:00",
  "last_linnworks_action": {
    "action": "unlock",
    "status": "success",
    "timestamp": "2026-01-08 11:31:00",
    "lw_order_id": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
    "trigger_source": "auto"
  },
  "history_count": 4,
  "timestamp": "2026-01-08T12:34:56+00:00"
}
```

**Order Not Found (404):**
```json
{
  "code": "order_not_found",
  "message": "Order not found.",
  "data": {
    "status": 404
  }
}
```

---

## Integration Patterns

### Pattern 1: Automatic Lock on Verification Request

```php
// Triggered automatically when verification is requested
add_action( 'dsic_verification_requested', function( $order_id ) {
    if ( ! DSIC_Linnworks_API::is_enabled() ) {
        return;
    }

    $api = new DSIC_Linnworks_API();
    $result = $api->lock_order_by_wc_id( $order_id, 'auto' );

    if ( $result['success'] ) {
        DSIC_Logger::info(
            "Auto-locked order #{$order_id} in Linnworks",
            $result
        );
    } else {
        DSIC_Logger::error(
            "Failed to auto-lock order #{$order_id}",
            $result
        );
    }
});
```

### Pattern 2: Automatic Unlock on Verification Pass

```php
// Triggered automatically when verification passes
add_action( 'dsic_verification_passed', function( $order_id ) {
    if ( ! DSIC_Linnworks_API::is_enabled() ) {
        return;
    }

    $api = new DSIC_Linnworks_API();
    $result = $api->unlock_order_by_wc_id(
        $order_id,
        'auto',
        'ID verification passed'
    );

    if ( $result['success'] ) {
        DSIC_Logger::info(
            "Auto-unlocked order #{$order_id} in Linnworks",
            $result
        );
    }
});
```

### Pattern 3: Manual Control via Admin

```php
// AJAX handler for manual lock/unlock buttons
add_action( 'wp_ajax_dsic_linnworks_lock', function() {
    check_ajax_referer( 'dsic_meta_box_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $order_id = absint( $_POST['order_id'] );

    $api = new DSIC_Linnworks_API();
    $result = $api->lock_order_by_wc_id( $order_id, 'manual' );

    if ( $result['success'] ) {
        wp_send_json_success( array(
            'message' => 'Order locked successfully',
            'data' => $result
        ) );
    } else {
        wp_send_json_error( array(
            'message' => $result['error'],
            'attempts' => $result['attempts']
        ) );
    }
});
```

### Pattern 4: External System Integration

```bash
#!/bin/bash
# External script to lock orders via REST API

API_KEY="dsic_your_api_key_here"
SITE_URL="https://your-site.com"

ORDER_ID=12345

# Lock the order
curl -X POST "${SITE_URL}/wp-json/dsic/v1/linnworks/lock" \
  -H "X-DSIC-API-Key: ${API_KEY}" \
  -H "Content-Type: application/json" \
  -d "{\"order_id\": ${ORDER_ID}}" \
  -w "\nHTTP Status: %{http_code}\n"

# Check order status
curl -X GET "${SITE_URL}/wp-json/dsic/v1/linnworks/order/${ORDER_ID}" \
  -H "X-DSIC-API-Key: ${API_KEY}" \
  -w "\nHTTP Status: %{http_code}\n"
```

### Pattern 5: Bulk Operation with Retry

```php
// Bulk unlock verified orders
function bulk_unlock_verified_orders( array $order_ids ) {
    $api = new DSIC_Linnworks_API();
    $results = array(
        'success' => array(),
        'failed'  => array(),
    );

    foreach ( $order_ids as $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            $results['failed'][] = array(
                'order_id' => $order_id,
                'error'    => 'Order not found'
            );
            continue;
        }

        $status = $order->get_meta( '_dsic_verification_status' );

        if ( 'verified' !== $status ) {
            $results['failed'][] = array(
                'order_id' => $order_id,
                'error'    => 'Not verified'
            );
            continue;
        }

        $result = $api->unlock_order_by_wc_id(
            $order_id,
            'manual',
            'Bulk unlock - verified orders'
        );

        if ( $result['success'] ) {
            $results['success'][] = $order_id;
        } else {
            $results['failed'][] = array(
                'order_id' => $order_id,
                'error'    => $result['error'],
                'attempts' => $result['attempts']
            );
        }

        // Rate limiting - wait 1 second between requests
        sleep( 1 );
    }

    return $results;
}
```

---

## Database Logging

All Linnworks operations are logged to the database for audit purposes.

**Table:** `{prefix}_dsic_linnworks_log`

**Log Entry Structure:**
- `id` - Auto-increment primary key
- `wc_order_id` - WooCommerce order ID
- `lw_order_id` - Linnworks order UUID
- `action` - 'lock' or 'unlock'
- `status` - 'success' or 'failed'
- `trigger_source` - 'manual', 'auto', or 'api'
- `attempts` - Number of retry attempts
- `error_message` - Error message if failed
- `created_at` - Timestamp

**Query Example:**
```php
global $wpdb;
$table = $wpdb->prefix . 'dsic_linnworks_log';

$logs = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$table}
     WHERE wc_order_id = %d
     ORDER BY created_at DESC",
    $order_id
) );
```

---

## Error Codes and Handling

### Common Error Scenarios

| Error | Cause | Solution |
|-------|-------|----------|
| "Linnworks integration not enabled" | Integration disabled in settings | Enable in WooCommerce → Settings |
| "API credentials not configured" | Missing credentials | Add Application ID, Secret, and Token |
| "Authentication failed" | Invalid credentials | Verify credentials with Linnworks |
| "Order not found in Linnworks" | Order reference doesn't exist | Check order reference matches |
| "Order already processed" | Order status is 'processed' | Cannot lock processed orders |
| "Failed to lock/unlock after 3 attempts" | API timeout or connectivity | Check Linnworks API status |
| "Invalid API key" | Wrong or expired API key | Regenerate API key in settings |

### Retry Logic

The high-level methods (`lock_order_by_wc_id` and `unlock_order_by_wc_id`) implement automatic retry:

- **Attempt 1:** Immediate
- **Attempt 2:** After 2 seconds
- **Attempt 3:** After 4 seconds (total 6 seconds)

Each attempt is logged to the database with attempt number and error message.

---

## Best Practices

1. **Always check if integration is enabled** before calling API methods
2. **Use high-level methods** (`lock_order_by_wc_id`, `unlock_order_by_wc_id`) for automatic retry
3. **Log all operations** for audit trail and debugging
4. **Handle WP_Error objects** properly when using low-level methods
5. **Rate limit bulk operations** to avoid overwhelming Linnworks API
6. **Use trigger_source parameter** to track operation origin
7. **Test connection** before running bulk operations
8. **Monitor database logs** for failed operations

---

## Support and Troubleshooting

### Enable Debug Logging

Add to `wp-config.php`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Linnworks operations will be logged to `wp-content/debug.log`.

### Check Logs

```php
// View recent Linnworks logs for an order
$logger = new DSIC_Linnworks_Logger();
$logs = $logger->get_order_logs( $order_id, 10 );

foreach ( $logs as $log ) {
    echo "{$log->created_at}: {$log->action} - {$log->status}\n";
    if ( $log->error_message ) {
        echo "  Error: {$log->error_message}\n";
    }
}
```

### Test Connection

Use the test connection feature in plugin settings or via PHP:

```php
$api = new DSIC_Linnworks_API();
$result = $api->test_connection();
print_r( $result );
```

---

**Last Updated:** Version 1.7.0
**Plugin:** Stripe ID Check for WooCommerce
**Author:** DROIX / Entertainment Gadgets LTD
