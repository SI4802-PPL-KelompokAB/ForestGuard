<?php

namespace App\Http\Controllers\Admin\Master;

use App\Actions\Admin\Master\CreateCheckpoint;
use App\Actions\Admin\Master\RestoreCheckpoint;
use App\Actions\Admin\Master\SoftDeleteCheckpoint;
use App\Actions\Admin\Master\UpdateCheckpoint;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Master\StoreCheckpointRequest;
use App\Http\Requests\Admin\Master\UpdateCheckpointRequest;
use App\Models\Checkpoint;
use App\Models\ForestArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckpointController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $kawasanId = $request->integer('kawasan');
        $q = $request->string('q')->trim()->toString();

        $checkpoints = Checkpoint::query()
            ->when($status === 'terhapus', fn ($query) => $query->onlyTrashed())
            ->when($status !== 'terhapus', function ($query): void {
                $query->withoutTrashed()->whereHas('forestArea');
            })
            ->when($kawasanId > 0, fn ($query) => $query->where('forest_area_id', $kawasanId))
            ->when($q !== '', function ($query) use ($q): void {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(function ($query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like);
                });
            })
            ->with('forestArea')
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        $forestAreas = ForestArea::orderBy('name')->get();

        return view('admin.master.checkpoints.index', [
            'checkpoints' => $checkpoints,
            'forestAreas' => $forestAreas,
            'status' => $status,
            'kawasanId' => $kawasanId,
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('admin.master.checkpoints.create', [
            'forestAreas' => ForestArea::orderBy('name')->get(),
        ]);
    }

    public function store(StoreCheckpointRequest $request, CreateCheckpoint $createCheckpoint): RedirectResponse
    {
        $createCheckpoint->handle($request);

        return redirect()
            ->route('admin.master.checkpoints.index')
            ->with('status', 'Checkpoint berhasil ditambahkan.');
    }

    public function edit(Checkpoint $checkpoint): View
    {
        $forestAreas = ForestArea::withTrashed()
            ->where(function ($query) use ($checkpoint): void {
                $query->whereNull('deleted_at')
                    ->orWhere('id', $checkpoint->forest_area_id);
            })
            ->orderBy('name')
            ->get();

        return view('admin.master.checkpoints.edit', [
            'checkpoint' => $checkpoint,
            'forestAreas' => $forestAreas,
        ]);
    }

    public function update(UpdateCheckpointRequest $request, Checkpoint $checkpoint, UpdateCheckpoint $updateCheckpoint): RedirectResponse
    {
        $updateCheckpoint->handle($request, $checkpoint);

        return redirect()
            ->route('admin.master.checkpoints.index')
            ->with('status', 'Checkpoint berhasil diperbarui.');
    }

    public function destroy(Checkpoint $checkpoint, SoftDeleteCheckpoint $softDeleteCheckpoint): RedirectResponse
    {
        $softDeleteCheckpoint->handle($checkpoint);

        return redirect()
            ->route('admin.master.checkpoints.index')
            ->with('status', 'Checkpoint berhasil dihapus.');
    }

    public function restore(Checkpoint $checkpoint, RestoreCheckpoint $restoreCheckpoint): RedirectResponse
    {
        $restoreCheckpoint->handle($checkpoint);

        return redirect()
            ->route('admin.master.checkpoints.index', ['status' => 'terhapus'])
            ->with('status', 'Checkpoint berhasil dipulihkan.');
    }
}
