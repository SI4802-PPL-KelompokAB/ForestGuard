<?php

namespace App\Http\Controllers\Ranger;

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
