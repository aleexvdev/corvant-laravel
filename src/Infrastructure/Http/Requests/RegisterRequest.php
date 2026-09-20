<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Http\Requests;

use Corvant\Infrastructure\Support\CorvantPasswordRule;
use Illuminate\Foundation\Http\FormRequest;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', CorvantPasswordRule::make()],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
