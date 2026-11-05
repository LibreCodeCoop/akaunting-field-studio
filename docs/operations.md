<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Operations and rollback

## Before installation

1. Record the Akaunting, PHP, and database versions, table prefix, and enabled modules. Ensure that `modules/FieldStudio` is not occupied by a different installation.
2. Make a consistent database backup and verify that it can be restored in an isolated environment. Back up the current application files.
3. Review authentication settings and authorized company IDs. Enable the module only for companies that need it.
4. Test the migration, user interface, API, and existing modules (including any fiscal extensions) in a disposable environment.
5. Deploy an approved, signed commit or release. Verify the exact SHA instead of relying on branch names.

## Installation

Copy the validated package into `modules/FieldStudio`. Do not overwrite an existing commercial Custom Fields module.

Run the native Akaunting commands with the appropriate company ID and locale:

~~~sh
php artisan module:migrate field-studio --force
php artisan module:install field-studio 1 en-GB
php artisan optimize:clear
php artisan module:list
php artisan route:list --path=field-studio
~~~

Then open **Settings → Field Studio** as an administrator and confirm that the interface follows the active Akaunting language. Check authenticated, read-only API requests. Do not create test invoices or transactions in production.

## Rollback without data loss

If there is an incompatibility, disable the extension for the affected company:

~~~sh
php artisan module:disable field-studio 1 en-GB
php artisan optimize:clear
~~~

The `lcc_fieldstudio_definitions`, `lcc_fieldstudio_values`, and `lcc_fieldstudio_audits` tables remain available, prefixed as configured by Akaunting. The migration's `down` operation is intentionally non-destructive. **Do not use `migrate:fresh`, `DROP TABLE`, or `db:wipe`.**

Only remove stored metadata after separately approved inventory, export, and retention checks.

## Operational constraints

- Requests obey the host application's authentication, permissions, company selection, and rate limit.
- Enabling a company does not grant access to another.
- Localization is selected by the Akaunting user/application locale; field labels and options entered by users are not automatically translated.
- Field Studio does not change tax amounts, accounting totals, or fiscal document transmission.
- Compatibility with additional Akaunting versions requires its own test matrix.

## Production Cooperativista metadata normalization

The module can normalize the six free-text metadata keys used by Produção
Cooperativista and a seventh per-record migration marker. The native Akaunting
document and transaction API returns these fields only with
`include_field_studio=1`. Keep company and record permissions intact.

Run the migration script from the installed module in the Akaunting PHP
container, with a **private, existing backup directory**. Dry-run is the
default:

~~~sh
php modules/FieldStudio/scripts/production-metadata.php --company=1 --definitions
php modules/FieldStudio/scripts/production-metadata.php --company=1 --definitions --apply --backup-dir=/private/backups
php modules/FieldStudio/scripts/production-metadata.php --company=1 --period=2026-11
php modules/FieldStudio/scripts/production-metadata.php --company=1 --period=2026-11 --apply --backup-dir=/private/backups
~~~

Compare the financial closing with the pre-migration baseline and verify that
the native API exposes `field_studio` with `is_set=true` for each record.
Only then clean the recognized lines, preserving all unrelated content:

~~~sh
php modules/FieldStudio/scripts/production-metadata.php --company=1 --period=2026-11 --apply --clean --backup-dir=/private/backups
~~~

Each run creates a mode-0600 JSONL backup with original text, SHA-256, field
values, and record identifiers. The script checks concurrent edits and existing
structured values, verifies read-back before cleanup, and is idempotent.
No fiscal or monetary fields are changed. Repeat for the preceding period only
after reconciliation. Do not deploy the parser-free consumer before its source
records have been normalized; an unmarked record intentionally blocks import.
