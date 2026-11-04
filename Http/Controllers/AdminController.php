<?php
// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);

namespace Modules\FieldStudio\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\FieldStudio\Domain\Access;
use Modules\FieldStudio\Domain\Fields;

final class AdminController extends Controller
{
    public function __construct(
        private readonly Fields $fields,
        private readonly Access $access,
    ) {}

    public function index(Request $request): View
    {
        $this->access->definitions('read');
        $entity = $request->query('entity_type');

        return view('field-studio::index', [
            'definitions' => $this->fields->definitions(
                $this->fields->company(), is_string($entity) && $entity !== '' ? $entity : null
            ),
            'entityLabels' => trans('field-studio::general.entities'),
            'typeLabels' => trans('field-studio::general.types'),
        ]);
    }

    public function create(): View
    {
        $this->access->definitions('write');
        return view('field-studio::definition', [
            'field' => null, 'entityLabels' => trans('field-studio::general.entities'), 'typeLabels' => trans('field-studio::general.types'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->access->definitions('write');
        $this->fields->createDefinition(
            $this->fields->company(), $this->normalizeForm($request), $this->access->actor()
        );

        return redirect()->route('field-studio.definitions.index')->with('success', trans('field-studio::general.messages.created'));
    }

    public function edit(int $definition): View
    {
        $this->access->definitions('write');
        return view('field-studio::definition', [
            'field' => $this->fields->definition($this->fields->company(), $definition),
            'entityLabels' => trans('field-studio::general.entities'), 'typeLabels' => trans('field-studio::general.types'),
        ]);
    }

    public function update(Request $request, int $definition): RedirectResponse
    {
        $this->access->definitions('write');
        $this->fields->updateDefinition(
            $this->fields->company(), $definition,
            $this->normalizeForm($request, false), $this->access->actor()
        );

        return redirect()->route('field-studio.definitions.index')->with('success', trans('field-studio::general.messages.updated'));
    }

    public function destroy(int $definition): RedirectResponse
    {
        $this->access->definitions('write');
        $this->fields->deleteDefinition($this->fields->company(), $definition, $this->access->actor());
        return redirect()->route('field-studio.definitions.index')
            ->with('success', trans('field-studio::general.messages.archived'));
    }

    public function record(string $entity_type, int $entity_id): View
    {
        $this->access->record($entity_type, 'read', $entity_id);
        return view('field-studio::record', [
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'entityLabels' => trans('field-studio::general.entities'),
            'fields' => $this->fields->readValues($this->fields->company(), $entity_type, $entity_id),
        ]);
    }

    public function saveRecord(Request $request, string $entity_type, int $entity_id): RedirectResponse
    {
        $this->access->record($entity_type, 'write', $entity_id);
        $this->fields->saveValues(
            $this->fields->company(), $entity_type, $entity_id,
            $request->input('values', []),
            $request->input('versions', []),
            $this->access->actor()
        );

        return redirect()->route('field-studio.records.edit', [
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
        ])->with('success', trans('field-studio::general.messages.values_saved'));
    }

    private function normalizeForm(Request $request, bool $creating = true): array
    {
        $input = $request->only([
            'entity_type', 'field_key', 'label', 'field_type',
            'description', 'placeholder', 'sort_order', 'column_span',
            'default_value', 'is_enabled', 'is_visible', 'is_required', 'expected_version',
        ]);

        if (!$creating) {
            unset($input['entity_type'], $input['field_key']);
        }

        foreach (['is_enabled', 'is_visible', 'is_required'] as $key) {
            $input[$key] = $request->boolean($key);
        }

        $type = $request->input('field_type');
        if ($type === 'multiselect' && is_string($input['default_value'] ?? null)
            && trim($input['default_value']) !== '') {
            $input['default_value'] = array_values(array_filter(
                array_map('trim', explode(',', $input['default_value'])),
                static fn (string $item): bool => $item !== ''
            ));
        }

        if ($request->has('options_text')) {
            $options = array_map(
                'trim',
                preg_split('/\r\n|\r|\n/', (string) $request->input('options_text')) ?: []
            );
            $input['options'] = array_values(array_filter(
                $options, static fn (string $item): bool => $item !== ''
            ));
        }

        return $input;
    }
}