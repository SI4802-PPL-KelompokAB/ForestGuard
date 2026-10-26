<?php

namespace App\Actions\Admin\Master;

use App\Http\Requests\Admin\Master\UpdateRiskLevelRequest;
use App\Models\RiskLevel;
use Illuminate\Support\Facades\DB;

class UpdateRiskLevel
{
    public function handle(UpdateRiskLevelRequest $request, RiskLevel $riskLevel): RiskLevel
    {
        return DB::transaction(function () use ($request, $riskLevel): RiskLevel {
            $riskLevel->update($request->validated());

            return $riskLevel;
        });
    }
}
