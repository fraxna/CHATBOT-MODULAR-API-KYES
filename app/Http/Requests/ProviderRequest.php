<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $providerId = $this->route('id_provider');

        return [
            'slug' => [
                'required',
                'string',
                'min:1',
                'max:100',
                Rule::unique('ai_providers', 'slug')->ignore($providerId, 'id_provider')->whereNull('deleted_at'),
            ],
            'default_model' => ['required', 'string', 'min:1', 'max:100'],
        ];
    }
}
