<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Field Studio REST API

Field Studio exposes an authenticated JSON API. It reuses Akaunting's company selection and access control.

**Base path:** `/api/v1/field-studio`

Use the authentication configured in your Akaunting installation. Supply `X-Company: 1` or `?company_id=1` when applicable, and only select a company that the caller is authorized to access. Company-scoped OAuth tokens retain their associated restrictions.

## Field definitions

| Method | Path | Behavior |
| --- | --- | --- |
| GET | /definitions | List non-archived definitions, optionally filtered by `entity_type` |
| POST | /definitions | Create a field definition; HTTP 201 |
| GET | /definitions/{id} | Read a definition |
| PUT / PATCH | /definitions/{id} | Update supplied attributes; `expected_version` recommended |
| DELETE | /definitions/{id} | Archive a definition; HTTP 204 (preserves values and history) |

Example request body:

~~~json
{
  "entity_type": "invoice",
  "field_key": "cost_center",
  "label": "Cost center",
  "field_type": "select",
  "options": ["Operations", "Sales", "Administration"],
  "description": "Department responsible for this invoice",
  "is_visible": true,
  "is_required": true,
  "is_enabled": true,
  "default_value": "Operations",
  "sort_order": 10,
  "column_span": 3
}
~~~

The combination of company, entity type, and field key is unique. The `entity_type` and `field_key` are immutable after creation, and archiving a definition does not free its key for reuse. A type cannot change when there are stored values. Selection options already used by records cannot be removed.

Supported field types: `text`, `textarea`, `email`, `url`, `integer`, `decimal`, `percent`, `date`, `time`, `datetime`, `boolean`, `select`, `multiselect`, and `contact`.

## Record values

| Method | Path | Behavior |
| --- | --- | --- |
| GET | /records/{entity_type}/{entity_id}/values | Read definitions and current/default values |
| POST / PATCH | /records/{entity_type}/{entity_id}/values | Create or partially update supplied values |
| PUT | /records/{entity_type}/{entity_id}/values | Replace the full set: omitted optional fields are cleared, omitted required fields return 422 |
| DELETE | /records/{entity_type}/{entity_id}/values/{field_key} | Remove a single value |
| GET | /records?entity_type=invoice&field_key=cost_center&value=Operations | Search for record IDs with an exact value |
| GET | /audit?entity_type=invoice&entity_id=123 | Read audit events for a record |

Entity types: `invoice`, `bill`, `transaction`, `contact`, `item`, and `document_item`.

Example PATCH request:

~~~json
{
  "values": {
    "cost_center": "Sales",
    "admin_fee": "27.450000"
  },
  "versions": {
    "cost_center": 1
  }
}
~~~

- Exact decimals are **strings**, not floating-point numbers, with up to six decimal places. Percentages range from 0 to 100.
- Dates use `YYYY-MM-DD`, times use `HH:MM` or `HH:MM:SS`, and date-times use `YYYY-MM-DDTHH:MM`.
- Multiple selection accepts a JSON array without duplicate or invalid options.
- Contact references use a contact ID belonging to the selected company.
- Each returned field includes `is_set` and `value_version` for concurrency control.

When `versions` is supplied, a stale version returns HTTP 409 and does not modify data. Any invalid value rejects the entire transaction. Idempotent writes do not create redundant audit entries.

### Structured values in the native Akaunting API

The existing `GET /api/documents?include_field_studio=1` and
`GET /api/transactions?include_field_studio=1` endpoints include a
`field_studio` object for authorized records. The opt-in query parameter
avoids extra database reads for unrelated API consumers. Keys match the
field definitions, with `value` and `is_set` for each. This avoids
additional requests per document/transaction and preserves Akaunting's
native pagination, filters, company context, and access control.
The `is_set` flag distinguishes an explicit value from a default.
If the module or a definition is unavailable, the consumer must fail
closed rather than parse free-text notes or descriptions.

## Authentication, authorization, and errors

| HTTP status | Meaning |
| --- | --- |
| 401 | Authentication required |
| 403 | Insufficient permission |
| 404 | Definition or record not found in the selected company |
| 409 | Version conflict |
| 422 | Invalid input, type, selection, or reference |
| 429 | Native Akaunting API rate limit exceeded |

Managing definitions requires permission to manage company settings. Reading or editing values requires the corresponding Akaunting record permission. Customer and vendor access is distinguished; line items follow the parent document's access rules. Global audit access requires company settings read permission.

**Localization:** human-readable validation errors follow the current Akaunting locale when the module provides translations. API paths, JSON attribute names, field keys, entity identifiers, and user-created option values remain stable and are not translated.

Use HTTPS and least-privileged credentials. Never test mutating endpoints against production data.