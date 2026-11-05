<?php
// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

/**
 * Run from the installed FieldStudio module in Akaunting:
 * php modules/FieldStudio/scripts/production-metadata.php --company=1 --definitions [--apply]
 * php modules/FieldStudio/scripts/production-metadata.php --company=1 --all
 * php modules/FieldStudio/scripts/production-metadata.php --company=1 --all --apply --backup-dir=/private/path
 * php modules/FieldStudio/scripts/production-metadata.php --company=1 --period=2026-11
 * php modules/FieldStudio/scripts/production-metadata.php --company=1 --period=2026-11 --apply --backup-dir=/private/path
 * php modules/FieldStudio/scripts/production-metadata.php --company=1 --period=2026-11 --apply --clean --backup-dir=/private/path
 *
 * Dry-run is the default. No invoice, tax, payment, or NFS-e amounts are changed.
 */
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Modules\FieldStudio\Domain\Fields;
use Modules\FieldStudio\Domain\ProductionMetadataText;

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('CLI only');
}
$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$options = getopt('', ['company:', 'period::', 'all', 'definitions', 'apply', 'clean', 'backup-dir::']);
$company = filter_var($options['company'] ?? null, FILTER_VALIDATE_INT);
$period = $options['period'] ?? null;
$all = array_key_exists('all', $options);
$apply = array_key_exists('apply', $options);
$clean = array_key_exists('clean', $options);
if (!$company || $company < 1 || ($clean && !$apply)) {
    throw new InvalidArgumentException('Specify --company=ID; --clean requires --apply');
}
if ($period !== null && (!preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $period))) {
    throw new InvalidArgumentException('Period must use YYYY-MM');
}
if ($all && $period !== null) {
    throw new InvalidArgumentException('Use either --all or --period, never both');
}
if ($apply && (!isset($options['backup-dir']) || !is_dir($options['backup-dir']))) {
    throw new InvalidArgumentException('Writes require an existing private --backup-dir');
}
$fields = app(Fields::class);
$equivalent = static function (string $key, string $stored, string $source): bool {
    if ($stored === $source) {
        return true;
    }
    return $key === 'discount_percentage' && is_numeric($stored)
        && bccomp($stored, $source, 6) === 0;
};
$types = ['invoice', 'bill', 'transaction'];
$schema = [
    'transaction_of_month' => ['text', 'Competencia financeira (YYYY-MM)'],
    'discount_percentage' => ['percent', 'Percentual de desconto fixo'],
    'customer_cnpj' => ['text', 'CNPJ do cliente'],
    'sector' => ['text', 'Setor do cliente'],
    'archive' => ['boolean', 'Arquivar na producao cooperativista'],
    'nfse' => ['text', 'Referencia NFS-e'],
    'production_metadata_migrated' => ['boolean', 'Metadados da producao normalizados'],
];

