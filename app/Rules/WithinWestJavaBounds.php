<?php

namespace App\Rules;

use App\Support\GeoLocationValidator;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class WithinWestJavaBounds implements DataAwareRule, ValidationRule
{
    /**
     * Data yang sedang divalidasi.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Set data under validation.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Validasi apakah koordinat (latitude & longitude) berada di dalam cakupan wilayah Jawa Barat (TR-17).
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $latitude = $this->data['latitude'] ?? null;
        $longitude = $this->data['longitude'] ?? null;

        if ($latitude === null || $longitude === null || ! is_numeric($latitude) || ! is_numeric($longitude)) {
            return;
        }

        if (! GeoLocationValidator::isWithinWestJava((float) $latitude, (float) $longitude)) {
            $fail('Titik koordinat kejadian berada di luar wilayah cakupan Jawa Barat.');
        }
    }
}
