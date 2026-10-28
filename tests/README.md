# Automated tests

The suite runs on the official WordPress test library (`wp-phpunit/wp-phpunit`)
with a real MySQL database, so the export retrievers are exercised against
`WP_User_Query`, `WP_Query` and the WooCommerce data stores exactly as in
production. WooCommerce is optional: when it is not installed the
WooCommerce-dependent tests are skipped.

## One-time setup

1. Create a dedicated database. **It is dropped and recreated on every run.**

   ```sql
   CREATE DATABASE newsman_wp_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'newsman_test'@'localhost' IDENTIFIED BY 'newsman_test';
   GRANT ALL PRIVILEGES ON newsman_wp_test.* TO 'newsman_test'@'localhost';
   ```

2. Install the dev dependencies. This also downloads WordPress core into
   `tests/wordpress/` (git-ignored):

   ```bash
   composer install
   ```

3. Optional: install WooCommerce into `tests/tmp/wp-content/plugins/`
   (git-ignored). Without it the WooCommerce tests are skipped.

   ```bash
   tests/bin/install-woocommerce.sh            # latest stable from wordpress.org
   tests/bin/install-woocommerce.sh 11.2.1     # a specific version
   WC_SOURCE_DIR=/path/to/woocommerce tests/bin/install-woocommerce.sh   # copy a local checkout
   ```

## Running

```bash
vendor/bin/phpunit                                   # whole suite
vendor/bin/phpunit --filter PayloadParserTest        # one class
NEWSMAN_TESTS_SKIP_WOOCOMMERCE=1 vendor/bin/phpunit  # WordPress-only run
vendor/bin/phpcs                                     # coding standards (phpcbf fixes what it can)
composer lint                                        # php -l on every file
```

Database settings default to `newsman_wp_test` / `newsman_test` / `newsman_test`
on `localhost` and can be overridden with the environment variables
`WP_TESTS_DB_NAME`, `WP_TESTS_DB_USER`, `WP_TESTS_DB_PASSWORD` and
`WP_TESTS_DB_HOST` (see `tests/wp-tests-config.php`).

## Layout

| Path | Purpose |
|------|---------|
| `tests/bootstrap.php` | Loads the WordPress test library, WooCommerce (if present) and the plugin |
| `tests/wp-tests-config.php` | WordPress test configuration, values from the environment |
| `tests/bin/install-woocommerce.sh` | Downloads or copies WooCommerce into the test wp-content |
| `tests/phpunit/NewsmanTestCase.php` | Base class: API gate helpers, user factory, pagination walker |
| `tests/phpunit/*Test.php` | The tests |
| `tests/js/purchase-guard-harness.js` | Runs the purchase script against a fake Newsman tracker (used by `PurchaseScriptTest`) |
| `tests/wordpress/` | WordPress core installed by Composer (git-ignored) |
| `tests/tmp/` | Test wp-content directory (git-ignored) |

## Coverage

- `PayloadParserTest` - API v1 request parsing, error codes, filter flattening, method map and its filters
- `AuthenticatorTest` - API key, authenticate token and alternate header key acceptance and rejection
- `ListParametersTest` - sort, order, pagination and filter translation of `AbstractRetriever`
- `UsersExportTest` - users and WordPress subscribers export against the database: fields, default order, complete pagination, sorts, filters
- `ProcessorTest` - authentication gate, retriever dispatch, API v1 round trip, `Config::is_enabled_with_api()`
- `WooCommerceExportTest` - orders, customers and product feed exports (skipped without WooCommerce)
- `IdentityScriptTest` - billing and user names stay data in the inline identify scripts
- `TrackCartScriptTest` - cart tracking script and its cart cookie gate
- `PurchaseScriptTest` - order received purchase script: printed only to a visitor WooCommerce shows the order to, and run in Node.js against a fake tracker so the purchase is neither lost nor sent again on a reload (the Node.js part is skipped without `node`; set `NODE_BINARY` for another path)
- `AdminSecurityTest` - nonce on every POST to the settings pages, OAuth forms need `manage_options`
- `OauthTest` - the error Newsman redirects back with (`access_denied`, `missing_lists`) is shown on the OAuth page
- `SubscribeFormBlockTest` - subscribe form and embedded form blocks: registration, rendered fields, consent text filtering, signed settings, the submit handler (validation, honeypot, rate limit, Sync or block list and segment, double opt-in `initSubscribe` with the confirmation form ID through a faked Newsman API, API failure) and the result after a submit without JavaScript
- `SubscribeHelperTest` - shared subscribe helper against a faked Newsman API: newsletter subscriptions subscribe again unsubscribed and pending emails (`saveSubscribe` / `initSubscribe`, then `updateProps`), other forms only refresh them, confirmed subscribers and new emails as before, failure handling and the `newsman_subscribe_newsletter` filter
- `ConfirmationFormIdTest` - double opt-in confirmation email form ID for every subscribe path against a faked Newsman API: the ID set on the form wins, an empty ID means the Settings value for newsletter forms and none for other forms (Gravity Forms, WPForms, Contact Form 7, Elementor Form widget, subscribe form block), and My Account sends the Settings ID and the Sync segment like the checkout

## Continuous integration

`.github/workflows/ci.yml` runs on every push and pull request:

- `php -l` on PHP 7.4, 8.0, 8.1, 8.2, 8.3 and 8.4
- `phpcs` with the WordPress standard
- PHPUnit on PHP 7.4 + WooCommerce, PHP 8.4 + WooCommerce and PHP 8.4 WordPress-only, against MySQL 8
