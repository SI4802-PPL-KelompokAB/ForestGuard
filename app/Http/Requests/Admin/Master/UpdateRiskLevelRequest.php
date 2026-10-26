<?php

namespace App\Http\Requests\Admin\Master;

use App\Models\RiskLevel;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRiskLevelRequest extends FormRequest
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
        /** @var RiskLevel|null $riskLevel */
        $riskLevel = $this->route('risk_level');
        $riskLevelId = $riskLevel?->id;

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                Rule::unique('risk_levels', 'name')->ignore($riskLevelId),
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

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var RiskLevel|null $riskLevel */
                $riskLevel = $this->route('risk_level');

                if (! $this->boolean('is_active') && $riskLevel instanceof RiskLevel && $riskLevel->is_active) {
                    $otherActiveCount = RiskLevel::query()
                        ->where('is_active', true)
                        ->where('id', '!=', $riskLevel->id)
                        ->count();

                    if ($otherActiveCount === 0) {
                        $validator->errors()->add('is_active', 'Tidak dapat menonaktifkan tingkat risiko ini karena minimal harus ada satu tingkat risiko yang aktif.');
                    }
                }
            },
        ];
    }
}
