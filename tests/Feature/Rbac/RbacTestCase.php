<?php

declare(strict_types=1);

namespace Corvant\Tests\Feature\Rbac;

use Corvant\Tests\TestCase;

abstract class RbacTestCase extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->get('_test/rbac-protected', fn () => response()->json(['authorized' => true]))
            ->middleware(['corvant.authenticate', 'corvant.resolve-tenant', 'permission:invoice:create']);
    }
}
