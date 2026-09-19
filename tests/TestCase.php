<?php

declare(strict_types=1);

namespace Corvant\Tests;

use Corvant\Infrastructure\CorvantServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            CorvantServiceProvider::class,
        ];
    }
}
