<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Support;

use Illuminate\Validation\Rules\Password;

final class CorvantPasswordRule
{
    public static function make(): Password
    {
        $rule = Password::min((int) config('corvant.password.min_length', 8));

        if ((bool) config('corvant.password.require_mixed_case', true)) {
            $rule->mixedCase();
        }

        if ((bool) config('corvant.password.require_numbers', true)) {
            $rule->numbers();
        }

        if ((bool) config('corvant.password.require_symbols', true)) {
            $rule->symbols();
        }

        return $rule;
    }
}
