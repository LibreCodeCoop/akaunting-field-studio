<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Contributing

Contributions to Field Studio are welcome.

## Pull requests

Review existing issues before starting changes. Describe the intended behavior, security implications, and at least one positive and one negative test case. Never commit credentials, private customer data, financial database dumps, or screenshots from real installations.

Use a feature branch and open a pull request for human review. Commits must follow [Conventional Commits](https://www.conventionalcommits.org/), include a DCO `Signed-off-by` trailer matching the actual author, and carry a verifiable cryptographic signature. Do not automatically squash commits.

Run the PHP syntax checks, JSON validation, REUSE licensing check, and applicable disposable-environment integration tests before requesting review. See [Development and testing](docs/development.md).

## Quality and security

Verify the definition and value CRUD, all supported types, validation failures, authorization boundaries, company isolation, and the native invoice form before submitting changes. The end-to-end runner must never modify production data. See [Development and testing](docs/development.md) and [Operations and rollback](docs/operations.md).

For vulnerability reports, use [private GitHub security advisories](https://github.com/LibreCodeCoop/akaunting-field-studio/security/advisories) instead of public issues. See [SECURITY.md](SECURITY.md).

## Translating the interface

English (`en-GB`) is the reference language. Add new human-facing strings to the module's translation catalogs, keeping keys and placeholders consistent with the Brazilian Portuguese (`pt-BR`) catalog. Do not hardcode text in Blade templates, controllers, validation errors, or JavaScript.

Run `php tests/i18n-check.php` and verify both locales in the browser.

## Reporting vulnerabilities

Use the private reporting channel in [SECURITY.md](SECURITY.md). Do not disclose exploit details in public issues.