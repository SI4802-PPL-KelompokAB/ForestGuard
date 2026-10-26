<?php

namespace App\Http\Requests\Admin\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRiskLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                Rule::unique('risk_levels', 'name'),
            ],
            'sort_order' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama tingkat risiko',
            'sort_order' => 'bobot / urutan prioritas',
            'is_active' => 'status aktif',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama tingkat risiko wajib diisi.',
            'name.unique' => 'Nama tingkat risiko sudah digunakan.',
            'sort_order.required' => 'Bobot / urutan prioritas wajib diisi.',
            'sort_order.min' => 'Bobot / urutan prioritas minimal 1.',
        ];
    }
}
