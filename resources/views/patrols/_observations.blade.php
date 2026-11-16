@forelse ($patrol->observations->sortByDesc('id') as $observation)
    <article data-observation-id="{{ $observation->id }}" class="space-y-2 rounded-xl border border-slate-200 p-4">
        <h3 class="font-semibold text-forest-800">{{ $observation->category->value }}</h3>
        <p class="whitespace-pre-wrap break-words text-sm text-slate-800">{{ $observation->description }}</p>
        <p class="text-xs text-slate-500">Waktu observasi: {{ $observation->observed_at->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</p>
        <p class="break-words text-xs text-slate-500">Lokasi GPS: {{ $observation->latitude }}, {{ $observation->longitude }}</p>
        @if ($observation->location_recorded_at)
            <p class="text-xs text-slate-500">Lokasi diambil: {{ $observation->location_recorded_at->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</p>
        @endif
        @foreach ($observation->evidences as $evidence)
            <a href="{{ route('evidence.show', $evidence) }}" target="_blank" rel="noopener" class="block">
                <img src="{{ route('evidence.show', $evidence) }}" alt="Foto observasi {{ $observation->category->value }}" loading="lazy" class="max-h-64 rounded-lg object-contain">
            </a>
        @endforeach
        @if (auth()->user()->isRanger() && $patrol->ranger_id === auth()->id())
            <a href="{{ route('ranger.incidents.create', ['observation' => $observation->id]) }}" class="inline-flex min-h-11 items-center text-sm font-medium text-forest-700 underline">Laporkan insiden dari temuan</a>
        @endif
    </article>
@empty
    <p class="text-sm text-slate-500">Belum ada observasi lapangan.</p>
@endforelse
