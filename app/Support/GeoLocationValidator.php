<?php

namespace App\Support;

use App\Models\ForestArea;

class GeoLocationValidator
{
    /**
     * Bounding Box koordinat cakupan wilayah Provinsi Jawa Barat (TR-17).
     * Latitude: -7.90 s/d -5.90 (Selatan ke Utara)
     * Longitude: 106.35 s/d 108.85 (Barat ke Timur)
     */
    public const WEST_JAVA_MIN_LAT = -7.90;

    public const WEST_JAVA_MAX_LAT = -5.90;

    public const WEST_JAVA_MIN_LNG = 106.35;

    public const WEST_JAVA_MAX_LNG = 108.85;

    /**
     * Memeriksa apakah koordinat berada di dalam batas wilayah Jawa Barat (Bounding Box).
     */
    public static function isWithinWestJava(float|string|null $latitude, float|string|null $longitude): bool
    {
        if ($latitude === null || $longitude === null || ! is_numeric($latitude) || ! is_numeric($longitude)) {
            return false;
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;

        return $lat >= self::WEST_JAVA_MIN_LAT
            && $lat <= self::WEST_JAVA_MAX_LAT
            && $lng >= self::WEST_JAVA_MIN_LNG
            && $lng <= self::WEST_JAVA_MAX_LNG;
    }

    /**
     * Memeriksa apakah koordinat (lat, lng) berada di dalam poligon GeoJSON menggunakan Ray-Casting algorithm.
     *
     * @param  array<string, mixed>  $geojson
     */
    public static function isPointInPolygon(float $latitude, float $longitude, array $geojson): bool
    {
        if (! isset($geojson['coordinates']) || ! is_array($geojson['coordinates'])) {
            return false;
        }

        /** @var list<array{0: float|int, 1: float|int}> $coords */
        $coords = $geojson['type'] === 'Polygon'
            ? ($geojson['coordinates'][0] ?? [])
            : [];

        if (count($coords) < 3) {
            return false;
        }

        $inside = false;
        $numPoints = count($coords);

        for ($i = 0, $j = $numPoints - 1; $i < $numPoints; $j = $i++) {
            $xi = (float) $coords[$i][0]; // longitude
            $yi = (float) $coords[$i][1]; // latitude
            $xj = (float) $coords[$j][0]; // longitude
            $yj = (float) $coords[$j][1]; // latitude

            $intersect = (($yi > $latitude) !== ($yj > $latitude))
                && ($longitude < ($xj - $xi) * ($latitude - $yi) / ($yj - $yi) + $xi);

            if ($intersect) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * Memeriksa apakah koordinat berada di dalam kawasan hutan tertentu (berdasarkan batas GeoJSON poligon,
     * atau toleransi radius dari titik pusat kawasan hutan).
     */
    public static function isWithinForestArea(float $latitude, float $longitude, ForestArea $forestArea, float $toleranceKm = 25.0): bool
    {
        if (! empty($forestArea->boundary) && is_array($forestArea->boundary)) {
            if (self::isPointInPolygon($latitude, $longitude, $forestArea->boundary)) {
                return true;
            }
        }

        if ($forestArea->center_lat !== null && $forestArea->center_lng !== null) {
            $distance = self::calculateDistance(
                (float) $forestArea->center_lat,
                (float) $forestArea->center_lng,
                $latitude,
                $longitude
            );

            return $distance <= $toleranceKm;
        }

        return self::isWithinWestJava($latitude, $longitude);
    }

    /**
     * Menghitung jarak geosferik antara dua titik koordinat menggunakan formula Haversine (dalam kilometer).
     */
    public static function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

        return $angle * $earthRadiusKm;
    }
}
