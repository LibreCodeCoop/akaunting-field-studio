<?php
// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);

namespace Modules\FieldStudio\Domain;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class Fields
{
    public const TYPES = [
        'text', 'textarea', 'email', 'url', 'integer', 'decimal', 'percent',
        'date', 'time', 'datetime', 'boolean', 'select', 'multiselect', 'contact',
    ];

    public const ENTITIES = ['invoice', 'bill', 'transaction', 'contact', 'item', 'document_item'];

    private const TARGETS = [
        'invoice' => ['documents', 'invoice'],
        'bill' => ['documents', 'bill'],
        'transaction' => ['transactions', null],
        'contact' => ['contacts', null],
        'item' => ['items', null],
        'document_item' => ['document_items', null],
    ];

    public function company(): int
    {
        $id = function_exists('company_id') ? (int) company_id() : 0;
        abort_if($id <= 0, 400, trans('field-studio::general.errors.missing_company'));
        return $id;
    }

    public function definitions(int $company, ?string $entity = null, bool $enabledOnly = false): array
    {
        $query = DB::table('lcc_fieldstudio_definitions')
            ->where('company_id', $company)->whereNull('deleted_at');

        if ($entity !== null) {
            $this->assertType($entity);
            $query->where('entity_type', $entity);
        }

        if ($enabledOnly) {
            $query->where('is_enabled', true);
        }

        return $query->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (object $row): array => $this->definitionArray($row))->all();
    }

    public function definition(int $company, int $id): array
    {
        $row = DB::table('lcc_fieldstudio_definitions')
            ->where('company_id', $company)->whereNull('deleted_at')
            ->where('id', $id)->first();

        if (!$row) {
            throw new NotFoundHttpException(trans('field-studio::general.errors.definition_not_found_company'));
        }

        return $this->definitionArray($row);
    }

    private function definitionArray(object $row): array
    {
        $field = (array) $row;
        $field['options'] = $row->options === null
            ? [] : json_decode((string) $row->options, true, 512, JSON_THROW_ON_ERROR);
        $field['translations'] = $row->translations === null
            ? [] : json_decode((string) $row->translations, true, 512, JSON_THROW_ON_ERROR);

        foreach (['is_required', 'is_visible', 'is_enabled'] as $flag) {
            $field[$flag] = (bool) $field[$flag];
        }

        return $field;
    }

    public function createDefinition(int $company, array $input, ?int $actor = null): array
    {
        $fields = $this->validateDefinition($input, true);
        $this->validateOptions($fields);

        if (array_key_exists('default_value', $fields)) {
            $fields['default_value'] = in_array($fields['default_value'], [null, ''], true)
                ? null : $this->normalize($fields, $fields['default_value'], $company);
        }

        return DB::transaction(function () use ($company, $fields, $actor): array {
            if (DB::table('lcc_fieldstudio_definitions')
                ->where('company_id', $company)->where('entity_type', $fields['entity_type'])
                ->where('field_key', $fields['field_key'])->exists()) {
                throw ValidationException::withMessages([
                    'field_key' => trans('field-studio::general.errors.field_key_reserved'),
                ]);
            }

            try {
                $id = DB::table('lcc_fieldstudio_definitions')->insertGetId(array_merge([
                    'company_id' => $company,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $this->encodeDefinition($fields)));
            } catch (QueryException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    throw ValidationException::withMessages(['field_key' => trans('field-studio::general.errors.field_key_reserved_simple')]);
                }
                throw $e;
            }

            $definition = $this->definition($company, (int) $id);
            $this->audit(
                $company, $actor, 'definition.created', $definition['entity_type'],
                null, (int) $id, null, json_encode($definition, JSON_THROW_ON_ERROR)
            );

            return $definition;
        });
    }

    public function updateDefinition(int $company, int $id, array $input, ?int $actor = null): array
    {
        $old = $this->definition($company, $id);
        $fields = $this->validateDefinition($input, false);
        $expectedVersion = isset($fields['expected_version']) ? (int) $fields['expected_version'] : null;
        unset($fields['expected_version']);

        if ($expectedVersion !== null && $expectedVersion !== (int) $old['version']) {
            throw new ConflictHttpException(trans('field-studio::general.errors.definition_concurrent'));
        }

        $effective = array_merge($old, $fields);

        if ($effective['field_type'] !== $old['field_type']
            && DB::table('lcc_fieldstudio_values')
                ->where('company_id', $company)->where('definition_id', $id)->exists()) {
            throw ValidationException::withMessages(['field_type' => trans('field-studio::general.errors.type_with_values')]);
        }

        $this->validateOptions($effective);
        if ($old['field_type'] === 'select' && array_key_exists('options', $fields)
            && DB::table('lcc_fieldstudio_values')
                ->where('company_id', $company)->where('definition_id', $id)
                ->whereNotIn('value', $effective['options'])->exists()) {
            throw ValidationException::withMessages(['options' => trans('field-studio::general.errors.option_in_use')]);
        }
        if ($old['field_type'] === 'multiselect' && array_key_exists('options', $fields)) {
            $rows = DB::table('lcc_fieldstudio_values')
                ->where('company_id', $company)->where('definition_id', $id)->pluck('value');

            foreach ($rows as $value) {
                $selected = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
                if (array_diff($selected, $effective['options']) !== []) {
                    throw ValidationException::withMessages(['options' => trans('field-studio::general.errors.option_in_use')]);
                }
            }
        }

        // Validate a previous default against a modified type/options, too.
        if (array_key_exists('default_value', $fields) || array_key_exists('field_type', $fields)
            || array_key_exists('options', $fields)) {
            $fields['default_value'] = in_array($effective['default_value'], [null, ''], true)
                ? null : $this->normalize($effective, $effective['default_value'], $company);
        }

        if ($fields === []) {
            return $old;
        }

        return DB::transaction(function () use ($company, $id, $old, $fields, $actor): array {
            $affected = DB::table('lcc_fieldstudio_definitions')
                ->where('id', $id)->where('company_id', $company)->whereNull('deleted_at')
                ->where('version', $old['version'])
                ->update(array_merge($this->encodeDefinition($fields), [
                    'version' => (int) $old['version'] + 1,
                    'updated_at' => now(),
                ]));

            if ($affected !== 1) {
                throw new ConflictHttpException(trans('field-studio::general.errors.definition_concurrent'));
            }

            $new = $this->definition($company, $id);
            $this->audit(
                $company, $actor, 'definition.updated', $old['entity_type'],
                null, $id, json_encode($old, JSON_THROW_ON_ERROR),
                json_encode($new, JSON_THROW_ON_ERROR)
            );

            return $new;
        });
    }

    public function deleteDefinition(int $company, int $id, ?int $actor = null): void
    {
        DB::transaction(function () use ($company, $id, $actor): void {
            $old = $this->definition($company, $id);
            $updated = DB::table('lcc_fieldstudio_definitions')
                ->where('id', $id)->where('company_id', $company)
                ->where('version', $old['version'])->whereNull('deleted_at')
                ->update([
                    'is_enabled' => false,
                    'deleted_at' => now(),
                    'updated_at' => now(),
                    'version' => (int) $old['version'] + 1,
                ]);

            if ($updated !== 1) {
                throw new ConflictHttpException(trans('field-studio::general.errors.definition_concurrent'));
            }

            $this->audit(
                $company, $actor, 'definition.archived', $old['entity_type'],
                null, $id, json_encode($old, JSON_THROW_ON_ERROR), null
            );
        });
    }

    private function validateDefinition(array $input, bool $creating): array
    {
        $rules = [
            'entity_type' => [$creating ? 'required' : 'sometimes', Rule::in(self::ENTITIES)],
            'field_key' => [
                $creating ? 'required' : 'sometimes',
                'string', 'regex:/^[a-z][a-z0-9_]{1,70}$/',
            ],
            'label' => [$creating ? 'required' : 'sometimes', 'string', 'max:180'],
            'field_type' => [$creating ? 'required' : 'sometimes', Rule::in(self::TYPES)],
            'options' => ['sometimes', 'nullable', 'array', 'max:100'],
            'options.*' => ['string', 'max:255', 'distinct'],
            'translations' => ['sometimes', 'nullable', 'array'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'placeholder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'default_value' => ['sometimes', 'nullable'],
            'is_required' => ['sometimes', 'boolean'],
            'is_visible' => ['sometimes', 'boolean'],
            'is_enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'column_span' => ['sometimes', 'integer', 'min:1', 'max:6'],
            'expected_version' => ['sometimes', 'integer', 'min:1'],
        ];

        $fields = Validator::make($input, $rules)->validate();
        if (!$creating && (isset($fields['field_key']) || isset($fields['entity_type']))) {
            throw ValidationException::withMessages([
                'field_key' => trans('field-studio::general.errors.immutable_identity'),
            ]);
        }

        if ($creating && isset($fields['expected_version'])) {
            throw ValidationException::withMessages(['expected_version' => trans('field-studio::general.errors.unexpected_version')]);
        }

        return $fields;
    }

    private function validateOptions(array $field): void
    {
        if (in_array($field['field_type'], ['select', 'multiselect'], true)
            && empty($field['options'])) {
            throw ValidationException::withMessages([
                'options' => trans('field-studio::general.errors.selection_requires_options'),
            ]);
        }
    }

    private function encodeDefinition(array $fields): array
    {
        foreach (['options', 'translations'] as $key) {
            if (array_key_exists($key, $fields)) {
                $fields[$key] = $fields[$key] === null ? null
                    : json_encode($fields[$key], JSON_THROW_ON_ERROR);
            }
        }
        return $fields;
    }

    public function assertType(string $type): void
    {
        if (!in_array($type, self::ENTITIES, true)) {
            throw ValidationException::withMessages(['entity_type' => trans('field-studio::general.errors.unsupported_entity')]);
        }
    }

    private function sourceQuery(int $company, string $type, int $id)
    {
        $this->assertType($type);
        if ($id < 1) {
            throw new NotFoundHttpException(trans('field-studio::general.errors.invalid_record_id'));
        }
        [$table, $subtype] = self::TARGETS[$type];
        $query = DB::table($table)->where('id', $id)
            ->where('company_id', $company)->whereNull('deleted_at');

        if ($subtype !== null) {
            $query->where('type', $subtype);
        }

        return $query;
    }

    public function requireEntity(int $company, string $type, int $id): void
    {
        if (!$this->sourceQuery($company, $type, $id)->exists()) {
            throw new NotFoundHttpException(trans('field-studio::general.errors.record_not_found_company'));
        }
    }

    public function readValues(int $company, string $type, int $id): array
    {
        $this->requireEntity($company, $type, $id);
        $definitions = $this->definitions($company, $type);
        $values = DB::table('lcc_fieldstudio_values')
            ->where('company_id', $company)->where('entity_type', $type)
            ->where('entity_id', $id)->get()->keyBy('definition_id');

        return array_map(function (array $definition) use ($values): array {
            $row = $values->get($definition['id']);
            $value = $row?->value ?? $definition['default_value'];

            if ($definition['field_type'] === 'multiselect') {
                $value = $value === null ? [] :
                    json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
            }

            $definition['value'] = $value;
            $definition['is_set'] = $row !== null;
            $definition['value_version'] = $row?->version ?? 0;
            return $definition;
        }, $definitions);
    }

    /**
     * PATCH semantics: only the submitted keys change. Explicit null
     * removes an optional value; omitted keys remain unchanged.
     */
    public function saveValues(
        int $company, string $type, int $id,
        array $values, array $versions = [], ?int $actor = null
    ): array {
        $this->assertType($type);
        if (count($values) > 100) {
            throw ValidationException::withMessages(['values' => trans('field-studio::general.errors.too_many_fields')]);
        }

        return DB::transaction(function () use ($company, $type, $id, $values, $versions, $actor): array {
            // Parent row lock serializes simultaneous first-value creations.
            if (!$this->sourceQuery($company, $type, $id)->lockForUpdate()->first(['id'])) {
                throw new NotFoundHttpException(trans('field-studio::general.errors.record_not_found_company'));
            }

            $definitions = collect($this->definitions($company, $type, true))->keyBy('field_key');
            foreach ($values as $key => $value) {
                $definition = $definitions->get($key);
                if (!$definition) {
                    throw ValidationException::withMessages([
                        "values.$key" => trans('field-studio::general.errors.unknown_field'),
                    ]);
                }

                $normalized = $this->normalize($definition, $value, $company);
                $previous = DB::table('lcc_fieldstudio_values')
                    ->where('company_id', $company)->where('entity_type', $type)
                    ->where('entity_id', $id)->where('definition_id', $definition['id'])
                    ->lockForUpdate()->first();

                $version = $previous ? (int) $previous->version : 0;
                if (array_key_exists($key, $versions) && (int) $versions[$key] !== $version) {
                    throw new ConflictHttpException(trans('field-studio::general.errors.value_concurrent', ['key' => $key]));
                }

                if (($previous && $previous->value === $normalized)
                    || (!$previous && $normalized === null)) {
                    continue; // idempotence, including clearing a missing field
                }

                if ($normalized === null) {
                    DB::table('lcc_fieldstudio_values')->where('id', $previous->id)->delete();
                    $operation = 'value.deleted';
                } elseif ($previous) {
                    DB::table('lcc_fieldstudio_values')->where('id', $previous->id)
                        ->update([
                            'value' => $normalized,
                            'version' => $version + 1,
                            'updated_at' => now(),
                        ]);
                    $operation = 'value.updated';
                } else {
                    DB::table('lcc_fieldstudio_values')->insert([
                        'company_id' => $company, 'entity_type' => $type, 'entity_id' => $id,
                        'definition_id' => $definition['id'], 'value' => $normalized,
                        'version' => 1, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $operation = 'value.created';
                }

                $this->audit(
                    $company, $actor, $operation, $type, $id,
                    (int) $definition['id'], $previous?->value, $normalized
                );
            }

            return $this->readValues($company, $type, $id);
        }, 3);
    }

    /**
     * PUT semantics: all enabled definitions are part of the representation.
     * Missing optional values are cleared; missing required values fail.
     */
    public function replaceValues(
        int $company, string $type, int $id,
        array $values, array $versions = [], ?int $actor = null
    ): array {
        return DB::transaction(function () use ($company, $type, $id, $values, $versions, $actor): array {
            $defs = collect($this->definitions($company, $type, true))->keyBy('field_key');
            foreach ($defs as $key => $definition) {
                if ($definition['is_required'] && !array_key_exists($key, $values)) {
                    throw ValidationException::withMessages([
                        "values.$key" => trans('field-studio::general.errors.required_missing'),
                    ]);
                }
                if (!array_key_exists($key, $values)) {
                    $values[$key] = null;
                }
            }
            return $this->saveValues($company, $type, $id, $values, $versions, $actor);
        });
    }

    public function normalize(array $definition, mixed $value, int $company): ?string
    {
        $type = $definition['field_type'];
        if ($value === null || $value === '' || ($type === 'multiselect' && $value === [])) {
            if (!empty($definition['is_required'])) {
                throw ValidationException::withMessages(['value' => trans('field-studio::general.errors.value_required')]);
            }
            return null;
        }

        if ($type === 'boolean') {
            if (in_array($value, [true, 1, '1', 'true'], true)) return '1';
            if (in_array($value, [false, 0, '0', 'false'], true)) return '0';
            throw ValidationException::withMessages(['value' => trans('field-studio::general.errors.boolean')]);
        }

        if ($type === 'multiselect') {
            if (!is_array($value) || count($value) > 100 || !array_is_list($value)) {
                throw ValidationException::withMessages(['value' => trans('field-studio::general.errors.multiselect_array')]);
            }
            $options = $definition['options'] ?? [];
            if (count($value) !== count(array_unique($value, SORT_REGULAR))) {
                throw ValidationException::withMessages(['value' => trans('field-studio::general.errors.multiselect_duplicate')]);
            }
            foreach ($value as $choice) {
                if (!is_string($choice) || !in_array($choice, $options, true)) {
                    throw ValidationException::withMessages(['value' => trans('field-studio::general.errors.multiselect_option')]);
                }
            }
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        if (!is_string($value) && !is_int($value)) {
            throw ValidationException::withMessages(['value' => trans('field-studio::general.errors.scalar')]);
        }
        $s = (string) $value;

        switch ($type) {
            case 'text':
            case 'textarea':
                $max = $type === 'textarea' ? 10000 : 4000;
                if (mb_strlen($s) > $max) {
                    throw ValidationException::withMessages(['value' => trans('field-studio::general.errors.text_limit', ['max' => $max])]);
                }
                break;
            case 'email':
                if (mb_strlen($s) > 254 || filter_var($s, FILTER_VALIDATE_EMAIL) === false) {
                    throw ValidationException::withMessages(['value' => trans('field-studio::general.errors.email')]);
                }
                break;
            case 'url':
                $scheme = parse_url($s, PHP_URL_SCHEME);
                if (mb_strlen($s) > 2000 || !in_array(strtolower((string) $scheme), ['http', 'https'], true)
                    || filter_var($s, FILTER_VALIDATE_URL) === false) {
                    throw ValidationException::withMessages(['value' => trans('field-studio::general.errors.url')]);
                }
                break;
            case 'integer':
                if (!preg_match('/^-?(0|[1-9][0-9]{0,17})$/D', $s)) {
                    throw ValidationException::withMessages(['value' => trans('field-studio::general.errors.integer')]);
                }
                break;
            case 'decimal':
            case 'percent':
                if (!preg_match('/^-?(?:0|[1-9][0-9]{0,15})(?:\.[0-9]{1,6})?$/D', $s)) {
                    throw ValidationException::withMessages([
                        'value' => trans('field-studio::general.errors.decimal'),
                    ]);
                }
                if ($type === 'percent'
                    && (bccomp($s, '0', 6) < 0 || bccomp($s, '100', 6) > 0)) {
                    throw ValidationException::withMessages(['value' => trans('field-studio::general.errors.percent')]);
                }
                break;
            case 'date':
                if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $s)) {
                    throw ValidationException::withMessages(['value' => trans('field-studio::general.errors.date_format')]);
                }
                [$year, $month, $day] = array_map('intval', explode('-', $s));
                if (!checkdate($month, $day, $year)) {
                    throw ValidationException::withMessages(['value' => trans('field-studio::general.errors.date_invalid')]);
                }
                break;
            case 'time':
                if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D', $s)) {
                    throw ValidationException::withMessages(['value' => trans('field-studio::general.errors.time_format')]);
                }
                break;
            case 'datetime':
                if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $s)) {
                    throw ValidationException::withMessages(['value' => trans('field-studio::general.errors.datetime_format')]);
                }
                [$date] = explode('T', $s);
                [$year, $month, $day] = array_map('intval', explode('-', $date));
                if (!checkdate($month, $day, $year)) {
                    throw ValidationException::withMessages(['value' => trans('field-studio::general.errors.datetime_invalid')]);
                }
                break;
            case 'select':
                if (!in_array($s, $definition['options'] ?? [], true)) {
                    throw ValidationException::withMessages(['value' => trans('field-studio::general.errors.select_option')]);
                }
                break;
            case 'contact':
                if (!ctype_digit($s) || (int) $s < 1
                    || !DB::table('contacts')->where('company_id', $company)
                        ->where('id', (int) $s)->whereNull('deleted_at')->exists()) {
                    throw ValidationException::withMessages(['value' => trans('field-studio::general.errors.contact_company')]);
                }
                break;
            default:
                throw ValidationException::withMessages(['value' => trans('field-studio::general.errors.unsupported_type')]);
        }

        return $s;
    }

    public function search(int $company, string $type, string $key, string $value, int $limit = 100): array
    {
        $this->assertType($type);
        $def = DB::table('lcc_fieldstudio_definitions')
            ->where('company_id', $company)->where('entity_type', $type)
            ->where('field_key', $key)->whereNull('deleted_at')->first();

        if (!$def) {
            throw new NotFoundHttpException(trans('field-studio::general.errors.definition_not_found'));
        }

        [$table, $subtype] = self::TARGETS[$type];
        $query = DB::table('lcc_fieldstudio_values as value')
            ->join($table . ' as source', function ($join): void {
                $join->on('source.id', '=', 'value.entity_id')
                    ->on('source.company_id', '=', 'value.company_id');
            })
            ->where('value.company_id', $company)->where('value.entity_type', $type)
            ->where('value.definition_id', $def->id)->where('value.value', $value)
            ->whereNull('source.deleted_at');

        if ($subtype !== null) {
            $query->where('source.type', $subtype);
        }

        return $query->orderBy('value.entity_id')
            ->limit(max(1, min($limit, 200)))->pluck('value.entity_id')->all();
    }

    public function auditList(int $company, ?string $type = null, ?int $id = null, int $limit = 100): array
    {
        $query = DB::table('lcc_fieldstudio_audits')->where('company_id', $company);
        if ($type !== null) {
            $this->assertType($type);
            $query->where('entity_type', $type);
        }
        if ($id !== null) {
            $query->where('entity_id', $id);
        }

        return $query->orderByDesc('id')
            ->limit(max(1, min($limit, 200)))->get()->toArray();
    }

    private function audit(
        int $company, ?int $actor, string $operation,
        string $type, ?int $entity, ?int $definition,
        ?string $before, ?string $after
    ): void {
        DB::table('lcc_fieldstudio_audits')->insert([
            'company_id' => $company,
            'actor_type' => $actor ? 'App\\Models\\Auth\\User' : null,
            'actor_id' => $actor,
            'operation' => $operation,
            'entity_type' => $type,
            'entity_id' => $entity,
            'definition_id' => $definition,
            'before_value' => $before,
            'after_value' => $after,
            'created_at' => now(),
        ]);
    }
}