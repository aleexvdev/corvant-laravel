<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePhoneRequest extends FormRequest
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
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9]{7,15}$/'],
        ];
    }
}
