<?php

namespace App\Http\Requests\Ranger;

use App\Enums\ObservationCategory;
use App\Http\Requests\StoreEvidenceRequest;
use Illuminate\Validation\Rule;

class StoreObservationRequest extends StoreEvidenceRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize() || ! $this->user()->isRanger()) {
            return false;
        }

        $this->user()->patrols()->findOrFail($this->route('patrol'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('description'))) {
            $this->merge(['description' => trim($this->input('description'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $photoRules = parent::rules()['photo'];
        $photoRules[0] = 'nullable';

        return [
            'client_uuid' => ['required', 'uuid'],
            'category' => ['required', Rule::enum(ObservationCategory::class)],
            'description' => ['required', 'string', 'min:5'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_recorded_at' => ['required', 'date', 'before_or_equal:now'],
            'photo' => $photoRules,
        ];
    }

    public function fileField(): string
    {
        return 'photo';
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'description.required' => 'Catatan observasi wajib diisi.',
            'description.min' => 'Catatan observasi minimal 5 karakter setelah trim.',
            'category.required' => 'Kategori observasi wajib dipilih.',
            'category.Illuminate\\Validation\\Rules\\Enum' => 'Kategori observasi tidak valid.',
            'latitude.required' => 'Lokasi kejadian belum ditentukan. Ambil lokasi GPS atau pilih titik pada peta.',
            'latitude.numeric' => 'Lokasi kejadian belum ditentukan. Ambil lokasi GPS atau pilih titik pada peta.',
            'latitude.between' => 'Lokasi kejadian belum ditentukan. Ambil lokasi GPS atau pilih titik pada peta.',
            'longitude.required' => 'Lokasi kejadian belum ditentukan. Ambil lokasi GPS atau pilih titik pada peta.',
            'longitude.numeric' => 'Lokasi kejadian belum ditentukan. Ambil lokasi GPS atau pilih titik pada peta.',
            'longitude.between' => 'Lokasi kejadian belum ditentukan. Ambil lokasi GPS atau pilih titik pada peta.',
            'location_recorded_at.required' => 'Waktu pengambilan lokasi GPS wajib disertakan.',
            'location_recorded_at.before_or_equal' => 'Waktu pengambilan lokasi GPS tidak boleh di masa depan.',
        ]);
    }
}
