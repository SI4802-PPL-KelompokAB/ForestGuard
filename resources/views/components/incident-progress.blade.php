@props(['incident'])

@php
    $statuses = ['Reported', 'Under Verification', 'Verified', 'Assigned', 'Investigation/Response', 'Monitoring', 'Resolved'];
    $currentStatus = $incident->caseStatus?->name ?? 'Reported';
    $isRejected = $currentStatus === 'Rejected';
    $currentIndex = $isRejected ? 1 : array_search($currentStatus, $statuses);
    if ($currentIndex === false) {
        $currentIndex = 0;
    }
@endphp

<div class="space-y-2">
    <div class="grid grid-cols-7 gap-1">
        @foreach ($statuses as $index => $statusName)
            @php
                if ($isRejected) {
                    $barColor = $index <= 1 ? 'bg-red-500' : 'bg-slate-200';
                } else {
                    $barColor = $index <= $currentIndex ? 'bg-forest-600' : 'bg-slate-200';
                }
            @endphp
            <div class="h-2 rounded-full {{ $barColor }} transition-all"></div>
        @endforeach
    </div>
    <div class="flex items-center justify-between text-xs text-slate-500">
        <span class="font-semibold text-slate-800">Status: {{ $currentStatus }}</span>
        <span>
            @if ($isRejected)
                <span class="text-red-600 font-medium">Ditolak · Kasus Ditutup</span>
            @elseif ($currentIndex < 6)
                Berikutnya: {{ $statuses[$currentIndex + 1] }}
            @else
                <span class="text-forest-700 font-medium">Kasus Selesai</span>
            @endif
        </span>
    </div>
</div>

