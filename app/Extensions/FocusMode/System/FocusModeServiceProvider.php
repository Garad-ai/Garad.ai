<?php

declare(strict_types=1);

namespace App\Extensions\FocusMode\System;

use App\Extensions\FocusMode\System\View\Components\DropDown;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;

class FocusModeServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(Kernel $kernel): void
    {
        $this->registerTranslations()
            ->registerViews()
            ->registerComponents();

    }

    public function registerComponents(): void
    {
        $this->loadViewComponentsAs('focus-mode', [
            DropDown::class,
        ]);
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'focus-mode');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'focus-mode');

        return $this;
    }
}
