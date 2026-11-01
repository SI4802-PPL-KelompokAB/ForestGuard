<?php

namespace App\Http\Controllers\Ranger;

use App\Actions\Ranger\RecordPatrolTrack;
use App\Actions\Ranger\TransitionPatrol;
use App\Enums\PatrolStatus;
use App\Exceptions\PatrolConflict;
use App\Http\Controllers\Controller;
use App\Models\Patrol;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PatrolController extends Controller
{
    public function index(Request $request): View
    {
        return view('ranger.patrols.index', [
            'patrols' => $request->user()->patrols()->with('forestArea')->withCount('patrolCheckpoints')
                ->orderByRaw('CASE WHEN status = ? THEN 0 WHEN status = ? THEN 1 ELSE 2 END', [PatrolStatus::InProgress->value, PatrolStatus::Assigned->value])
                ->orderByRaw('CASE WHEN status = ? THEN scheduled_at END ASC', [PatrolStatus::Assigned->value])
                ->orderByDesc('ended_at')->orderBy('id')->paginate(15),
            'activePatrol' => $this->activePatrol($request->user()),
        ]);
    }

    public function show(Request $request, int $patrol): Response|JsonResponse
    {
        $patrol = $request->user()->patrols()->with(['forestArea', 'ranger', 'creator', 'patrolCheckpoints.checkpoint'])->findOrFail($patrol);
        $active = $this->activePatrol($request->user());

        if ($request->expectsJson()) {
            return response()->json(['patrol' => $patrol->executionState(), 'active_patrol_id' => $active?->id])->header('Cache-Control', 'no-store');
        }

        return response()->view('patrols.show', ['patrol' => $patrol, 'activePatrol' => $active])->header('Cache-Control', 'no-store');
    }

    public function start(Request $request, int $patrol, TransitionPatrol $transitionPatrol): RedirectResponse|JsonResponse|Response
    {
        return $this->transition($request, $patrol, PatrolStatus::InProgress, $transitionPatrol);
    }

    public function finish(Request $request, int $patrol, TransitionPatrol $transitionPatrol): RedirectResponse|JsonResponse|Response
    {
        return $this->transition($request, $patrol, PatrolStatus::Completed, $transitionPatrol);
    }

    public function recordTrack(Request $request, int $patrol, RecordPatrolTrack $recordPatrolTrack): JsonResponse
    {
        if ($request->has('tracks')) {
            $validated = $request->validate([
                'tracks' => ['required', 'array', 'min:1'],
                'tracks.*.client_uuid' => ['required', 'uuid'],
                'tracks.*.latitude' => ['required', 'numeric', 'between:-90,90'],
                'tracks.*.longitude' => ['required', 'numeric', 'between:-180,180'],
                'tracks.*.recorded_at' => ['nullable', 'date'],
            ]);
            $points = $validated['tracks'];
        } else {
            $validated = $request->validate([
                'client_uuid' => ['required', 'uuid'],
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
                'recorded_at' => ['nullable', 'date'],
            ]);
            $points = [$validated];
        }

        try {
            $tracks = $recordPatrolTrack->handle($request->user(), $patrol, $points);
            $patrolModel = $request->user()->patrols()->findOrFail($patrol);

            return response()->json([
                'message' => 'Titik rute berhasil dicatat.',
                'total_distance_km' => $patrolModel->total_distance_km,
                'tracks_count' => $patrolModel->tracks()->count(),
                'recorded_tracks' => $tracks,
            ], 201)->header('Cache-Control', 'no-store');
        } catch (PatrolConflict $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409)->header('Cache-Control', 'no-store');
        }
    }

    public function tracks(Request $request, int $patrol): JsonResponse
    {
        $patrolModel = $request->user()->patrols()->findOrFail($patrol);
        $tracks = $patrolModel->tracks()->orderBy('recorded_at')->orderBy('id')->get();

        return response()->json([
            'patrol_id' => $patrolModel->id,
            'total_distance_km' => $patrolModel->total_distance_km,
            'tracks' => $tracks,
        ])->header('Cache-Control', 'no-store');
    }

    private function transition(Request $request, int $patrolId, PatrolStatus $target, TransitionPatrol $transitionPatrol): RedirectResponse|JsonResponse|Response
    {
        try {
            $patrol = $transitionPatrol->handle($request->user(), $patrolId, $target);
        } catch (PatrolConflict $exception) {
            $patrol = $request->user()->patrols()->with(['forestArea', 'ranger', 'creator', 'patrolCheckpoints.checkpoint'])->findOrFail($patrolId);
            $active = $this->activePatrol($request->user());
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage(), 'patrol' => $patrol->executionState(), 'active_patrol_id' => $active?->id], 409)->header('Cache-Control', 'no-store');
            }

            return response()->view('patrols.show', ['patrol' => $patrol, 'activePatrol' => $active, 'conflictMessage' => $exception->getMessage()], 409)->header('Cache-Control', 'no-store');
        } catch (QueryException $exception) {
            report($exception);
            $message = 'Perubahan belum dapat dikonfirmasi. Periksa kembali status patroli setelah koneksi tersedia.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 500);
            }

            return redirect()->route('ranger.patrols.show', $patrolId)->with('warning', $message);
        }

        if ($request->expectsJson()) {
            return response()->json(['patrol' => $patrol->executionState()])->header('Cache-Control', 'no-store');
        }

        return redirect()->route('ranger.patrols.show', $patrol)->with('status', $target === PatrolStatus::InProgress ? 'Patroli berhasil dimulai.' : 'Patroli berhasil diselesaikan.');
    }

    private function activePatrol(User $ranger): ?Patrol
    {
        return $ranger->patrols()->where('status', PatrolStatus::InProgress)->first();
    }
}
