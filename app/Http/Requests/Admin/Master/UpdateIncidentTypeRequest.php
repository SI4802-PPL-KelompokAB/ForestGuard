<?php

namespace App\Http\Requests\Admin\Master;

use App\Models\IncidentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncidentTypeRequest extends FormRequest
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
        /** @var IncidentType|null $incidentType */
        $incidentType = $this->route('incident_type');
        $incidentTypeId = $incidentType?->id;

        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:255',
                Rule::unique('incident_types', 'name')->ignore($incidentTypeId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama tipe insiden',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama tipe insiden wajib diisi.',
            'name.min' => 'Nama tipe insiden minimal 3 karakter.',
            'name.unique' => 'Nama tipe insiden sudah digunakan.',
        ];
    }
}