if (array_key_exists('definitions', $options)) {
    $created = 0;
    foreach ($types as $type) {
        $existing = array_column($fields->definitions($company, $type), null, 'field_key');
        foreach ($schema as $key => [$fieldType, $label]) {
            if (isset($existing[$key])) {
                if ($existing[$key]['field_type'] !== $fieldType || !$existing[$key]['is_enabled']) {
                    throw new RuntimeException("Incompatible definition: $type.$key");
                }
                continue;
            }
            ++$created;
            if ($apply) {
                $fields->createDefinition($company, [
                    'entity_type' => $type,
                    'field_key' => $key,
                    'field_type' => $fieldType,
                    'label' => $label,
                    'description' => 'Dados estruturados da Produção Cooperativista; não substituir competência fiscal.',
                    'is_required' => false,
                    'is_visible' => true,
                    'is_enabled' => true,
                ]);
            }
        }
    }
    echo json_encode(['mode' => $apply ? 'definitions-installed' : 'definitions-preview',
        'company_id' => $company, 'missing_definitions' => $created], JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(0);
}
if (!$all && $period === null) {
    throw new InvalidArgumentException('Specify --all, --period=YYYY-MM or --definitions');
}
foreach ($types as $type) {
    $existing = array_column($fields->definitions($company, $type), null, 'field_key');
    foreach ($schema as $key => [$fieldType]) {
        if (!isset($existing[$key]) || $existing[$key]['field_type'] !== $fieldType
            || !$existing[$key]['is_enabled']) {
            throw new RuntimeException("Install required definitions first: $type.$key");
        }
    }
}

$parser = new ProductionMetadataText();
$docs = DB::table('documents')->where('company_id', $company)
    ->whereNull('deleted_at')->whereIn('type', ['invoice', 'bill'])
    ->get(['id', 'type', 'notes', 'due_at']);
$transactions = DB::table('transactions')->where('company_id', $company)
    ->whereNull('deleted_at')->get(['id', 'description', 'paid_at', 'document_id']);

// Re-running after cleanup must use the normalized month, never infer a
// different month from due_at/paid_at after the original line was removed.
$structuredValues = [];
foreach (DB::table('lcc_fieldstudio_values as value')
    ->join('lcc_fieldstudio_definitions as definition', 'definition.id', '=', 'value.definition_id')
    ->where('value.company_id', $company)->where('definition.company_id', $company)
    ->whereNull('definition.deleted_at')->whereIn('definition.field_key', array_keys($schema))
    ->get(['value.entity_type', 'value.entity_id', 'value.value', 'definition.field_key']) as $row) {
    $structuredValues[$row->entity_type . ':' . $row->entity_id][$row->field_key] = (string) $row->value;
}

$parsed = [];
$errors = [];
$selected = [];
$docById = [];
foreach ($docs as $row) {
    $type = (string) $row->type;
    $id = (int) $row->id;
    $key = $type . ':' . $id;
    $docById[$id] = $key;
    try {
        $parsed[$key] = $parser->extract((string) ($row->notes ?? ''));
        $sourceMonth = $parsed[$key]['values']['transaction_of_month'] ?? null;
        $storedMonth = $structuredValues[$key]['transaction_of_month'] ?? null;
        if ($sourceMonth !== null && $storedMonth !== null && $sourceMonth !== $storedMonth) {
            throw new RuntimeException('Source and normalized financial month disagree');
        }
        $effective = $sourceMonth ?? $storedMonth
            ?? substr((string) ($row->due_at ?? ''), 0, 7);
        if ($all || $effective === $period) {
            $selected[$key] = ['type' => $type, 'id' => $id, 'column' => 'notes',
                'original' => (string) ($row->notes ?? '')];
        }
    } catch (Throwable $e) {
        $errors[$key] = $e->getMessage();
        if ($all || substr((string) ($row->due_at ?? ''), 0, 7) === $period) {
            $selected[$key] = ['type' => $type, 'id' => $id, 'column' => 'notes',
                'original' => (string) ($row->notes ?? '')];
        }
    }
}
foreach ($transactions as $row) {
    $id = (int) $row->id;
    $key = 'transaction:' . $id;
    try {
        $parsed[$key] = $parser->extract((string) ($row->description ?? ''));
        $sourceMonth = $parsed[$key]['values']['transaction_of_month'] ?? null;
        $storedMonth = $structuredValues[$key]['transaction_of_month'] ?? null;
        if ($sourceMonth !== null && $storedMonth !== null && $sourceMonth !== $storedMonth) {
            throw new RuntimeException('Source and normalized financial month disagree');
        }
        $effective = $sourceMonth ?? $storedMonth
            ?? substr((string) ($row->paid_at ?? ''), 0, 7);
        if ($all || $effective === $period) {
            $selected[$key] = ['type' => 'transaction', 'id' => $id,
                'column' => 'description', 'original' => (string) ($row->description ?? '')];
            if ((int) ($row->document_id ?? 0) > 0) {
                $linked = $docById[(int) $row->document_id] ?? null;
                if ($linked === null) {
                    $errors[$key] = 'Linked document is not accessible in the selected company';
                } else {
                    $doc = $docs->first(static fn ($doc): bool => (int) $doc->id === (int) $row->document_id);
                    $selected[$linked] = ['type' => (string) $doc->type, 'id' => (int) $doc->id,
                        'column' => 'notes', 'original' => (string) ($doc->notes ?? '')];
                }
            }
        }
    } catch (Throwable $e) {
        $errors[$key] = $e->getMessage();
        if ($all || substr((string) ($row->paid_at ?? ''), 0, 7) === $period) {
            $selected[$key] = ['type' => 'transaction', 'id' => $id,
                'column' => 'description', 'original' => (string) ($row->description ?? '')];
        }
    }
}
ksort($selected);
// Preflight every structured value, not only the month. Reject mismatches
// before beginning a migration that otherwise commits one record at a time.
foreach ($selected as $key => $item) {
    foreach (($parsed[$key]['values'] ?? []) as $field => $value) {
        $stored = $structuredValues[$key][$field] ?? null;
        if ($stored !== null && !$equivalent($field, $stored, $value)) {
            $errors[$key] = 'Structured metadata conflicts with source: ' . $field;
            break;
        }
    }
}
$selectedErrors = array_intersect_key($errors, $selected);
$summary = [
    'mode' => $apply ? ($clean ? 'migrate-and-clean' : 'migrate-only') : 'dry-run',
    'company_id' => $company, 'period' => $all ? 'all' : $period,
    'scanned' => ['documents' => $docs->count(), 'transactions' => $transactions->count()],
    'selected' => count($selected),
    'recognized_lines' => array_sum(array_map(
        static fn (array $row): int => (int) ($parsed[$row['type'] . ':' . $row['id']]['matched_lines'] ?? 0),
        $selected
    )),
    'selected_errors' => count($selectedErrors),
    'other_period_errors' => count($errors) - count($selectedErrors),
];
if ($selectedErrors !== []) {
    echo json_encode($summary, JSON_THROW_ON_ERROR) . PHP_EOL;
    throw new RuntimeException('Invalid or conflicting source metadata in selected records; no writes performed');
}
if (!$apply) {
    echo json_encode($summary, JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(0);
}
$backupPath = rtrim((string) $options['backup-dir'], '/') . '/production-metadata-'
    . $company . '-' . ($all ? 'all' : $period) . '-' . gmdate('YmdHis') . '-' . getmypid() . '.jsonl';
$backup = fopen($backupPath, 'x');
if (!$backup) {
    throw new RuntimeException('Cannot create exclusive migration backup');
}
chmod($backupPath, 0600);
$updated = 0;
$cleaned = 0;
try {
    foreach ($selected as $key => $item) {
        $data = $parsed[$key];
        $values = $data['values'] + ['production_metadata_migrated' => '1'];
        $snapshot = [
            'company_id' => $company, 'type' => $item['type'], 'id' => $item['id'],
            'column' => $item['column'], 'sha256' => hash('sha256', $item['original']),
            'original' => $item['original'], 'values' => $values,
        ];
        fwrite($backup, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);
        fflush($backup);
        if (function_exists('fsync')) fsync($backup);

        DB::transaction(static function () use ($fields, $equivalent, $company, $item, $data, $values, $clean): void {
            $table = $item['type'] === 'transaction' ? 'transactions' : 'documents';
            $query = DB::table($table)->where('company_id', $company)
                ->where('id', $item['id'])->whereNull('deleted_at');
            if ($table === 'documents') $query->where('type', $item['type']);
            $current = $query->lockForUpdate()->first([$item['column']]);
            if (!$current || (string) ($current->{$item['column']} ?? '') !== $item['original']) {
                throw new RuntimeException('Source text changed concurrently; aborting this record');
            }
            $previous = array_column($fields->readValues($company, $item['type'], $item['id']), null, 'field_key');
            $missing = [];
            foreach ($values as $field => $value) {
                if (($previous[$field]['is_set'] ?? false)) {
                    if (!$equivalent($field, (string) $previous[$field]['value'], $value)) {
                        throw new RuntimeException('Existing structured value conflicts with source; manual review required');
                    }
                    continue;
                }
                $missing[$field] = $value;
            }
            if ($missing !== []) {
                $fields->saveValues($company, $item['type'], $item['id'], $missing);
            }
            $readBack = array_column($fields->readValues($company, $item['type'], $item['id']), null, 'field_key');
            foreach ($values as $field => $value) {
                if (!($readBack[$field]['is_set'] ?? false)
                    || !$equivalent($field, (string) $readBack[$field]['value'], $value)) {
                    throw new RuntimeException('Field Studio read-back mismatch; refusing cleanup');
                }
            }
            if ($clean && $data['matched_lines'] > 0 && $data['cleaned'] !== $item['original']) {
                $affected = $query->where($item['column'], $item['original'])
                    ->update([$item['column'] => $data['cleaned']]);
                if ($affected !== 1) {
                    throw new RuntimeException('Concurrent text update prevented cleanup');
                }
            }
        }, 3);
        ++$updated;
        if ($clean && $data['matched_lines'] > 0 && $data['cleaned'] !== $item['original']) {
            ++$cleaned;
        }
    }
} finally {
    fclose($backup);
}
$summary['updated_records'] = $updated;
$summary['cleaned_records'] = $cleaned;
$summary['backup_file'] = basename($backupPath);
echo json_encode($summary, JSON_THROW_ON_ERROR) . PHP_EOL;
