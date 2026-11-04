<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Security policy

## Private vulnerability reporting

Use [Report a vulnerability](https://github.com/LibreCodeCoop/akaunting-field-studio/security/advisories/new) on GitHub. Do not publish exploitable details in a public issue.

Include your Field Studio, Akaunting, PHP, and database versions; a reproduction using synthetic data; and a description of the impact. Do not share live credentials or financial database dumps.

Maintenance capacity and response times depend on project maintainers; no specific SLA is promised.

## Security model

Field Studio relies on Akaunting authentication and authorization while enforcing its own company boundaries, source-record access checks, typed validation, and transactional consistency. Production deployments require working backups, HTTPS, and timely security updates.

API error messages may be localized, but HTTP status codes and permission behavior must be independent of locale.