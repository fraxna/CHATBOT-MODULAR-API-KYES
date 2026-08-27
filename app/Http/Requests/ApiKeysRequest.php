<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApiKeysRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->route('id_provider')) {
            $this->merge([
                'id_provider' => $this->route('id_provider'),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'id_provider' => ['required', 'integer', Rule::exists('ai_providers', 'id_provider')],
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'encrypted_key' => [$isUpdate ? 'nullable' : 'required', 'string'],
            'priority' => ['required', 'integer', 'min:0'],
            'rate_limit' => ['required', 'integer', 'min:1'],
        ];
    }
}
