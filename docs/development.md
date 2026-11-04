<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Development and testing

Field Studio is a PHP module for Akaunting. It provides its own metadata storage, JSON API routes, and Blade views integrated with Akaunting's native forms without rebuilding the host application.

## Requirements

- Akaunting 3.2.4, Laravel 10.x, PHP 8.3, and MySQL 8.x.
- A **disposable** installation containing synthetic companies and financial records.
- Node.js, Playwright, and Chromium or Google Chrome for browser tests.
- An administrator account and optionally a read-only invoice account for permission checks.

Install the module at `modules/FieldStudio` and run:

~~~sh
php artisan module:migrate field-studio --force
php artisan module:install field-studio 1 en-GB
php artisan optimize:clear
php artisan module:list
php artisan route:list --path=field-studio
~~~

The definition screen uses `/{company_id}/field-studio/new`. Invoice forms use Akaunting's `category_start` Blade stack and native Vue form data binding.

## End-to-end tests

The test runner refuses to make changes to remote or production hosts. Seed a disposable database with synthetic invoices, bills, contacts, items, document lines, and transactions.

| Variable | Example | Purpose |
| --- | --- | --- |
| FS_BASE_URL | http://127.0.0.1:18086 | Local-only endpoint |
| FS_ADMIN_EMAIL | admin@example.invalid | Synthetic administrator |
| FS_ADMIN_PASSWORD_FILE | /tmp/test-admin-password | Protected local file; do not commit |
| FS_PLAYWRIGHT_PATH | /opt/test/node_modules/playwright | Installed Playwright library |
| FS_CHROME_PATH | /usr/bin/google-chrome | Chromium/Chrome executable |
| FS_TEST_COMPANY_ID | 1 | Synthetic company |
| FS_TEST_INVOICE_ID | 1 | Synthetic sales invoice |
| FS_TEST_BILL_ID | 3 | Synthetic bill |
| FS_TEST_CONTACT_ID | 1 | Synthetic customer |
| FS_VENDOR_ID | 3 | Synthetic vendor |
| FS_TEST_ITEM_ID | 1 | Synthetic item |
| FS_TEST_LINE_ID | 1 | Synthetic document line |
| FS_TEST_TRANSACTION_ID | 1 | Synthetic transaction |

~~~sh
export FS_BASE_URL=http://127.0.0.1:18086
export FS_PLAYWRIGHT_PATH=/opt/test/node_modules/playwright
export FS_ADMIN_PASSWORD_FILE=/tmp/test-admin-password

node tests/api-e2e.js
node tests/ui-e2e.js
php tests/i18n-check.php
~~~

For security regression tests, set `FS_VIEWER_EMAIL` and `FS_VIEWER_PASSWORD_FILE` to a synthetic user with invoice-read permission only. The API suite creates and archives test definitions at the end.

`FS_SCREENSHOT_DIR` saves non-versioned browser snapshots with synthetic data.

## Localization

English (`Resources/lang/en-GB/general.php`) is the reference language. Brazilian Portuguese (`Resources/lang/pt-BR/general.php`) is shipped with the module. The Akaunting application locale selects the translation; other languages use the host's English fallback.

**When adding text:**

1. Add an English translation key and its Portuguese counterpart.
2. Render it with `trans('field-studio::general.some_key')` in controllers and Blade templates.
3. Pass type hints to JavaScript using Blade's `@json(trans(...))`. Do not duplicate language strings in JS.
4. Keep identifiers, field keys, API JSON property names, and user-supplied values language-neutral.
5. Run `php tests/i18n-check.php` and test the interface in both locales.

Translators should preserve keys, placeholder names (such as `:count`, `:label`, and `:id`), and field type identifiers.

## Static checks

~~~sh
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
find tests -type f -name '*.js' -print0 | xargs -0 -n1 node --check
php tests/i18n-check.php
php -r 'json_decode(file_get_contents("module.json"), true, 512, JSON_THROW_ON_ERROR);'
php -r 'json_decode(file_get_contents("composer.json"), true, 512, JSON_THROW_ON_ERROR);'
reuse lint
git diff --check
~~~

Successful HTTP tests do not imply compatibility with every Akaunting version. Validate all supported versions before claiming broader support.

## Architecture

- `Providers/Main.php`: module initialization, menu event, routes, translations, and views.
- `Domain/Fields.php`: validation, metadata, transactions, search, and audit history.
- `Domain/Access.php`: company and record-level permission checks.
- `Domain/NativeHooks.php`: Akaunting view and model integration.
- `Http/Controllers/ApiController.php`: authenticated REST API.
- `Http/Controllers/AdminController.php`: configuration and record UI.
- `Database/Migrations`: namespaced module tables and indexes.

The module does not modify commercial Custom Fields data. Migration of external applications and tax/accounting logic are outside its scope.