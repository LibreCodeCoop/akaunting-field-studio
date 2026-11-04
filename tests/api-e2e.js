// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
'use strict';

// Run only against a disposable Akaunting installation with synthetic records.
// Example:
// FS_PLAYWRIGHT_PATH=/path/to/node_modules/playwright \
// FS_BASE_URL=http://127.0.0.1:18086 \
// FS_ADMIN_PASSWORD_FILE=/secure/path/test-admin-password \
// node tests/api-e2e.js

const assert = require('node:assert/strict');
const fs = require('node:fs');
const { URL } = require('node:url');
const { request } = require(process.env.FS_PLAYWRIGHT_PATH || 'playwright');

(async function run() {
    const baseURL = process.env.FS_BASE_URL;
    const pwdPath = process.env.FS_ADMIN_PASSWORD_FILE;
    const adminEmail = process.env.FS_ADMIN_EMAIL || 'admin@example.invalid';

    assert.ok(baseURL && pwdPath, 'FS_BASE_URL and FS_ADMIN_PASSWORD_FILE are required');
    const host = new URL(baseURL);
    assert.ok(['127.0.0.1', 'localhost', '::1'].includes(host.hostname), 'Refusing to modify a non-local endpoint');

    const company = process.env.FS_TEST_COMPANY_ID || '1';
    const invoiceId = Number(process.env.FS_TEST_INVOICE_ID || '1');
    const transactionId = Number(process.env.FS_TEST_TRANSACTION_ID || '1');
    const cases = [
        ['bill', Number(process.env.FS_TEST_BILL_ID || '3')],
        ['transaction', transactionId],
        ['contact', Number(process.env.FS_TEST_CONTACT_ID || '1')],
        ['item', Number(process.env.FS_TEST_ITEM_ID || '1')],
        ['document_item', Number(process.env.FS_TEST_LINE_ID || '1')],
    ];

    const api = await request.newContext({
        baseURL,
        httpCredentials: { username: adminEmail, password: fs.readFileSync(pwdPath, 'utf8').trim() },
        extraHTTPHeaders: { Accept: 'application/json', 'X-Company': company, 'Content-Type': 'application/json' },
    });
    const results = [];
    const base = '/api/v1/field-studio';
    const prefix = 'fs_t' + Date.now().toString(36) + '_';
    const key = name => prefix + name;

    const check = (name, condition, details) => {
        results.push({ name, passed: Boolean(condition) });
        if (!condition) {
            throw new Error(name + ': ' + String(JSON.stringify(details)).slice(0, 500));
        }
        console.log('PASS: ' + name);
    };

    const call = async (method, resource, payload) => {
        const resp = await api[method](base + resource, payload === undefined ? {} : { data: payload });
        let body;
        try { body = await resp.json(); } catch { body = null; }
        return { status: resp.status(), body };
    };

    const defs = [];
    const define = async (name, type, entity = 'invoice', options = undefined, extra = {}) => {
        const payload = {
            entity_type: entity,
            field_key: key(name + '_' + entity),
            label: 'Synthetic field ' + name,
            field_type: type,
            ...extra,
        };
        if (options !== undefined) payload.options = options;
        const created = await call('post', '/definitions', payload);
        check('POST definition ' + name + '/' + entity, created.status === 201, created);
        defs.push(created.body.data);
        return created.body.data;
    };

    try {
        let resp = await call('get', '/definitions');
        check('GET definitions', resp.status === 200 && Array.isArray(resp.body.data), resp);

        const required = await define('required', 'text', 'invoice', undefined, { is_required: true });
        const select = await define('team', 'select', 'invoice', ['Operations', 'Commercial', 'Management']);
        const types = [
            ['long', 'textarea', undefined, 'Detailed note'],
            ['email', 'email', undefined, 'test@example.invalid'],
            ['url', 'url', undefined, 'https://example.org/path'],
            ['integer', 'integer', undefined, 2026],
            ['decimal', 'decimal', undefined, '123.456789'],
            ['percent', 'percent', undefined, '27.450000'],
            ['date', 'date', undefined, '2028-02-29'],
            ['time', 'time', undefined, '13:45'],
            ['datetime', 'datetime', undefined, '2028-02-29T13:45'],
            ['boolean', 'boolean', undefined, false],
            ['multiple', 'multiselect', ['Operations', 'Commercial'], ['Operations', 'Commercial']],
            ['contact', 'contact', undefined, String(process.env.FS_TEST_CONTACT_ID || '1')],
        ];
        for (const [name, type, options] of types) {
            await define(name, type, 'invoice', options);
        }

        const valueMap = {
            [required.field_key]: 'CC-42',
            [select.field_key]: 'Operations',
        };
        for (const [name, , , value] of types) {
            valueMap[key(name + '_invoice')] = value;
        }

        const valuesPath = '/records/invoice/' + invoiceId + '/values';
        resp = await call('post', valuesPath, { values: valueMap });
        check('POST custom values with all supported types', resp.status === 200, resp);

        const stored = new Map(resp.body.data.map(item => [item.field_key, item]));
        for (const [name, value] of Object.entries(valueMap)) {
            const expected = name === key('boolean_invoice') ? '0' : typeof value === 'number' ? String(value) : value;
            check('normalize ' + name, JSON.stringify(stored.get(name)?.value) === JSON.stringify(expected), stored.get(name));
        }

        resp = await call('get', valuesPath);
        check('GET values', resp.status === 200 && resp.body.data.some(v => v.field_key === required.field_key), resp);

        let version = resp.body.data.find(v => v.field_key === required.field_key).value_version;
        resp = await call('patch', valuesPath, { values: { [required.field_key]: 'CC-45' }, versions: { [required.field_key]: version } });
        check('PATCH partial update', resp.status === 200 && resp.body.data.find(v => v.field_key === required.field_key).value === 'CC-45', resp);
        resp = await call('patch', valuesPath, { values: { [required.field_key]: 'stale' }, versions: { [required.field_key]: version } });
        check('409 on stale version', resp.status === 409, resp);

        resp = await call('put', valuesPath, { values: { [select.field_key]: 'Commercial' } });
        check('PUT rejects omitted required fields', resp.status === 422, resp);

        resp = await call('put', valuesPath, { values: { [required.field_key]: 'Replacement CC' } });
        check('PUT replaces full representation', resp.status === 200
            && resp.body.data.find(v => v.field_key === required.field_key)?.value === 'Replacement CC'
            && resp.body.data.find(v => v.field_key === select.field_key)?.is_set === false, resp);

        resp = await call('patch', valuesPath, { values: { [select.field_key]: 'Operations' } });
        check('PATCH restores selection', resp.status === 200, resp);

        resp = await call('patch', valuesPath, { values: { [select.field_key]: 'Unknown' } });
        check('422 for invalid option', resp.status === 422, resp);
        resp = await call('patch', valuesPath, { values: { [key('date_invoice')]: '2026-02-29' } });
        check('422 for invalid date', resp.status === 422, resp);
        resp = await call('patch', valuesPath, { values: { [key('time_invoice')]: '27:00' } });
        check('422 for invalid time', resp.status === 422, resp);
        resp = await call('patch', valuesPath, { values: { [key('datetime_invoice')]: '2026-02-29T09:30' } });
        check('422 for invalid datetime', resp.status === 422, resp);
        resp = await call('patch', valuesPath, { values: { [key('decimal_invoice')]: 3.14 } });
        check('422 for floating point', resp.status === 422, resp);
        resp = await call('patch', valuesPath, { values: { [key('percent_invoice')]: '130' } });
        check('422 for percent outside range', resp.status === 422, resp);
        resp = await call('patch', valuesPath, { values: { [key('multiple_invoice')]: ['Unknown'] } });
        check('422 for unknown multiselection', resp.status === 422, resp);
        resp = await call('patch', valuesPath, { values: { [key('url_invoice')]: 'ftp://example.org' } });
        check('422 for unsafe URL', resp.status === 422, resp);
        resp = await call('patch', valuesPath, { values: { ['unknown_key']: 'invalid' } });
        check('422 for unknown key', resp.status === 422, resp);

        resp = await call('patch', '/definitions/' + select.id, { options: ['Commercial'] });
        check('422 when deleting an option in use', resp.status === 422, resp);
        resp = await call('put', '/definitions/' + required.id, { label: 'Cost centre', expected_version: required.version });
        check('PUT updates definition', resp.status === 200 && resp.body.data.label === 'Cost centre', resp);
        resp = await call('patch', '/definitions/' + required.id, { label: 'stale label', expected_version: required.version });
        check('409 when definition changed concurrently', resp.status === 409, resp);
        resp = await call('patch', '/definitions/' + required.id, { field_type: 'integer' });
        check('422 when changing a used field type', resp.status === 422, resp);
        resp = await call('get', '/definitions/' + required.id);
        check('GET definition by id', resp.status === 200, resp);

        resp = await call('get', '/records?entity_type=invoice&field_key=' + required.field_key + '&value=Replacement%20CC');
        check('Filter by value', resp.status === 200 && resp.body.data.includes(invoiceId), resp);

        resp = await call('get', '/audit?entity_type=invoice&entity_id=' + invoiceId);
        check('Read audit events', resp.status === 200 && resp.body.data.some(v => v.operation === 'value.updated'), resp);

        resp = await call('delete', valuesPath + '/' + select.field_key);
        check('DELETE individual value', resp.status === 200 && resp.body.data.find(v => v.field_key === select.field_key)?.is_set === false, resp);

        // Each entity has a full create / read / update / archive round-trip.
        for (const [entity, id] of cases) {
            const def = await define('extra', 'text', entity);
            const rec = '/records/' + entity + '/' + id + '/values';
            resp = await call('post', rec, { values: { [def.field_key]: 'initial' } });
            check(entity + ': POST value', resp.status === 200, resp);
            resp = await call('patch', rec, { values: { [def.field_key]: 'updated' } });
            check(entity + ': PATCH value', resp.status === 200, resp);
            resp = await call('get', rec);
            check(entity + ': GET value', resp.status === 200 && resp.body.data.some(f => f.field_key === def.field_key && f.value === 'updated'), resp);
            resp = await call('delete', rec + '/' + def.field_key);
            check(entity + ': DELETE value', resp.status === 200 && resp.body.data.some(f => f.field_key === def.field_key && !f.is_set), resp);
        }

        const invalid = await call('get', '/records/invoice/999999/values');
        check('404 for nonexistent record', invalid.status === 404, invalid);

        const anonymous = await request.newContext({
            baseURL, extraHTTPHeaders: { Accept: 'application/json', 'X-Company': company },
        });
        const unauthenticated = await anonymous.get(base + '/definitions');
        check('401 for unauthenticated request', unauthenticated.status() === 401);
        await anonymous.dispose();

        const tenant = await api.get(base + '/definitions', { headers: { 'X-Company': '2' } });
        check('Unknown company inaccessible', tenant.status() !== 200);

        if (process.env.FS_VIEWER_PASSWORD_FILE) {
            const viewer = await request.newContext({
                baseURL,
                httpCredentials: {
                    username: process.env.FS_VIEWER_EMAIL || 'viewer@example.invalid',
                    password: fs.readFileSync(process.env.FS_VIEWER_PASSWORD_FILE, 'utf8').trim(),
                },
                extraHTTPHeaders: { Accept: 'application/json', 'X-Company': company },
            });
            check('403 for nonadmin definitions', (await viewer.get(base + '/definitions')).status() === 403);
            check('200 for reader invoice', (await viewer.get(base + valuesPath)).status() === 200);
            check('403 for readonly invoice write', (await viewer.patch(base + valuesPath, {
                data: { values: { [required.field_key]: 'forbidden' } }
            })).status() === 403);
            check('403 for vendor contact (role isolation)', (await viewer.get(
                base + '/records/contact/' + Number(process.env.FS_VENDOR_ID || 3) + '/values'
            )).status() === 403);
            await viewer.dispose();
        }

        console.log('ALL API E2E CHECKS PASS: ' + results.length);
        if (process.env.FS_RESULTS_FILE) {
            fs.writeFileSync(process.env.FS_RESULTS_FILE,
                JSON.stringify({ date: new Date().toISOString(), checks: results }, null, 2));
        }
    } finally {
        for (const def of defs) {
            const result = await call('delete', '/definitions/' + def.id);
            if (result.status !== 204) console.error('Cleanup warning for definition', def.id, result.status);
        }
        await api.dispose();
    }
})().catch(error => {
    console.error('API E2E FAILURE: ' + error.stack);
    process.exitCode = 1;
});