<?php

declare(strict_types=1);

use Corvant\Tests\Feature\Rbac\RbacTestCase;
use Corvant\Tests\TestCase;

uses(TestCase::class)->in('Feature/Auth', 'Feature/Tenancy', 'Feature/Users', 'Feature/Mfa', 'Feature/Sessions', 'Feature/Audit');
uses(RbacTestCase::class)->in('Feature/Rbac');
uses(PHPUnit\Framework\TestCase::class)->in('Unit');
uses(TestCase::class)->in('Unit/Adapters');

afterEach(function (): void {
    if (class_exists(Mockery::class)) {
        Mockery::close();
    }
});
