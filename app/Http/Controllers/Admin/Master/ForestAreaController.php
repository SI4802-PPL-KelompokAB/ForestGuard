<?php

namespace App\Http\Controllers\Admin\Master;

use App\Actions\Admin\Master\CreateForestArea;
use App\Actions\Admin\Master\RestoreForestArea;
use App\Actions\Admin\Master\SoftDeleteForestArea;
use App\Actions\Admin\Master\UpdateForestArea;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Master\StoreForestAreaRequest;
use App\Http\Requests\Admin\Master\UpdateForestAreaRequest;
use App\Models\ForestArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ForestAreaController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $q = $request->string('q')->trim()->toString();

        $forestAreas = ForestArea::query()
            ->when($status === 'terhapus', fn ($query) => $query->onlyTrashed())
            ->when($status === 'aktif', fn ($query) => $query->withoutTrashed())
            ->when($q !== '', function ($query) use ($q): void {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(function ($query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            })
            ->withCount('checkpoints')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.master.forest-areas.index', [
            'forestAreas' => $forestAreas,
            'status' => $status,
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('admin.master.forest-areas.create');
    }

    public function store(StoreForestAreaRequest $request, CreateForestArea $createForestArea): RedirectResponse
    {
        $createForestArea->handle($request);

        return redirect()
            ->route('admin.master.forest-areas.index')
            ->with('status', 'Kawasan hutan berhasil ditambahkan.');
    }

    public function edit(ForestArea $forestArea): View
    {
        return view('admin.master.forest-areas.edit', [
            'forestArea' => $forestArea,
        ]);
    }

    public function update(UpdateForestAreaRequest $request, ForestArea $forestArea, UpdateForestArea $updateForestArea): RedirectResponse
    {
        $updateForestArea->handle($request, $forestArea);

        return redirect()
            ->route('admin.master.forest-areas.index')
            ->with('status', 'Kawasan hutan berhasil diperbarui.');
    }

    public function destroy(ForestArea $forestArea, SoftDeleteForestArea $softDeleteForestArea): RedirectResponse
    {
        $softDeleteForestArea->handle($forestArea);

        return redirect()
            ->route('admin.master.forest-areas.index')
            ->with('status', 'Kawasan hutan berhasil dihapus.');
    }

    public function restore(ForestArea $forestArea, RestoreForestArea $restoreForestArea): RedirectResponse
    {
        $restoreForestArea->handle($forestArea);

        return redirect()
            ->route('admin.master.forest-areas.index', ['status' => 'terhapus'])
            ->with('status', 'Kawasan hutan berhasil dipulihkan.');
    }
}
