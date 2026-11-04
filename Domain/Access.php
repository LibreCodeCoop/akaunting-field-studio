<?php
// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);

namespace Modules\FieldStudio\Domain;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Reuse Akaunting's existing ACL. The parent record determines access,
 * especially where one Field Studio entity spans customers/vendors or
 * invoice/bill line items.
 */
final class Access
{
    public function actor(): ?int
    {
        return auth()->id() ? (int) auth()->id() : null;
    }

    public function definitions(string $action): void
    {
        $permission = $action === 'read' ? 'read-settings-company' : 'update-settings-company';
        abort_unless(auth()->check() && auth()->user()->can($permission), 403, trans('field-studio::general.errors.access_definitions'));
    }

    public function record(string $type, string $action, ?int $entityId = null): void
    {
        abort_unless(auth()->check(), 401, trans('field-studio::general.errors.auth_required'));
        $permissions = match ($type) {
            'invoice' => ['sales-invoices'],
            'bill' => ['purchases-bills'],
            'transaction' => ['banking-transactions'],
            'item' => ['common-items'],
            'contact' => $this->contactResource($entityId),
            'document_item' => $this->documentItemResource($entityId),
            default => [],
        };

        abort_if($permissions === [], 422, trans('field-studio::general.errors.unsupported_entity'));

        $prefix = $action === 'read' ? 'read-' : 'update-';
        foreach ($permissions as $suffix) {
            if (auth()->user()->can($prefix . $suffix)) {
                return;
            }
        }

        abort(403, trans('field-studio::general.errors.access_record'));
    }

    public function canReadRecord(string $type, int $entityId): bool
    {
        try {
            $this->record($type, 'read', $entityId);
            return true;
        } catch (HttpExceptionInterface) {
            return false;
        }
    }

    private function contactResource(?int $id): array
    {
        if ($id === null) {
            return ['sales-customers', 'purchases-vendors'];
        }
        $record = DB::table('contacts')
            ->where('company_id', app(Fields::class)->company())
            ->where('id', $id)->whereNull('deleted_at')->first(['type']);
        if (!$record) {
            throw new NotFoundHttpException(trans('field-studio::general.errors.contact_not_found'));
        }
        return match ($record->type) {
            'customer' => ['sales-customers'],
            'vendor' => ['purchases-vendors'],
            default => [],
        };
    }

    private function documentItemResource(?int $id): array
    {
        if ($id === null) {
            return ['sales-invoices', 'purchases-bills'];
        }
        $record = DB::table('document_items as line')
            ->join('documents as parent', function ($join): void {
                $join->on('parent.id', '=', 'line.document_id')
                    ->on('parent.company_id', '=', 'line.company_id');
            })
            ->where('line.company_id', app(Fields::class)->company())
            ->where('line.id', $id)->whereNull('line.deleted_at')
            ->whereNull('parent.deleted_at')
            ->first(['parent.type']);
        if (!$record) {
            throw new NotFoundHttpException(trans('field-studio::general.errors.line_not_found'));
        }
        return match ($record->type) {
            'invoice' => ['sales-invoices'],
            'bill' => ['purchases-bills'],
            default => [],
        };
    }
}