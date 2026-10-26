<?php

namespace App\Actions\Admin\Master;

use App\Models\RiskLevel;
use Illuminate\Validation\ValidationException;

class ToggleRiskLevel
{
    public function handle(RiskLevel $riskLevel): RiskLevel
    {
        if ($riskLevel->is_active) {
            $otherActiveCount = RiskLevel::query()
                ->where('is_active', true)
                ->where('id', '!=', $riskLevel->id)
                ->count();

            if ($otherActiveCount === 0) {
                throw ValidationException::withMessages([
                    'is_active' => 'Tidak dapat menonaktifkan tingkat risiko ini karena minimal harus ada satu tingkat risiko yang aktif.',
                ]);
            }
        }

        $riskLevel->is_active = ! $riskLevel->is_active;
        $riskLevel->save();

        return $riskLevel;
    }
}
