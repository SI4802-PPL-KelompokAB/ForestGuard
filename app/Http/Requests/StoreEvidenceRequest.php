<?php

namespace App\Http\Requests;

use App\Models\Incident;
use App\Models\InvestigationUpdate;
use App\Models\Observation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class StoreEvidenceRequest extends FormRequest
{
    /**
     * Tentukan apakah pengguna diotorisasi untuk membuat request ini.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isUsable();
    }

    /**
     * Tentukan nama field berkas gambar yang digunakan ('photo' atau 'file').
     */
    public function fileField(): string
    {
        if ($this->hasFile('evidence') || $this->has('evidence')) {
            return 'evidence';
        }

        if ($this->hasFile('file') || $this->has('file')) {
            return 'file';
        }

        return 'photo';
    }

    /**
     * Dapatkan instance UploadedFile dari request.
     */
    public function uploadedFile(): ?UploadedFile
    {
        $field = $this->fileField();

        return $this->file($field);
    }

    /**
     * Aturan validasi request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $field = $this->fileField();

        return [
            $field => [
                'required',
                'file',
                'mimes:jpg,jpeg,png',
                'max:5120',
            ],
            'parent_type' => [
                'required',
                'string',
                'in:incident,observation,investigation_update',
            ],
            'parent_id' => [
                'required',
                'integer',
            ],
            'client_uuid' => [
                'nullable',
                'uuid',
            ],
            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],
        ];
    }

    /**
     * Pesan kustom untuk aturan validasi.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            'parent_type.required' => 'Jenis entitas induk wajib dipilih.',
            'parent_type.in' => 'Jenis entitas induk tidak valid.',
            'parent_id.required' => 'ID entitas induk wajib diisi.',
            'parent_id.integer' => 'ID entitas induk harus berupa angka.',
            'client_uuid.uuid' => 'Format client UUID tidak valid.',
            'latitude.numeric' => 'Koordinat latitude harus berupa angka.',
            'latitude.between' => 'Koordinat latitude harus di antara -90 dan 90.',
            'longitude.numeric' => 'Koordinat longitude harus berupa angka.',
            'longitude.between' => 'Koordinat longitude harus di antara -180 dan 180.',
        ];

        foreach (['evidence', 'photo', 'file'] as $field) {
            $messages["{$field}.required"] = 'Berkas gambar wajib diunggah.';
            $messages["{$field}.file"] = 'Berkas gambar tidak valid atau rusak';
            $messages["{$field}.uploaded"] = 'Ukuran berkas maksimal adalah 5 MB';
            $messages["{$field}.max"] = 'Ukuran berkas maksimal adalah 5 MB';
            $messages["{$field}.mimes"] = 'Format berkas harus berupa JPG, JPEG, atau PNG';
        }

        return $messages;
    }

    /**
     * Konfigurasi validator untuk mendeteksi berkas kosong (0 byte) atau gambar rusak.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $field = $this->fileField();
            $file = $this->file($field);

            if ($file && $file->isValid()) {
                $realPath = $file->getRealPath();

                // Berkas kosong (0 byte)
                if ($file->getSize() === 0 || filesize($realPath) === 0) {
                    $v->errors()->add($field, 'Berkas gambar tidak valid atau rusak');

                    return;
                }

                // Berkas tidak bisa dibaca sebagai gambar
                $imageInfo = @getimagesize($realPath);
                if ($imageInfo === false) {
                    $v->errors()->add($field, 'Berkas gambar tidak valid atau rusak');
                }
            }
        });
    }

    /**
     * Resolusi model parent berdasarkan parent_type dan parent_id.
     */
    public function resolveParent(): Incident|Observation|InvestigationUpdate
    {
        $type = $this->input('parent_type');
        $id = $this->input('parent_id');

        return match ($type) {
            'incident' => Incident::query()->findOrFail($id),
            'observation' => Observation::query()->findOrFail($id),
            'investigation_update' => InvestigationUpdate::query()->findOrFail($id),
            default => throw new InvalidArgumentException('Tipe parent tidak dikenali.'),
        };
    }
}
