<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class ProcessedImage
{
    /**
     * @param  string  $tempPath  Path ke berkas sementara hasil kompresi
     * @param  string  $mimeType  Tipe MIME berkas gambar ('image/jpeg' atau 'image/png')
     * @param  int  $fileSize  Ukuran berkas dalam bytes
     * @param  float|null  $latitude  Koordinat latitude yang diekstrak dari EXIF
     * @param  float|null  $longitude  Koordinat longitude yang diekstrak dari EXIF
     * @param  CarbonImmutable|null  $capturedAt  Waktu pengambilan foto dari EXIF
     * @param  int|null  $width  Lebar gambar dalam piksel
     * @param  int|null  $height  Tinggi gambar dalam piksel
     */
    public function __construct(
        public readonly string $tempPath,
        public readonly string $mimeType,
        public readonly int $fileSize,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?CarbonImmutable $capturedAt = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
    ) {}

    /**
     * Ekstensi berkas yang sesuai dengan MIME yang benar-benar disimpan.
     */
    public function extension(): string
    {
        return match ($this->mimeType) {
            'image/png' => 'png',
            default => 'jpg',
        };
    }

    /**
     * Hapus berkas sementara dari disk setelah selesai disimpan ke storage.
     * Aman dipanggil berulang kali dan tidak melempar error jika berkas sudah tidak ada.
     */
    public function cleanup(): void
    {
        if (file_exists($this->tempPath)) {
            @unlink($this->tempPath);
            clearstatcache(true, $this->tempPath);
        }
    }
}
