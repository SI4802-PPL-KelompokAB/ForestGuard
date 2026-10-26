<?php

namespace Database\Seeders;

use App\Models\ForestArea;
use Illuminate\Database\Seeder;

class ForestAreaSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->areas() as $area) {
            ForestArea::withTrashed()->updateOrCreate(
                ['name' => $area['name']],
                [
                    'description' => $area['description'],
                    'area_ha' => $area['area_ha'],
                    'boundary' => $area['boundary'],
                    'center_lat' => $area['center_lat'],
                    'center_lng' => $area['center_lng'],
                    'deleted_at' => null,
                ]
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function areas(): array
    {
        return [
            [
                'name' => 'Taman Nasional Gunung Ciremai',
                'description' => 'Kawasan hutan lindung di Jawa Barat sekitar Kuningan dan Majalengka.',
                'area_ha' => 15500.00,
                'center_lat' => -6.8930000,
                'center_lng' => 108.4070000,
                'boundary' => $this->polygon(
                    -6.9100000,
                    108.3900000,
                    -6.8760000,
                    108.4240000
                ),
            ],
            [
                'name' => 'Taman Hutan Raya Ir. H. Djuanda',
                'description' => 'Kawasan konservasi di utara Bandung, Jawa Barat.',
                'area_ha' => 590.00,
                'center_lat' => -6.8560000,
                'center_lng' => 107.6320000,
                'boundary' => $this->polygon(
                    -6.8650000,
                    107.6230000,
                    -6.8470000,
                    107.6410000
                ),
            ],
            [
                'name' => 'Taman Nasional Gunung Gede Pangrango',
                'description' => 'Kawasan hutan tropis pegunungan di perbatasan Cianjur, Sukabumi, dan Bogor.',
                'area_ha' => 24270.00,
                'center_lat' => -6.7800000,
                'center_lng' => 106.9800000,
                'boundary' => $this->polygon(
                    -6.8100000,
                    106.9500000,
                    -6.7500000,
                    107.0100000
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function polygon(float $south, float $west, float $north, float $east): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [$west, $south],
                [$east, $south],
                [$east, $north],
                [$west, $north],
                [$west, $south],
            ]],
        ];
    }
}
