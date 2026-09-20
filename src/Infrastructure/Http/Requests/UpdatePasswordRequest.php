<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Http\Requests;

use Corvant\Infrastructure\Support\CorvantPasswordRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', CorvantPasswordRule::make()],
        ];
    }
}
