<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UseRecoveryCodeRequest extends FormRequest
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
            'challenge_token' => ['required', 'string'],
            'code' => ['required', 'string'],
        ];
    }
}
