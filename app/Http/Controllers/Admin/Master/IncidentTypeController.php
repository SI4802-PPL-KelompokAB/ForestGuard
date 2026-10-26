<?php

namespace App\Http\Controllers\Admin\Master;

use App\Actions\Admin\Master\CreateIncidentType;
use App\Actions\Admin\Master\RestoreIncidentType;
use App\Actions\Admin\Master\SoftDeleteIncidentType;
use App\Actions\Admin\Master\UpdateIncidentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Master\StoreIncidentTypeRequest;
use App\Http\Requests\Admin\Master\UpdateIncidentTypeRequest;
use App\Models\IncidentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncidentTypeController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $q = $request->string('q')->trim()->toString();

        $incidentTypes = IncidentType::query()
            ->when($status === 'terhapus', fn ($query) => $query->onlyTrashed())
            ->when($status === 'aktif', fn ($query) => $query->withoutTrashed())
            ->when($q !== '', function ($query) use ($q): void {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where('name', 'like', $like);
            })
            ->withCount('incidents')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.master.incident-types.index', [
            'incidentTypes' => $incidentTypes,
            'status' => $status,
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('admin.master.incident-types.create');
    }

    public function store(StoreIncidentTypeRequest $request, CreateIncidentType $createIncidentType): RedirectResponse
    {
        $createIncidentType->handle($request);

        return redirect()
            ->route('admin.master.incident-types.index')
            ->with('status', 'Tipe insiden berhasil ditambahkan.');
    }

    public function edit(IncidentType $incidentType): View
    {
        return view('admin.master.incident-types.edit', [
            'incidentType' => $incidentType,
        ]);
    }

    public function update(UpdateIncidentTypeRequest $request, IncidentType $incidentType, UpdateIncidentType $updateIncidentType): RedirectResponse
    {
        $updateIncidentType->handle($request, $incidentType);

        return redirect()
            ->route('admin.master.incident-types.index')
            ->with('status', 'Tipe insiden berhasil diperbarui.');
    }

    public function destroy(IncidentType $incidentType, SoftDeleteIncidentType $softDeleteIncidentType): RedirectResponse
    {
        $softDeleteIncidentType->handle($incidentType);

        return redirect()
            ->route('admin.master.incident-types.index')
            ->with('status', 'Tipe insiden berhasil dihapus.');
    }

    public function restore(IncidentType $incidentType, RestoreIncidentType $restoreIncidentType): RedirectResponse
    {
        $restoreIncidentType->handle($incidentType);

        return redirect()
            ->route('admin.master.incident-types.index', ['status' => 'terhapus'])
            ->with('status', 'Tipe insiden berhasil dipulihkan.');
    }
}
