<?php

namespace App\Http\Requests\Admin\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateForestAreaRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'area_ha' => ['required', 'numeric', 'gt:0'],
            'center_lat' => ['required', 'numeric', 'between:-90,90'],
            'center_lng' => ['required', 'numeric', 'between:-180,180'],
            'boundary' => ['nullable', 'json'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama kawasan',
            'description' => 'deskripsi',
            'area_ha' => 'luas kawasan (hektar)',
            'center_lat' => 'latitude pusat',
            'center_lng' => 'longitude pusat',
            'boundary' => 'batas kawasan (GeoJSON)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama kawasan wajib diisi.',
            'name.min' => 'Nama kawasan minimal 3 karakter.',
            'area_ha.required' => 'Luas kawasan wajib diisi.',
            'area_ha.numeric' => 'Luas kawasan harus berupa angka.',
            'area_ha.gt' => 'Luas kawasan harus lebih besar dari 0 hektar.',
            'center_lat.required' => 'Latitude pusat wajib diisi.',
            'center_lat.between' => 'Latitude pusat harus berada di antara -90 dan 90.',
            'center_lng.required' => 'Longitude pusat wajib diisi.',
            'center_lng.between' => 'Longitude pusat harus berada di antara -180 dan 180.',
            'boundary.json' => 'Format batas kawasan harus berupa JSON GeoJSON yang valid.',
        ];
    }
}
