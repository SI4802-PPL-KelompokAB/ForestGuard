@php
    $nextCheckpoint = $patrol->patrolCheckpoints->firstWhere('visited_at', null);
    $visitedCount = $patrol->patrolCheckpoints->whereNotNull('visited_at')->count();
    $canRecord = auth()->user()->isRanger() && $patrol->ranger_id === auth()->id()
        && $patrol->status === \App\Enums\PatrolStatus::InProgress;
@endphp
<div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
    <h2 class="font-semibold text-slate-800">Rencana Checkpoint</h2>
    <span data-checkpoint-progress class="text-xs text-slate-500">{{ $visitedCount }}/{{ $patrol->patrolCheckpoints->count() }} dikunjungi</span>
</div>
<ol class="divide-y divide-slate-100">
    @forelse ($patrol->patrolCheckpoints as $planned)
        <li data-checkpoint-id="{{ $planned->id }}" class="flex flex-wrap items-center gap-3 px-5 py-4">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600">{{ $planned->sequence }}</span>
            <div class="min-w-0 flex-1 space-y-1">
                <p class="text-sm font-medium text-slate-800">{{ $planned->checkpoint->name }}</p>
                <p class="text-xs text-slate-500">{{ $planned->checkpoint->code }}</p>
                @if ($planned->visited_at)
                    <p class="text-xs text-emerald-700">Sudah dikunjungi · {{ $planned->visited_at->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</p>
                    <p class="break-words text-xs text-slate-500">GPS kunjungan: {{ $planned->latitude }}, {{ $planned->longitude }}</p>
                @else
                    <p class="text-xs text-slate-500">Belum dikunjungi</p>
                    @if ($nextCheckpoint?->id === $planned->id)
                        <p data-next-checkpoint class="text-xs font-semibold text-forest-700">Checkpoint berikutnya</p>
                    @endif
                @endif
            </div>
            @if ($canRecord && $planned->visited_at === null)
                <form method="POST" action="{{ route('ranger.patrols.checkpoints.visit', [$patrol, $planned]) }}" data-checkpoint-form>
                    @csrf
                    <x-button class="min-h-11">Catat</x-button>
                </form>
            @endif
        </li>
    @empty
        <li class="p-5 text-sm text-slate-500">Belum ada checkpoint penugasan.</li>
    @endforelse
</ol>
