<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IncidentReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string', 'max:100'],
            'incident_type_id' => ['nullable', 'integer', 'exists:incident_types,id'],
            'forest_area_id' => ['nullable', 'integer', 'exists:forest_areas,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_from.date_format' => 'Tanggal awal harus menggunakan format YYYY-MM-DD.',
            'date_to.date_format' => 'Tanggal akhir harus menggunakan format YYYY-MM-DD.',
            'date_to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            'incident_type_id.exists' => 'Jenis insiden tidak valid.',
            'forest_area_id.exists' => 'Kawasan hutan tidak valid.',
        ];
    }
}
