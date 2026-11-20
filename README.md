# Bojaco MU Plugin

A custom WordPress Must-Use (MU) plugin development environment featuring an automated build process to merge multiple modules into a single production-ready file.

## Features

- **Modular Development**: Write clean, separated PHP modules in the `modules/` directory.
- **Auto-Merging**: A custom PHP tool (`tools/merge.php`) that resolves `require_once` statements and flattens your code into a single file for deployment.
- **GitHub Actions Integration**: Automated releases that build and attach the merged plugin binary to GitHub tags.
- **Makefile Driven**: Simple build commands to manage the development lifecycle.

## Modules

All modules are loaded by default. Individual modules can be turned off with the `bojaco_mu_plugin_disabled_modules` filter, using the module names below. The filter has to be added before `after_setup_theme`, e.g. in another MU plugin:

```php
add_filter(
	'bojaco_mu_plugin_disabled_modules',
	function ( $modules ) {
		$modules[] = 'login-rate-limit';
		return $modules;
	}
);
```

### `user-rest-api`

Blocks the `/wp-json/wp/v2/users` REST API endpoints for users who are not logged in, to prevent user enumeration. CORS preflight (`OPTIONS`) requests are still allowed.

### `staging-force-login`

On sites with the environment type `staging`, redirects visitors who are not logged in to the login screen.

The redirect can be bypassed by adding `?bojaco_staging_bypass` to any URL. This sets the `wordpress_bojaco_staging_bypass` cookie, which keeps the bypass active for 24 hours.

### `jco-file-mods`

Allows file modifications (plugin, theme and core installs and updates) for administrators (and super admins on multisite) with an `@jco.fi` email address, even when `DISALLOW_FILE_MODS` is set.

### `login-rate-limit`

Locks out an IP address after too many failed login attempts. Covers `wp-login.php`, XML-RPC and anything else that goes through `wp_authenticate()`. During a lockout, correct credentials are rejected as well. The counter is reset after a successful login.

Defaults: 5 failed attempts within 15 minutes cause a 15 minute lockout.

| Hook | Type | Description |
| --- | --- | --- |
| `bojaco_login_rate_limit_settings` | filter | Change `max_attempts`, `window` (seconds) and `lockout` (seconds). |
| `bojaco_login_rate_limit_ip` | filter | Change the client IP. Only `REMOTE_ADDR` is used by default; use this to trust a proxy header behind a known load balancer. |
| `bojaco_login_rate_limit_lockout` | action | Fires when an IP is locked out. Receives the username and the rate limit state. |

### `disable-password-reset`

Disables password resets on sites with the environment type `staging`, `development` or `local`:

- Password reset keys can't be generated (`allow_password_reset`), which blocks the lost password form, the "Send password reset" action in the admin and the "set your password" email for new users.
- The "Lost your password?" link is hidden from the login screen.
- The lost password and reset screens on `wp-login.php` redirect to the login screen, so existing reset links can't be used either.

The `BOJACO_DISABLE_PASSWORD_RESET` constant overrides the environment check:

```php
define( 'BOJACO_DISABLE_PASSWORD_RESET', true );  // Disable password resets on any site.
define( 'BOJACO_DISABLE_PASSWORD_RESET', false ); // Keep password resets enabled, even on staging.
```

### `password-reset-protection`

Protects password resets against spam bots:

- **Proof of work:** the lost password form on `wp-login.php` contains a signed challenge that the browser solves with JavaScript before the form can be submitted (similar to ALTCHA). This takes about a second, needs no user interaction and uses no third-party services or cookies. Each solution can only be used once. Users without JavaScript can't reset their password.
- **Per-account throttle:** limits how many reset emails one account can receive. This applies to every form that calls `retrieve_password()` (e.g. WooCommerce), not only `wp-login.php`. Users who can edit the account (admins) are not throttled.

Defaults: challenges are valid for 30 minutes, and an account can receive 3 reset emails per hour.

| Hook | Type | Description |
| --- | --- | --- |
| `bojaco_password_reset_protection_settings` | filter | Change `max_number` (proof-of-work difficulty), `ttl` (challenge lifetime in seconds), `max_emails` and `window` (seconds). |

## Project Structure

```text
.
├── dist/               # Compiled/merged plugin (ignored by git)
├── modules/            # Plugin modules and features
├── tools/              # Build tools and scripts
│   └── merge.php       # PHP script to flatten dependencies
├── index.php           # Plugin entry point
├── Makefile            # Build automation
├── foonver.toml        # Release/version configuration
└── version.txt         # Current version
```

## Getting Started

### Prerequisites

- PHP 7.4 or higher
- Make

### Development

1.  Add your logic into the `modules/` directory.
2.  Include your modules in the root `index.php` using `require_once`, wrapped in a check so the module can be disabled:
    ```php
    if ( ! in_array( 'my-feature', $disabled_modules, true ) ) {
        require_once 'modules/my-feature.php';
    }
    ```
3.  Document the module in the [Modules](#modules) section of this README.

### Building for Production

To generate the single-file version of the plugin, run:

```bash
make build
```

The compiled plugin will be available at `dist/bojaco.php`. This is the file you should upload to the `wp-content/mu-plugins/` directory on your WordPress server.

## Automated Releases

This project is configured with a GitHub Action (`.github/workflows/release.yml`). When you push a new tag following the `v*` pattern (e.g., `v1.0.0`), the workflow will:

1.  Set up a PHP environment.
2.  Run the build process via `make`.
3.  Create a new GitHub Release.
4.  Attach the compiled `dist/bojaco.php` as a release asset.

## Tooling Details

### merge.php

The `tools/merge.php` script is a token-based PHP flattener. It recursively finds `require_once` calls, reads the target file, strips the opening/closing PHP tags, and injects the content into the main file. It also handles basic path resolution using `__DIR__` or `dirname(__FILE__)`.

## License

This project is licensed under the GPL-2.0-or-later License.
