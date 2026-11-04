<?php
// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\FieldStudio\Http\Controllers\AdminController;

Route::admin('field-studio', function (): void {
    Route::get('/', [AdminController::class, 'index'])->name('definitions.index');
    Route::get('new', [AdminController::class, 'create'])->name('definitions.create');
    Route::post('definitions', [AdminController::class, 'store'])->name('definitions.store');
    Route::get('definitions/{definition}/edit', [AdminController::class, 'edit'])->name('definitions.edit');
    Route::put('definitions/{definition}', [AdminController::class, 'update'])->name('definitions.update');
    Route::delete('definitions/{definition}', [AdminController::class, 'destroy'])->name('definitions.destroy');
    Route::get('records/{entity_type}/{entity_id}', [AdminController::class, 'record'])->name('records.edit');
    Route::put('records/{entity_type}/{entity_id}', [AdminController::class, 'saveRecord'])->name('records.update');
});