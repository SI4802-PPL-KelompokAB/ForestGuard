<?php

namespace App\Http\Controllers\Admin\Master;

use App\Actions\Admin\Master\CreateRiskLevel;
use App\Actions\Admin\Master\ToggleRiskLevel;
use App\Actions\Admin\Master\UpdateRiskLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Master\StoreRiskLevelRequest;
use App\Http\Requests\Admin\Master\UpdateRiskLevelRequest;
use App\Models\RiskLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RiskLevelController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $q = $request->string('q')->trim()->toString();

        $riskLevels = RiskLevel::query()
            ->when($status === 'aktif', fn ($query) => $query->where('is_active', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('is_active', false))
            ->when($q !== '', function ($query) use ($q): void {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where('name', 'like', $like);
            })
            ->withCount('incidents')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.master.risk-levels.index', [
            'riskLevels' => $riskLevels,
            'status' => $status,
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('admin.master.risk-levels.create');
    }

    public function store(StoreRiskLevelRequest $request, CreateRiskLevel $createRiskLevel): RedirectResponse
    {
        $createRiskLevel->handle($request);

        return redirect()
            ->route('admin.master.risk-levels.index')
            ->with('status', 'Tingkat risiko berhasil ditambahkan.');
    }

    public function edit(RiskLevel $riskLevel): View
    {
        return view('admin.master.risk-levels.edit', [
            'riskLevel' => $riskLevel,
        ]);
    }

    public function update(UpdateRiskLevelRequest $request, RiskLevel $riskLevel, UpdateRiskLevel $updateRiskLevel): RedirectResponse
    {
        $updateRiskLevel->handle($request, $riskLevel);

        return redirect()
            ->route('admin.master.risk-levels.index')
            ->with('status', 'Tingkat risiko berhasil diperbarui.');
    }

    public function toggle(RiskLevel $riskLevel, ToggleRiskLevel $toggleRiskLevel): RedirectResponse
    {
        $toggleRiskLevel->handle($riskLevel);

        $statusLabel = $riskLevel->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()
            ->route('admin.master.risk-levels.index')
            ->with('status', "Tingkat risiko berhasil {$statusLabel}.");
    }
}
