<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Http\Requests;

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
        $min = (int) config('corvant.password.min_length', 8);

        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:'.$min],
        ];
    }
}
