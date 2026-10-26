<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\AssignPatrol;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePatrolRequest;
use App\Models\Checkpoint;
use App\Models\ForestArea;
use App\Models\Patrol;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatrolController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.patrols.index', [
            'patrols' => Patrol::with(['ranger', 'forestArea'])->orderByDesc('id')->paginate(15),
            'rangers' => User::where('is_active', true)->whereHas('role', fn ($query) => $query->where('name', Role::RANGER))->orderBy('name')->orderBy('id')->get(),
            'areas' => ForestArea::orderBy('name')->orderBy('id')->get(),
            'checkpoints' => Checkpoint::where('forest_area_id', $request->old('forest_area_id'))->whereHas('forestArea')->orderBy('code')->orderBy('id')->get(),
        ]);
    }

    public function checkpoints(ForestArea $area): JsonResponse
    {
        return response()->json($area->checkpoints()->orderBy('code')->orderBy('id')->get(['id', 'code', 'name']));
    }

    public function store(StorePatrolRequest $request, AssignPatrol $assignPatrol): RedirectResponse|JsonResponse
    {
        try {
            $patrol = $assignPatrol->handle($request->user(), $request->validated());
        } catch (QueryException $exception) {
            report($exception);
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Penugasan belum dapat disimpan. Silakan coba kembali.'], 500);
            }

            return back()->withInput()->with('warning', 'Penugasan belum dapat disimpan. Silakan coba kembali.');
        }

        if ($request->expectsJson()) {
            return response()->json($patrol->load('patrolCheckpoints'), 201);
        }

        return redirect()->route('admin.patrols.index')->with('status', 'Patroli '.$patrol->code.' berhasil ditugaskan kepada '.$patrol->ranger->name.'.');
    }

    public function show(Patrol $patrol): View
    {
        return view('patrols.show', ['patrol' => $patrol->load(['forestArea', 'ranger', 'creator', 'patrolCheckpoints.checkpoint'])]);
    }
}
