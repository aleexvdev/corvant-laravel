<?php

declare(strict_types=1);

namespace Corvant\Tests\Feature\Rbac;

use Corvant\Tests\TestCase;
use Illuminate\Testing\TestResponse;

/**
 * @method TestResponse getJson(string $uri, array $headers = [], int $options = 0)
 * @method TestResponse postJson(string $uri, array $data = [], array $headers = [], int $options = 0)
 * @method TestResponse putJson(string $uri, array $data = [], array $headers = [], int $options = 0)
 * @method TestResponse deleteJson(string $uri, array $data = [], array $headers = [], int $options = 0)
 */
abstract class RbacTestCase extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->get('_test/rbac-protected', fn () => response()->json(['authorized' => true]))
            ->middleware(['corvant.authenticate', 'corvant.resolve-tenant', 'permission:invoice:create']);
    }
}
