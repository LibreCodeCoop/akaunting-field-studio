<?php
// SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);

namespace Modules\FieldStudio\Providers;

use App\Events\Menu\SettingsCreated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\FieldStudio\Domain\NativeHooks;

final class Main extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/admin.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'field-studio');
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'field-studio');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        Event::listen(SettingsCreated::class, static function (SettingsCreated $event): void {
            if (!auth()->check() || !auth()->user()->can('read-settings-company')) {
                return;
            }

            $event->menu->route(
                'field-studio.definitions.index',
                trans('field-studio::general.name'),
                [],
                190,
                [
                    'icon' => 'tune',
                    'search_keywords' => trans('field-studio::general.description'),
                ],
            );
        });

        app(NativeHooks::class)->register();
    }
}