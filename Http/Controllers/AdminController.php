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
    private const ENTITIES = [
        'invoice' => 'Faturas de venda',
        'bill' => 'Contas a pagar',
        'transaction' => 'Recebimentos e pagamentos',
        'contact' => 'Clientes e fornecedores',
        'item' => 'Produtos e serviços',
        'document_item' => 'Itens de documentos',
    ];

    private const TYPES = [
        'text' => 'Texto curto',
        'textarea' => 'Texto longo',
        'email' => 'E-mail',
        'url' => 'Link (URL)',
        'integer' => 'Número inteiro',
        'decimal' => 'Número decimal',
        'percent' => 'Percentual',
        'date' => 'Data',
        'time' => 'Horário',
        'datetime' => 'Data e horário',
        'boolean' => 'Sim / Não',
        'select' => 'Seleção única',
        'multiselect' => 'Seleção múltipla',
        'contact' => 'Referência a contato',
    ];

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
            'entityLabels' => self::ENTITIES,
            'typeLabels' => self::TYPES,
        ]);
    }

    public function create(): View
    {
        $this->access->definitions('write');
        return view('field-studio::definition', [
            'field' => null, 'entityLabels' => self::ENTITIES, 'typeLabels' => self::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->access->definitions('write');
        $this->fields->createDefinition(
            $this->fields->company(), $this->normalizeForm($request), $this->access->actor()
        );

        return redirect()->route('field-studio.definitions.index')->with('success', 'Campo criado com sucesso.');
    }

    public function edit(int $definition): View
    {
        $this->access->definitions('write');
        return view('field-studio::definition', [
            'field' => $this->fields->definition($this->fields->company(), $definition),
            'entityLabels' => self::ENTITIES, 'typeLabels' => self::TYPES,
        ]);
    }

    public function update(Request $request, int $definition): RedirectResponse
    {
        $this->access->definitions('write');
        $this->fields->updateDefinition(
            $this->fields->company(), $definition,
            $this->normalizeForm($request, false), $this->access->actor()
        );

        return redirect()->route('field-studio.definitions.index')->with('success', 'Campo atualizado com sucesso.');
    }

    public function destroy(int $definition): RedirectResponse
    {
        $this->access->definitions('write');
        $this->fields->deleteDefinition($this->fields->company(), $definition, $this->access->actor());
        return redirect()->route('field-studio.definitions.index')
            ->with('success', 'Campo arquivado. Os dados históricos foram preservados.');
    }

    public function record(string $entity_type, int $entity_id): View
    {
        $this->access->record($entity_type, 'read', $entity_id);
        return view('field-studio::record', [
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'entityLabels' => self::ENTITIES,
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
        ])->with('success', 'Informações adicionais salvas com sucesso.');
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