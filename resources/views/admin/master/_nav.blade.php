@php
    $masterTabs = [
        [
            'label' => 'Kawasan Hutan',
            'href' => route('admin.master.forest-areas.index'),
            'active' => 'admin.master.forest-areas.*',
        ],
        [
            'label' => 'Checkpoint',
            'href' => route('admin.master.checkpoints.index'),
            'active' => 'admin.master.checkpoints.*',
        ],
        [
            'label' => 'Tipe Insiden',
            'href' => route('admin.master.incident-types.index'),
            'active' => 'admin.master.incident-types.*',
        ],
        [
            'label' => 'Tingkat Risiko',
            'href' => route('admin.master.risk-levels.index'),
            'active' => 'admin.master.risk-levels.*',
        ],
        [
            'label' => 'Status Kasus (Read-Only)',
            'href' => route('admin.master.case-statuses.index'),
            'active' => 'admin.master.case-statuses.*',
        ],
    ];
@endphp

<div class="mb-6 border-b border-slate-200">
    <nav class="-mb-px flex flex-wrap gap-2 sm:gap-6" aria-label="Tabs Data Master">
        @foreach ($masterTabs as $tab)
            <a href="{{ $tab['href'] }}"
               class="inline-flex items-center border-b-2 py-3 px-1 text-sm font-medium transition-colors {{ request()->routeIs($tab['active']) ? 'border-forest-700 font-semibold text-forest-800' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
