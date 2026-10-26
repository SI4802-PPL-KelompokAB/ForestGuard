<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\CaseStatus;
use Illuminate\View\View;

class CaseStatusController extends Controller
{
    public function index(): View
    {
        $caseStatuses = CaseStatus::query()
            ->withCount('incidents')
            ->orderBy('sequence')
            ->orderBy('id')
            ->paginate(15);

        return view('admin.master.case-statuses.index', [
            'caseStatuses' => $caseStatuses,
        ]);
    }
}
