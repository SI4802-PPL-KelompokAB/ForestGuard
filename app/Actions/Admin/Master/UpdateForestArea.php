<?php

namespace App\Actions\Admin\Master;

use App\Http\Requests\Admin\Master\UpdateForestAreaRequest;
use App\Models\ForestArea;
use Illuminate\Support\Facades\DB;

class UpdateForestArea
{
    public function handle(UpdateForestAreaRequest $request, ForestArea $forestArea): ForestArea
    {
        return DB::transaction(function () use ($request, $forestArea): ForestArea {
            $data = $request->safe()->only([
                'name',
                'description',
                'area_ha',
                'center_lat',
                'center_lng',
            ]);

            if ($request->filled('boundary')) {
                $data['boundary'] = json_decode($request->input('boundary'), true);
            } else {
                $data['boundary'] = null;
            }

            $forestArea->update($data);

            return $forestArea;
        });
    }
}
