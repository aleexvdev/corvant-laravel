<?php

declare(strict_types=1);

namespace Corvant\Infrastructure;

use Illuminate\Support\ServiceProvider;

// Future port-to-adapter bindings ($this->app->bind(Port::class, Adapter::class)) are registered here.
class CorvantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
    }
}
