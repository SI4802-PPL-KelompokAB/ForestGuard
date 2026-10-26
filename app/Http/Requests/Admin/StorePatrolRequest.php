<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePatrolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ranger_id' => ['required', 'integer'],
            'forest_area_id' => ['required', 'integer'],
            'scheduled_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'checkpoint_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'checkpoint_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['ranger_id' => 'Ranger', 'forest_area_id' => 'area hutan', 'scheduled_at' => 'jadwal', 'checkpoint_ids' => 'checkpoint'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['checkpoint_ids.required' => 'Pilih minimal satu checkpoint.', 'checkpoint_ids.min' => 'Pilih minimal satu checkpoint.', 'checkpoint_ids.*.distinct' => 'Checkpoint tidak boleh dipilih lebih dari sekali.'];
    }
}
