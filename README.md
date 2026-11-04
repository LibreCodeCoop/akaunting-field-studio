<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Field Studio for Akaunting

**Your organization has its own way of working. Your accounting software should adapt to it.**

Field Studio is an independent, open-source extension developed by [LibreCode](https://github.com/LibreCodeCoop) for [Akaunting](https://akaunting.com). It lets organizations capture additional structured information without modifying Akaunting's core or changing financial amounts.

Cost centers, contract numbers, project references, internal classifications, reporting periods, and document owners are just some examples of information that should live alongside your financial records.

## Why Field Studio?

| Business need | What Field Studio provides |
| --- | --- |
| Each team needs different data | Configure additional fields without writing code |
| Inconsistent manual entry | Required fields, selection lists, defaults, and type validation |
| Several companies use different processes | Company-scoped field definitions and values |
| External systems need the same information | Authenticated REST API for configuration and record data |
| Changes need to be traceable | Audit history and archival that preserves existing values |
| Avoid vendor lock-in | AGPL-licensed source code and no proprietary Field Studio runtime dependency |

Field Studio is designed for cooperatives, service providers, associations, finance teams, and organizations integrating Akaunting with CRM, workflow, or reporting tools.

**Scope:** Field Studio adds metadata. It does not independently change tax calculation, fiscal document issuance, accounting entries, or financial totals.

## Features

- **Self-service configuration:** create, update, enable, disable, reorder, and archive custom field definitions in **Settings → Field Studio**.
- **14 data types:** short and long text, email, URL, integer, exact decimal, percentage, date, time, date and time, yes/no, single selection, multiple selection, and contact reference.
- **Practical form design:** labels, descriptions, input hints, default values, required fields, visibility, and field width.
- **Native Akaunting experience:** additional inputs in invoice and bill forms, plus record editors for payments, contacts, products, and document items.
- **API-first integration:** full CRUD for field definitions and record values, with search and audit endpoints.
- **Security controls:** Akaunting permissions, company isolation, type validation, optimistic concurrency, and audit trails.
- **Safe storage:** independent namespaced tables; archiving a field does not erase previously recorded data.
- **Localization:** the interface follows the active Akaunting locale. English (en-GB) and Brazilian Portuguese (pt-BR) translations are included.

## Example

A team creates a required **Cost center** selection for sales invoices, with **Operations**, **Sales**, and **Administration** as options. Each invoice stores the selected value separately from its accounting fields, and connected systems can retrieve it through the API.

Field names, descriptions, and options entered by users are business data. They are **not automatically translated** when someone switches languages; the Field Studio interface itself is localized.

## Installation

Back up your Akaunting database and application files before installing a new module.

1. Copy the repository to `modules/FieldStudio` inside your Akaunting installation.
2. Run the following commands from your Akaunting application directory:

~~~sh
php artisan module:migrate field-studio --force
php artisan module:install field-studio 1 en-GB
php artisan optimize:clear
php artisan module:list
~~~

Replace `1` with the company ID that should use the module. The locale argument can match your installation, for example `pt-BR`. Enable the module separately for each authorized company that needs it.

3. Sign in, select your company, and open **Settings → Field Studio**, or visit `/{company_id}/field-studio`.

The module stores its own data in `lcc_fieldstudio_definitions`, `lcc_fieldstudio_values`, and `lcc_fieldstudio_audits` (with the configured Akaunting table prefix, if any). It does not alter the schema of the commercial Custom Fields extension.

## REST API

Base path:

~~~text
/api/v1/field-studio
~~~

Example request using Akaunting's configured authentication and company selection:

~~~sh
curl -u admin@example.invalid \
  -H 'Accept: application/json' \
  -H 'X-Company: 1' \
  https://your-akaunting.example/api/v1/field-studio/definitions
~~~

The address is synthetic. Use HTTPS and an account with the appropriate permissions in a real installation.

See the [API reference](docs/api.md) for methods, request/response examples, input validation, and concurrency handling.

## Support and contributing

Field Studio is free software: organizations can host, use, and modify it independently. Implementation assistance, integration, hosting, training, and maintenance can be obtained separately from a provider of their choice.

Contributions are welcome under our [contribution guidelines](CONTRIBUTING.md). Please use the [issue tracker](https://github.com/LibreCodeCoop/akaunting-field-studio/issues) for questions and feature proposals.