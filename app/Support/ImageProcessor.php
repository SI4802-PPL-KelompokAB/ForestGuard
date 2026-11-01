<?php

namespace App\Support;

use App\Exceptions\InvalidImageException;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver as InterventionGdDriver;
use Intervention\Image\ImageManager;
use RuntimeException;
use Throwable;

class ImageProcessor
{
    /**
     * Batas resolusi dimensi maksimum (lebar / tinggi) setelah kompresi.
     */
    public const MAX_DIMENSION = 1920;

    /**
     * Batas piksel maksimum sebelum decode (40 Megapiksel) untuk mencegah kehabisan memori.
     */
    public const MAX_PIXELS = 40_000_000;

    /**
     * Kualitas kompresi JPEG (0-100).
     */
    public const JPEG_QUALITY = 80;

    /**
     * Memproses gambar: validasi dimensi, mengekstrak EXIF SEBELUM kompresi,
     * lalu mengompresi gambar satu jalur via Intervention Image v3 + GdDriver.
     *
     * @throws InvalidImageException
     * @throws RuntimeException
     */
    public function process(UploadedFile|string $file): ProcessedImage
    {
        $realPath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        if (! file_exists($realPath) || filesize($realPath) === 0) {
            throw new InvalidImageException('Berkas gambar tidak valid atau rusak.');
        }

        // Cek ketersediaan library dan ekstensi
        if (! class_exists(ImageManager::class)) {
            throw new RuntimeException('Library Intervention Image v3 tidak terpasang.');
        }

        if (! extension_loaded('gd')) {
            throw new RuntimeException('Ekstensi PHP GD wajib diaktifkan.');
        }

        // 1. Cek dimensi dan keabsahan berkas via getimagesize sebelum decode
        $imageInfo = @getimagesize($realPath);
        if ($imageInfo === false) {
            throw new InvalidImageException('Berkas gambar tidak valid atau rusak.');
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $detectedMime = $imageInfo['mime'] ?? '';

        if (! in_array($detectedMime, ['image/jpeg', 'image/png'], true)) {
            throw new InvalidImageException('Format berkas harus berupa JPG, JPEG, atau PNG.');
        }

        if (($width * $height) > self::MAX_PIXELS) {
            throw new InvalidImageException('Dimensi gambar melebihi batas maksimal 40 megapiksel.');
        }

        // 2. Ekstraksi EXIF metadata SEBELUM kompresi
        $exifData = $this->extractExif($realPath, $detectedMime);

        // 3. Lakukan kompresi gambar satu jalur (Intervention v3 + GdDriver)
        $compressed = $this->compressWithIntervention($realPath, $detectedMime);

        return new ProcessedImage(
            tempPath: $compressed['temp_path'],
            mimeType: $compressed['mime_type'],
            fileSize: $compressed['file_size'],
            latitude: $exifData['latitude'],
            longitude: $exifData['longitude'],
            capturedAt: $exifData['captured_at'],
            width: $compressed['width'],
            height: $compressed['height'],
        );
    }

    /**
     * Ekstrak metadata EXIF (GPS Latitude, Longitude, dan Timestamp).
     *
     * @return array{latitude: ?float, longitude: ?float, captured_at: ?CarbonImmutable}
     *
     * @throws RuntimeException
     */
    public function extractExif(string $filePath, string $mimeType): array
    {
        $result = [
            'latitude' => null,
            'longitude' => null,
            'captured_at' => null,
        ];

        // PNG aman tanpa EXIF
        if ($mimeType === 'image/png') {
            return $result;
        }

        if (! extension_loaded('exif')) {
            return $result;
        }

        try {
            $sections = @exif_read_data($filePath, null, true);
            if (! is_array($sections)) {
                return $result;
            }

            // Gabungkan bagian-bagian EXIF yang dibutuhkan menjadi satu array datar
            $exif = array_merge(
                $sections['IFD0'] ?? [],
                $sections['EXIF'] ?? [],
                $sections['GPS'] ?? [],
            );

            // Ekstrak timestamp waktu pengambilan foto dengan zona waktu aplikasi
            $rawDate = $exif['DateTimeOriginal'] ?? $exif['DateTimeDigitized'] ?? $exif['DateTime'] ?? null;
            if (is_string($rawDate) && trim($rawDate) !== '') {
                $appTimezone = (function_exists('app') && app()->bound('config'))
                    ? config('app.timezone', 'Asia/Jakarta')
                    : 'Asia/Jakarta';
                try {
                    $capturedAt = CarbonImmutable::createFromFormat('Y:m:d H:i:s', trim($rawDate), $appTimezone);
                    $result['captured_at'] = $capturedAt instanceof CarbonImmutable ? $capturedAt : null;
                } catch (Throwable) {
                    try {
                        $result['captured_at'] = CarbonImmutable::parse(trim($rawDate), $appTimezone);
                    } catch (Throwable) {
                        $result['captured_at'] = null;
                    }
                }
            }

            // Ekstrak koordinat GPS
            if (isset($exif['GPSLatitude'], $exif['GPSLatitudeRef'], $exif['GPSLongitude'], $exif['GPSLongitudeRef'])) {
                $lat = $this->parseGpsCoordinate($exif['GPSLatitude'], $exif['GPSLatitudeRef']);
                $lon = $this->parseGpsCoordinate($exif['GPSLongitude'], $exif['GPSLongitudeRef']);

                // Validasi batas geografis dan pastikan bukan 0,0
                if ($lat !== null && $lon !== null) {
                    if ($lat >= -90.0 && $lat <= 90.0 && $lon >= -180.0 && $lon <= 180.0) {
                        if (! ($lat == 0.0 && $lon == 0.0)) {
                            $result['latitude'] = $lat;
                            $result['longitude'] = $lon;
                        }
                    }
                }
            }
        } catch (Throwable) {
            // Abaikan kesalahan parsing jika header EXIF tidak lengkap
        }

        return $result;
    }

    /**
     * Kompresi gambar satu jalur menggunakan Intervention Image v3 + GdDriver.
     *
     * @return array{temp_path: string, mime_type: string, file_size: int, width: int, height: int}
     *
     * @throws InvalidImageException
     */
    protected function compressWithIntervention(string $sourcePath, string $mimeType): array
    {
        try {
            $manager = new ImageManager(new InterventionGdDriver);
            $image = $manager->read($sourcePath);

            // Putar sesuai tag EXIF orientation sebelum di-encode ulang
            $image->orient();

            // Resize jika melebihi batas dimensi maksimal
            if ($image->width() > self::MAX_DIMENSION || $image->height() > self::MAX_DIMENSION) {
                $image->scaleDown(width: self::MAX_DIMENSION, height: self::MAX_DIMENSION);
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'fg_img_');

            // Pertahankan tipe format: PNG tetap PNG, JPEG tetap JPEG
            if ($mimeType === 'image/png') {
                $encoded = $image->toPng();
                $outputMime = 'image/png';
            } else {
                $encoded = $image->toJpeg(self::JPEG_QUALITY);
                $outputMime = 'image/jpeg';
            }

            $encoded->save($tempPath);

            return [
                'temp_path' => $tempPath,
                'mime_type' => $outputMime,
                'file_size' => (int) filesize($tempPath),
                'width' => $image->width(),
                'height' => $image->height(),
            ];
        } catch (Throwable $e) {
            throw new InvalidImageException('Berkas gambar tidak valid atau rusak.', 0, $e);
        }
    }

    /**
     * Parse koordinat GPS EXIF (Degrees, Minutes, Seconds) ke format decimal.
     */
    private function parseGpsCoordinate(mixed $coordinate, mixed $ref): ?float
    {
        if (! is_array($coordinate) || count($coordinate) < 3) {
            return null;
        }

        $degrees = $this->parseCoordinateComponent($coordinate[0]);
        $minutes = $this->parseCoordinateComponent($coordinate[1]);
        $seconds = $this->parseCoordinateComponent($coordinate[2]);

        if ($degrees === null || $minutes === null || $seconds === null) {
            return null;
        }

        // Jika semua bernilai 0, koordinat dianggap tidak ada
        if ($degrees == 0.0 && $minutes == 0.0 && $seconds == 0.0) {
            return null;
        }

        $decimal = $degrees + ($minutes / 60.0) + ($seconds / 3600.0);

        if (is_string($ref) && in_array(strtoupper(trim($ref)), ['S', 'W'], true)) {
            $decimal = -$decimal;
        }

        return round($decimal, 7);
    }

    /**
     * Parse komponen pecahan rasional EXIF 'pembilang/penyebut'.
     */
    private function parseCoordinateComponent(mixed $component): ?float
    {
        if (is_numeric($component)) {
            return (float) $component;
        }

        if (is_string($component) && str_contains($component, '/')) {
            $parts = explode('/', $component, 2);
            $numerator = (float) trim($parts[0]);
            $denominator = (float) trim($parts[1]);

            // Denominator 0 dianggap tidak valid (tidak ada)
            if ($denominator == 0.0) {
                return null;
            }

            return $numerator / $denominator;
        }

        return null;
    }
}
