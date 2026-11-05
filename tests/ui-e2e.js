// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const { URL } = require('node:url');
const { chromium } = require(process.env.FS_PLAYWRIGHT_PATH || 'playwright');

(async () => {
    const base = process.env.FS_BASE_URL;
    const passwordFile = process.env.FS_ADMIN_PASSWORD_FILE;
    assert.ok(base && passwordFile, 'FS_BASE_URL and FS_ADMIN_PASSWORD_FILE are required');
    assert.ok(['localhost', '127.0.0.1', '::1'].includes(new URL(base).hostname),
        'Refusing to run E2E mutations against a nonlocal environment');

    const company = process.env.FS_TEST_COMPANY_ID || '1';
    const invoice = process.env.FS_TEST_INVOICE_ID || '1';
    const outDir = process.env.FS_SCREENSHOT_DIR;
    if (outDir) fs.mkdirSync(outDir, { recursive: true });

    const browser = await chromium.launch({
        executablePath: process.env.FS_CHROME_PATH || '/usr/bin/google-chrome',
        headless: true, args: ['--no-sandbox'],
    });
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    page.setDefaultTimeout(12000);
    const checks = [];
    const pass = name => { checks.push(name); console.log('PASS: ' + name); };
    const locale = process.env.FS_TEST_LOCALE || 'en-GB';
    const localeTexts = {
        'en-GB': { heading: 'Customize your records', add: 'Add field', fieldName: 'Field name',
            edit: 'Edit', saveValues: 'Save additional information', archive: 'Archive',
            selectHint: 'Choose one of the available options.', nativeHeading: 'Additional information',
            confirmArchive: 'Archive this field? Existing values and audit history will be kept.' },
        'pt-BR': { heading: 'Personalize seus registros', add: 'Novo campo', fieldName: 'Nome do campo',
            edit: 'Editar', saveValues: 'Salvar informações adicionais', archive: 'Arquivar',
            selectHint: 'Escolha uma das opções disponíveis.', nativeHeading: 'Informações adicionais',
            confirmArchive: 'Arquivar este campo? Os valores existentes e o histórico serão preservados.' },
    };
    assert.ok(localeTexts[locale], 'FS_TEST_LOCALE must be en-GB or pt-BR');
    const texts = localeTexts[locale];
    const key = 'fs_ui_' + Date.now().toString(36);
    const label = 'Centro de custo (E2E)';

    const screenshot = async name => {
        if (outDir) await page.screenshot({ path: outDir + '/' + name + '.png', fullPage: true });
    };

    try {
        await page.goto(base + '/auth/login', { waitUntil: 'domcontentloaded' });
        await page.locator('input[name=email]').fill(process.env.FS_ADMIN_EMAIL || 'admin@example.invalid');
        await page.locator('input[name=password]').fill(fs.readFileSync(passwordFile, 'utf8').trim());
        await page.locator('form button[type=submit]').first().click();
        await page.waitForTimeout(1300);
        await page.goto(base + '/' + company + '/field-studio', { waitUntil: 'domcontentloaded' });
        assert.ok(page.url().includes('/field-studio'), 'Login failed or module not accessible');
        assert.ok(await page.getByRole('heading', { name: texts.heading }).count() === 1, 'Incorrect settings locale');
        pass('Field Studio settings page opens in ' + locale);

        await screenshot('field-studio-settings');
        await page.getByRole('link', { name: texts.add }).first().click();
        assert.ok(page.url().endsWith('/field-studio/new'));
        assert.ok((await page.locator('label[for="field-label"]').innerText()).includes(texts.fieldName), 'Field label not localized');
        pass('Native new-field screen opens in ' + locale);

        await page.locator('#field-label').fill(label);
        await page.locator('#field-key').fill(key);
        await page.locator('#field-entity').selectOption('invoice');
        await page.locator('#field-type').selectOption('select');
        assert.ok(await page.locator('#field-options').isVisible(), 'Options should appear for select');
        assert.equal((await page.locator('#field-type-description').innerText()).trim(), texts.selectHint,
            'JavaScript field hints must follow the active locale');
        pass('Selection type reveals localized options editor');
        await page.locator('#field-options-input').fill('Operações\nComercial\nAdministração');
        await page.locator('#field-description').fill('Selecione o centro responsável pelo documento.');
        await page.locator('#field-default').fill('Operações');
        await page.locator('#field-span').selectOption('3');
        await screenshot('field-studio-definition');
        await page.locator('#field-save').click();
        await page.waitForURL(url => url.pathname === '/' + company + '/field-studio');
        assert.ok((await page.locator('body').innerText()).includes(label), 'Created definition not listed');
        pass('HTML form creates definition');

        // The Field Studio index uses Akaunting's table and sortable-link
        // components, preserving the active record-type filter on each sort.
        await page.goto(base + '/' + company + '/field-studio?entity_type=invoice',
            { waitUntil: 'domcontentloaded' });
        const definitionsTable = page.locator('table#field-studio-definitions');
        assert.equal(await definitionsTable.count(), 1, 'Native Akaunting table is required');
        const sortLabel = definitionsTable.locator('thead a[href*="sort=label"]');
        assert.equal(await sortLabel.count(), 1, 'Field label should be sortable');
        for (const column of ['entity_type', 'field_type', 'is_enabled']) {
            assert.equal(await definitionsTable.locator(
                'thead a[href*="sort=' + column + '"]').count(), 1,
            column + ' should be sortable');
        }
        await sortLabel.click();
        const asc = new URL(page.url());
        assert.equal(asc.searchParams.get('sort'), 'label');
        assert.equal(asc.searchParams.get('direction'), 'asc');
        assert.equal(asc.searchParams.get('entity_type'), 'invoice');
        const ascLabels = await page.locator('table#field-studio-definitions tbody tr td:first-child')
            .allTextContents();
        await page.locator('table#field-studio-definitions thead a[href*="sort=label"]').click();
        const desc = new URL(page.url());
        assert.equal(desc.searchParams.get('sort'), 'label');
        assert.equal(desc.searchParams.get('direction'), 'desc');
        assert.equal(desc.searchParams.get('entity_type'), 'invoice');
        const descLabels = await page.locator('table#field-studio-definitions tbody tr td:first-child')
            .allTextContents();
        assert.deepEqual(descLabels, [...ascLabels].reverse(),
            'Descending names should reverse the ascending table order');
        pass('Native table uses sortable columns without dropping filters');

        await page.getByRole('link', { name: texts.edit + ' ' + label }).click();
        await page.locator('#field-label').fill('Centro de custo revisado (E2E)');
        await page.locator('#field-save').click();
        await page.waitForURL(url => url.pathname === '/' + company + '/field-studio');
        assert.ok((await page.locator('body').innerText()).includes('Centro de custo revisado (E2E)'));
        pass('HTML form updates definition');

        await page.goto(base + '/' + company + '/field-studio/records/invoice/' + invoice,
            { waitUntil: 'domcontentloaded' });
        const input = page.locator('select[name="values[' + key + ']"]');
        await input.selectOption('Comercial');
        await page.getByRole('button', { name: texts.saveValues }).click();
        await page.waitForTimeout(500);
        assert.equal(await input.inputValue(), 'Comercial');
        pass('HTML record editor saves and reloads a selected value');
        await screenshot('field-studio-record');

        await page.goto(base + '/' + company + '/sales/invoices/' + invoice + '/edit',
            { waitUntil: 'domcontentloaded' });
        await page.getByText(/Advanced|Avançado/i, { exact: true }).first().click();
        const native = page.locator('select[name="custom_fields[' + key + ']"]');
        assert.ok(await native.count() === 1, 'Field must be injected into the native invoice form');
        assert.equal((await page.locator('#field-studio-native h3').innerText()).trim(), texts.nativeHeading,
            'Native invoice section must follow the active locale');
        await native.selectOption('Administração');
        await screenshot('field-studio-native-invoice');
        await page.getByRole('button', { name: /^(Save|Salvar)$/, exact: true }).first().click();
        await page.waitForTimeout(900);
        assert.ok(page.url().includes('/sales/invoices/' + invoice), 'Native invoice update did not complete');
        await page.goto(base + '/' + company + '/field-studio/records/invoice/' + invoice,
            { waitUntil: 'domcontentloaded' });
        assert.equal(await page.locator('select[name="values[' + key + ']"]').inputValue(), 'Administração');
        pass('Native Akaunting invoice save persists Field Studio value');

        await page.goto(base + '/' + company + '/field-studio', { waitUntil: 'domcontentloaded' });
        let archiveConfirmation;
        page.once('dialog', dialog => {
            archiveConfirmation = dialog.message();
            dialog.accept();
        });
        await page.getByRole('button', { name: texts.archive + ' Centro de custo revisado (E2E)' }).click();
        await page.waitForTimeout(500);
        assert.equal(archiveConfirmation, texts.confirmArchive, 'Archive confirmation must be localized');
        assert.ok(!(await page.locator('body').innerText()).includes('Centro de custo revisado (E2E)'));
        pass('HTML form archives definition');

        console.log('UI E2E ALL PASS (' + locale + '): ' + checks.length);
    } finally {
        await browser.close();
    }
})().catch(error => { console.error('UI E2E FAIL: ' + error.stack); process.exitCode = 1; });