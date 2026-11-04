<?php
// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);

namespace Modules\FieldStudio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\FieldStudio\Domain\Access;
use Modules\FieldStudio\Domain\Fields;

final class ApiController extends Controller
{
    public function __construct(
        private readonly Fields $fields,
        private readonly Access $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->access->definitions('read');
        return response()->json([
            'data' => $this->fields->definitions($this->fields->company(), $request->query('entity_type')),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->access->definitions('write');
        $record = $this->fields->createDefinition(
            $this->fields->company(), $request->all(), $this->access->actor()
        );

        return response()->json(['data' => $record], 201);
    }

    public function show(int $definition): JsonResponse
    {
        $this->access->definitions('read');
        return response()->json(['data' => $this->fields->definition($this->fields->company(), $definition)]);
    }

    public function update(Request $request, int $definition): JsonResponse
    {
        $this->access->definitions('write');
        return response()->json([
            'data' => $this->fields->updateDefinition(
                $this->fields->company(), $definition, $request->all(), $this->access->actor()
            ),
        ]);
    }

    public function destroy(int $definition): JsonResponse
    {
        $this->access->definitions('write');
        $this->fields->deleteDefinition($this->fields->company(), $definition, $this->access->actor());
        return response()->json(null, 204);
    }

    public function values(string $entity_type, int $entity_id): JsonResponse
    {
        $this->access->record($entity_type, 'read', $entity_id);
        return response()->json([
            'data' => $this->fields->readValues($this->fields->company(), $entity_type, $entity_id),
        ]);
    }

    public function saveValues(Request $request, string $entity_type, int $entity_id): JsonResponse
    {
        $this->access->record($entity_type, 'write', $entity_id);
        $data = $this->validateValues($request);
        return response()->json([
            'data' => $this->fields->saveValues(
                $this->fields->company(), $entity_type, $entity_id,
                $data['values'], $data['versions'] ?? [], $this->access->actor()
            ),
        ]);
    }

    public function replaceValues(Request $request, string $entity_type, int $entity_id): JsonResponse
    {
        $this->access->record($entity_type, 'write', $entity_id);
        $data = $this->validateValues($request);
        return response()->json([
            'data' => $this->fields->replaceValues(
                $this->fields->company(), $entity_type, $entity_id,
                $data['values'], $data['versions'] ?? [], $this->access->actor()
            ),
        ]);
    }

    public function removeValue(string $entity_type, int $entity_id, string $field_key): JsonResponse
    {
        $this->access->record($entity_type, 'write', $entity_id);
        return response()->json([
            'data' => $this->fields->saveValues(
                $this->fields->company(), $entity_type, $entity_id,
                [$field_key => null], [], $this->access->actor()
            ),
        ]);
    }

    public function searchRecords(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'entity_type' => 'required|string',
            'field_key' => 'required|string',
            'value' => 'required|string|max:10000',
            'limit' => 'sometimes|integer|min:1|max:200',
        ])->validate();
        $type = $validated['entity_type'];
        $this->access->record($type, 'read');

        $ids = $this->fields->search(
            $this->fields->company(), $type,
            $validated['field_key'], $validated['value'],
            (int) ($validated['limit'] ?? 100)
        );

        return response()->json([
            'data' => array_values(array_filter(
                $ids, fn ($id): bool => $this->access->canReadRecord($type, (int) $id)
            )),
        ]);
    }

    public function audits(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'entity_type' => 'sometimes|required|string',
            'entity_id' => 'sometimes|required|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:200',
        ])->validate();

        if (isset($validated['entity_type'], $validated['entity_id'])) {
            $this->access->record($validated['entity_type'], 'read', (int) $validated['entity_id']);
            $this->fields->requireEntity(
                $this->fields->company(), $validated['entity_type'], (int) $validated['entity_id']
            );
        } else {
            // Cross-record audit may expose vendor/customer data. Reserve it
            // for company settings administrators, never a generic invoice reader.
            $this->access->definitions('read');
        }

        return response()->json([
            'data' => $this->fields->auditList(
                $this->fields->company(),
                $validated['entity_type'] ?? null,
                isset($validated['entity_id']) ? (int) $validated['entity_id'] : null,
                (int) ($validated['limit'] ?? 100),
            ),
        ]);
    }

    private function validateValues(Request $request): array
    {
        return Validator::make($request->all(), [
            'values' => ['required', 'array', 'max:100'],
            'versions' => ['sometimes', 'array'],
            'versions.*' => ['integer', 'min:0'],
        ])->validate();
    }
}