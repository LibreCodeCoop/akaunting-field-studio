<?php
// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);

namespace Modules\FieldStudio\Domain;

use App\Models\Banking\Transaction;
use App\Models\Common\Contact;
use App\Models\Common\Item;
use App\Models\Document\Document;
use Illuminate\Support\Facades\View;

final class NativeHooks
{
    public function register(): void
    {
        $this->persistOnSave();
        $this->injectViews();
    }

    private function persistOnSave(): void
    {
        $save = static function ($model, string $type): void {
            $request = request();
            $values = $request->input('custom_fields');

            if (!$request->exists('custom_fields') || !is_array($values)) {
                return;
            }

            $company = (int) ($model->company_id ?? 0);
            $entityId = (int) ($model->id ?? 0);
            abort_if($company <= 0 || $entityId <= 0 || (int) company_id() !== $company, 403, 'Company mismatch');

            app(Access::class)->record($type, 'write', $entityId);
            app(Fields::class)->saveValues(
                $company, $type, $entityId, $values, [], app(Access::class)->actor()
            );
        };

        Document::saved(static function (Document $document) use ($save): void {
            if (in_array($document->type, ['invoice', 'bill'], true)) {
                $save($document, $document->type);
            }
        });
        Transaction::saved(static fn (Transaction $transaction) => $save($transaction, 'transaction'));
        Contact::saved(static fn (Contact $contact) => $save($contact, 'contact'));
        Item::saved(static fn (Item $item) => $save($item, 'item'));
    }

    private function injectViews(): void
    {
        foreach ([
            'sales.invoices.create', 'sales.invoices.edit',
            'purchases.bills.create', 'purchases.bills.edit',
        ] as $name) {
            View::composer($name, static function ($view): void {
                if (!auth()->check()) {
                    return;
                }

                $type = str_starts_with($view->name(), 'sales.') ? 'invoice' : 'bill';
                try {
                    $model = $view->getData()[$type] ?? null;
                    $id = is_object($model) ? (int) ($model->id ?? 0) : 0;
                    app(Access::class)->record($type, 'read', $id ?: null);

                    $company = app(Fields::class)->company();
                    $fields = app(Fields::class)->definitions($company, $type, true);
                    if ($fields === []) {
                        return;
                    }

                    $current = $id > 0
                        ? collect(app(Fields::class)->readValues($company, $type, $id))->keyBy('field_key')
                        : collect();

                    // Akaunting's document form exposes this named stack in its
                    // Advanced section. v-model is required by its Vue Form class.
                    View::startPush(
                        'category_start',
                        view('field-studio::native-inputs', compact('fields', 'current'))->render()
                    );
                } catch (\Throwable $exception) {
                    // An unconfigured extension must not prevent normal access
                    // to invoices. Record the failure for diagnosis instead.
                    report($exception);
                }
            });
        }

        foreach ([
            'sales.invoices.show' => 'invoice',
            'purchases.bills.show' => 'bill',
            'banking.transactions.show' => 'transaction',
            'banking.transactions.edit' => 'transaction',
            'sales.customers.show' => 'contact',
            'sales.customers.edit' => 'contact',
            'purchases.vendors.show' => 'contact',
            'purchases.vendors.edit' => 'contact',
            'common.items.edit' => 'item',
        ] as $name => $type) {
            View::composer($name, static function ($view) use ($type): void {
                if (!auth()->check()) {
                    return;
                }

                try {
                    $data = $view->getData();
                    $model = match ($type) {
                        'contact' => $data['customer'] ?? $data['vendor'] ?? null,
                        'item' => $data['item'] ?? null,
                        'transaction' => $data['transaction'] ?? null,
                        default => $data[$type] ?? $data['document'] ?? null,
                    };
                    $id = is_object($model) ? (int) ($model->id ?? 0) : 0;
                    if ($id <= 0) {
                        return;
                    }

                    app(Access::class)->record($type, 'read', $id);
                    $url = route('field-studio.records.edit', [
                        'entity_type' => $type, 'entity_id' => $id,
                    ]);

                    $html = '<div class="my-4 px-4">'
                        . '<a class="inline-flex items-center text-sm text-purple hover:underline" href="' . e($url) . '">'
                        . '<span class="material-icons-outlined text-base mr-2" aria-hidden="true">tune</span>'
                        . 'Informações adicionais (Field Studio)</a></div>';
                    View::startPush('body_end', $html);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });
        }
    }
}