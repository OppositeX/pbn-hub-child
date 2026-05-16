# Licenser SDK

Generic PHP library to embed in WordPress plugins for licensing, update delivery, and feedback collection against a Licenser server.

## Files

| File | Role |
|---|---|
| `SDK.php` | Public entry point: `SDK::init($cfg)`, `SDK::is_valid()` |
| `Config.php` | Validates init args |
| `Client.php` | HTTP wrapper + state machine, single-option storage |
| `Cache.php` | 12–24h cache + update transient |
| `Cron.php` | Twice-daily background validation refresh |
| `Updater.php` | Hooks WP update transient + `plugins_api` |
| `AdminUI.php` | Settings → License page (PHP-rendered, dark mode) |
| `FeedbackModal.php` | Pre-deactivation feedback modal |

## Namespace placeholder

Every PHP file declares `namespace __LICENSER_NAMESPACE__\Licenser;`. Replace `__LICENSER_NAMESPACE__` at install time with the embedding plugin's parent namespace, e.g. `Gloo\CanvasStudio` → SDK becomes `Gloo\CanvasStudio\Licenser\SDK`.

Use `scripts/install-sdk.sh` (in the Licenser server repo) to do the replacement automatically.

## init() config

```php
SDK::init([
  // Required
  'product_slug' => 'canvas-studio',
  'plugin_file'  => __FILE__,
  'plugin_slug'  => 'canvas-studio/canvas-studio.php',
  'version'      => '1.4.2',
  'server_url'   => 'https://licenser.d3v.co.il',
  'option_key'   => 'canvas_studio_license',  // unique per plugin

  // Recommended (must be unique to avoid conflicts)
  'js_global'    => 'CanvasStudioLicenser',
  'css_class'    => 'canvas-studio-licenser',

  // Optional
  'admin_label'  => 'Canvas Studio License',
  'cache_hours'  => 12,    // 1-24
  'grace_days'   => 7,
  'feedback'     => true,
  'menu_parent'  => 'options-general.php',
  'cap'          => 'manage_options',
]);
```

## Public API

```php
SDK::is_valid();   // bool — uses cache + grace
SDK::client();     // Client instance for advanced ops:
//   ->activate($key)
//   ->deactivate($reason, $message)
//   ->refresh_validation()
//   ->update_check()
//   ->send_feedback($reason, $message)
//   ->state()         // current state array
```

## How updates work

1. SDK schedules a twicedaily cron (`Cron::run`) that hits `/validate` to keep state fresh.
2. WP triggers `pre_set_site_transient_update_plugins` → SDK calls `/update-check`.
3. If `has_update`, SDK injects a package URL into the update list.
4. WP downloads from `/download?token=…`. The Licenser server verifies the HMAC token, validates license + activation, and streams the release zip.
5. After install, `upgrader_process_complete` clears the SDK's update cache.

## Grace period

If the Licenser server is unreachable, `is_valid()` falls back to a grace period (default 7 days from last successful validation). This prevents customer sites from breaking during a Licenser outage.

## Security

- License key plaintext is stored only in the SDK's option (single key, never in plugin meta).
- SDK never sends the plaintext key over HTTP except over the configured `server_url` (HTTPS strongly recommended).
- All HMAC verification happens server-side; the SDK only handles raw URLs returned by the server.
