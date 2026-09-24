<?php

namespace App\Http\Requests\Domain;

use Illuminate\Foundation\Http\FormRequest;

class DomainSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'domain' => ['nullable', 'string', 'max:255'],
            'extensions' => ['sometimes', 'array', 'max:20'],
            'extensions.*' => ['string', 'max:63'],
        ];
    }
}