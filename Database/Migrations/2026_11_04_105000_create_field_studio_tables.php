<?php
// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lcc_fieldstudio_definitions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('company_id');
            $table->string('entity_type', 32);
            $table->string('field_key', 72);
            $table->string('label', 180);
            $table->string('field_type', 24);
            $table->json('options')->nullable();
            $table->json('translations')->nullable();
            $table->string('placeholder')->nullable();
            $table->text('description')->nullable();
            $table->text('default_value')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('column_span')->default(6);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'entity_type', 'field_key'], 'lcc_fs_definition_key_unique');
            $table->index(['company_id', 'entity_type', 'is_enabled'], 'lcc_fs_def_scope');
        });
        Schema::create('lcc_fieldstudio_values', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('company_id');
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('definition_id');
            $table->longText('value')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['company_id', 'entity_type', 'entity_id', 'definition_id'], 'lcc_fs_value_unique');
            $table->index(['company_id', 'entity_type', 'entity_id'], 'lcc_fs_entity_scope');
            $table->index(['company_id', 'definition_id'], 'lcc_fs_lookup');
        });
        Schema::create('lcc_fieldstudio_audits', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('company_id');
            $table->nullableMorphs('actor');
            $table->string('operation', 40);
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('definition_id')->nullable();
            $table->longText('before_value')->nullable();
            $table->longText('after_value')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'entity_type', 'entity_id'], 'lcc_fs_audit_entity');
        });
    }

    public function down(): void
    {
        // Do not destroy financial metadata or audit history through an automatic rollback.
        // Explicit export and approved purge are required before manual removal.
    }
};