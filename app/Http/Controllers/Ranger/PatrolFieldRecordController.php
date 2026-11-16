<?php

namespace App\Http\Controllers\Ranger;

use App\Actions\Ranger\RecordPatrolCheckpoint;
use App\Actions\Ranger\StoreObservation;
use App\Exceptions\PatrolConflict;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ranger\StoreObservationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PatrolFieldRecordController extends Controller
{
    public function checkpoint(Request $request, int $patrol, int $plannedCheckpoint, RecordPatrolCheckpoint $action): JsonResponse
    {
        $request->user()->patrols()->findOrFail($patrol);
        $coordinates = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        try {
            $checkpoint = $action->handle($request->user(), $patrol, $plannedCheckpoint, $coordinates);
        } catch (PatrolConflict $exception) {
            return response()->json(['message' => $exception->getMessage()], 409)->header('Cache-Control', 'no-store');
        }

        $patrolModel = $request->user()->patrols()->with('patrolCheckpoints.checkpoint')->findOrFail($patrol);

        return response()->json([
            'message' => 'Kunjungan checkpoint tersimpan.',
            'checkpoint' => $checkpoint,
            'checkpoints_html' => view('patrols._checkpoints', ['patrol' => $patrolModel])->render(),
            'unvisited_count' => $patrolModel->patrolCheckpoints->whereNull('visited_at')->count(),
            'total_distance_km' => $patrolModel->total_distance_km,
        ])
            ->header('Cache-Control', 'no-store');
    }

    public function observation(StoreObservationRequest $request, int $patrol, StoreObservation $action): JsonResponse
    {
        try {
            $observation = $action->handle($request->user(), $patrol, $request->validated(), $request->file('photo'));
        } catch (PatrolConflict $exception) {
            return response()->json(['message' => $exception->getMessage()], 409)->header('Cache-Control', 'no-store');
        }

        $patrolModel = $request->user()->patrols()->with('observations.evidences')->findOrFail($patrol);

        return response()->json([
            'message' => 'Observasi lapangan tersimpan.',
            'observation' => $observation,
            'observations_html' => view('patrols._observations', ['patrol' => $patrolModel])->render(),
            'next_client_uuid' => (string) Str::uuid(),
        ], 201)
            ->header('Cache-Control', 'no-store');
    }
}
