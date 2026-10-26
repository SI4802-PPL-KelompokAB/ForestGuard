<?php

namespace App\Http\Requests\Admin\Master;

use App\Models\Checkpoint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCheckpointRequest extends FormRequest
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
        /** @var Checkpoint|null $checkpoint */
        $checkpoint = $this->route('checkpoint');
        $checkpointId = $checkpoint?->id;

        return [
            'forest_area_id' => [
                'required',
                'integer',
                Rule::exists('forest_areas', 'id')->whereNull('deleted_at'),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('checkpoints', 'code')->ignore($checkpointId),
            ],
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'forest_area_id' => 'kawasan hutan',
            'code' => 'kode checkpoint',
            'name' => 'nama checkpoint',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'forest_area_id.required' => 'Kawasan hutan wajib dipilih.',
            'forest_area_id.exists' => 'Kawasan hutan yang dipilih tidak valid atau sudah dihapus.',
            'code.required' => 'Kode checkpoint wajib diisi.',
            'code.unique' => 'Kode checkpoint sudah digunakan.',
            'name.required' => 'Nama checkpoint wajib diisi.',
            'name.min' => 'Nama checkpoint minimal 3 karakter.',
            'latitude.required' => 'Latitude wajib diisi.',
            'latitude.between' => 'Latitude harus berada di antara -90 dan 90.',
            'longitude.required' => 'Longitude wajib diisi.',
            'longitude.between' => 'Longitude harus berada di antara -180 dan 180.',
        ];
    }
}
