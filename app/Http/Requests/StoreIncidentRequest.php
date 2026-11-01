<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isUsable();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'uuid'],
            'incident_type_id' => ['required', 'integer', 'exists:incident_types,id,deleted_at,NULL'],
            'forest_area_id' => ['required', 'integer', 'exists:forest_areas,id,deleted_at,NULL'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'description' => ['required', 'string', 'min:15', 'max:2000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'gps_accuracy' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'patrol_id' => ['nullable', 'integer', 'exists:patrols,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_uuid.required' => 'Client UUID wajib disertakan.',
            'client_uuid.uuid' => 'Format Client UUID tidak valid.',
            'incident_type_id.required' => 'Kategori insiden wajib dipilih.',
            'incident_type_id.exists' => 'Kategori insiden tidak valid.',
            'forest_area_id.required' => 'Kawasan hutan wajib dipilih.',
            'forest_area_id.exists' => 'Kawasan hutan tidak valid.',
            'occurred_at.required' => 'Waktu kejadian wajib diisi.',
            'occurred_at.before_or_equal' => 'Waktu kejadian tidak boleh melebihi waktu sekarang.',
            'description.required' => 'Deskripsi laporan wajib diisi.',
            'description.min' => 'Uraian kejadian minimal harus 15 karakter.',
            'description.max' => 'Deskripsi laporan maksimal 2000 karakter.',
            'latitude.required' => 'Titik koordinat lokasi kejadian wajib ditentukan.',
            'longitude.required' => 'Titik koordinat lokasi kejadian wajib ditentukan.',
            'latitude.between' => 'Format koordinat lintang/bujur tidak valid.',
            'longitude.between' => 'Format koordinat lintang/bujur tidak valid.',
            'photo.required' => 'File foto bukti wajib dilampirkan.',
            'photo.image' => 'File bukti harus berupa format gambar (jpeg, png, jpg).',
            'photo.mimes' => 'File bukti harus berupa format gambar (jpeg, png, jpg).',
            'photo.max' => 'Ukuran file foto bukti maksimal 5MB.',
        ];
    }
}
