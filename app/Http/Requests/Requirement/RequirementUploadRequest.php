<?php

namespace App\Http\Requests\Requirement;

use Illuminate\Foundation\Http\FormRequest;

class RequirementUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('client')->check();
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:zip,rar,pdf,jpg,jpeg,png,webp', 'max:5120'],
            'document_requirement_id' => ['nullable', 'integer', 'exists:document_requirements,id'],
        ];
    }
}