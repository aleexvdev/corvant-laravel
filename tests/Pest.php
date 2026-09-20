<?php

declare(strict_types=1);

use Corvant\Tests\Feature\Rbac\RbacTestCase;
use Corvant\Tests\TestCase;

uses(TestCase::class)->in('Feature/Auth', 'Feature/Tenancy', 'Feature/Users');
uses(RbacTestCase::class)->in('Feature/Rbac');
uses(PHPUnit\Framework\TestCase::class)->in('Unit');

afterEach(function (): void {
    if (class_exists(Mockery::class)) {
        Mockery::close();
    }
});
