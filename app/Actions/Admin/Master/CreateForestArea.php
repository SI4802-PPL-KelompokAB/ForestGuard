<?php

namespace App\Actions\Admin\Master;

use App\Http\Requests\Admin\Master\StoreForestAreaRequest;
use App\Models\ForestArea;
use Illuminate\Support\Facades\DB;

class CreateForestArea
{
    public function handle(StoreForestAreaRequest $request): ForestArea
    {
        return DB::transaction(function () use ($request): ForestArea {
            $data = $request->safe()->only([
                'name',
                'description',
                'area_ha',
                'center_lat',
                'center_lng',
            ]);

            if ($request->filled('boundary')) {
                $data['boundary'] = json_decode($request->input('boundary'), true);
            }

            return ForestArea::create($data);
        });
    }
}
