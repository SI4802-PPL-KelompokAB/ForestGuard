<?php

namespace App\Http\Controllers\Ranger;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatrolController extends Controller
{
    public function index(Request $request): View
    {
        return view('ranger.patrols.index', [
            'patrols' => $request->user()->patrols()->with('forestArea')->withCount('patrolCheckpoints')->orderBy('scheduled_at')->orderBy('id')->paginate(15),
        ]);
    }

    public function show(Request $request, int $patrol): View
    {
        $patrol = $request->user()->patrols()->with(['forestArea', 'ranger', 'creator', 'patrolCheckpoints.checkpoint'])->findOrFail($patrol);

        return view('patrols.show', ['patrol' => $patrol]);
    }
}
