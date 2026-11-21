<?php

// PBI-04: Bagian kode yang mendukung master data dan proteksi akses.

namespace App\Actions\Admin\Master;

use App\Http\Requests\Admin\Master\StoreRiskLevelRequest;
use App\Models\RiskLevel;
use Illuminate\Support\Facades\DB;

class CreateRiskLevel
{
    public function handle(StoreRiskLevelRequest $request): RiskLevel
    {
        return DB::transaction(function () use ($request): RiskLevel {
            return RiskLevel::create($request->validated());
        });
    }
}
