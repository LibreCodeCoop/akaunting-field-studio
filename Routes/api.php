<?php
// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\FieldStudio\Http\Controllers\ApiController;

// Expose one consistent company-aware API. Keep /api/v1 distinct from the
// Akaunting admin Route::admin helper and avoid its CSRF-protected web routes.
Route::group([
    'prefix' => trim((string) config('api.prefix', 'api'), '/') . '/v1/field-studio',
    'middleware' => config('api.middleware', ['api']),
    'as' => 'api.field-studio.',
], static function (): void {
    Route::get('definitions', [ApiController::class, 'index'])->name('definitions.index');
    Route::post('definitions', [ApiController::class, 'store'])->name('definitions.store');
    Route::get('definitions/{definition}', [ApiController::class, 'show'])->name('definitions.show');
    Route::match(['put', 'patch'], 'definitions/{definition}', [ApiController::class, 'update'])->name('definitions.update');
    Route::delete('definitions/{definition}', [ApiController::class, 'destroy'])->name('definitions.destroy');

    Route::get('records', [ApiController::class, 'searchRecords'])->name('records.search');
    Route::get('records/{entity_type}/{entity_id}/values', [ApiController::class, 'values'])->name('values.show');
    Route::post('records/{entity_type}/{entity_id}/values', [ApiController::class, 'saveValues'])->name('values.create');
    Route::patch('records/{entity_type}/{entity_id}/values', [ApiController::class, 'saveValues'])->name('values.patch');
    Route::put('records/{entity_type}/{entity_id}/values', [ApiController::class, 'replaceValues'])->name('values.replace');
    Route::delete('records/{entity_type}/{entity_id}/values/{field_key}', [ApiController::class, 'removeValue'])->name('values.delete');
    Route::get('audit', [ApiController::class, 'audits'])->name('audit.index');
});