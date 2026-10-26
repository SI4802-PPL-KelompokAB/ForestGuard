<?php

namespace Database\Seeders;

use App\Models\Checkpoint;
use App\Models\ForestArea;
use Illuminate\Database\Seeder;

class CheckpointSeeder extends Seeder
{
    public function run(): void
    {
        $areas = ForestArea::query()->orderBy('id')->get();

        $checkpointsByArea = [
            'Taman Nasional Gunung Ciremai' => [
                ['code' => 'CP-GC-01', 'name' => 'Pos Palutungan', 'latitude' => -6.9125000, 'longitude' => 108.3982000],
                ['code' => 'CP-GC-02', 'name' => 'Pos Apuy', 'latitude' => -6.8874000, 'longitude' => 108.4191000],
                ['code' => 'CP-GC-03', 'name' => 'Pos Linggarjati', 'latitude' => -6.8712000, 'longitude' => 108.4015000],
                ['code' => 'CP-GC-04', 'name' => 'Pos Setianegara', 'latitude' => -6.8998000, 'longitude' => 108.3887000],
            ],
            'Taman Hutan Raya Ir. H. Djuanda' => [
                ['code' => 'CP-DJ-01', 'name' => 'Pos Pintu Utama', 'latitude' => -6.8569000, 'longitude' => 107.6318000],
                ['code' => 'CP-DJ-02', 'name' => 'Pos Goa Belanda', 'latitude' => -6.8542000, 'longitude' => 107.6354000],
                ['code' => 'CP-DJ-03', 'name' => 'Pos Tebing Keraton', 'latitude' => -6.8497000, 'longitude' => 107.6389000],
                ['code' => 'CP-DJ-04', 'name' => 'Pos Curug Omas', 'latitude' => -6.8591000, 'longitude' => 107.6276000],
            ],
            'Taman Nasional Gunung Gede Pangrango' => [
                ['code' => 'CP-GP-01', 'name' => 'Pos Cibodas', 'latitude' => -6.7418000, 'longitude' => 106.9932000],
                ['code' => 'CP-GP-02', 'name' => 'Pos Gunung Putri', 'latitude' => -6.7624000, 'longitude' => 106.9801000],
                ['code' => 'CP-GP-03', 'name' => 'Pos Selabintana', 'latitude' => -6.7985000, 'longitude' => 106.9614000],
                ['code' => 'CP-GP-04', 'name' => 'Pos Situ Gunung', 'latitude' => -6.8213000, 'longitude' => 106.9278000],
                ['code' => 'CP-GP-05', 'name' => 'Pos Alun-alun Suryakencana', 'latitude' => -6.7769000, 'longitude' => 106.9795000],
            ],
        ];

        foreach ($areas as $area) {
            $checkpoints = $checkpointsByArea[$area->name] ?? [];

            foreach ($checkpoints as $checkpoint) {
                Checkpoint::withTrashed()->updateOrCreate(
                    ['code' => $checkpoint['code']],
                    [
                        'forest_area_id' => $area->id,
                        'name' => $checkpoint['name'],
                        'latitude' => $checkpoint['latitude'],
                        'longitude' => $checkpoint['longitude'],
                        'deleted_at' => null,
                    ]
                );
            }
        }
    }
}
