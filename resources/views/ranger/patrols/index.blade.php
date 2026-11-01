@extends('layouts.app')

@section('title', 'Tugas Patroli')

@section('content')
    {{-- Page Header --}}
    <div class="mb-6 flex items-center gap-3">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-forest-700 text-white shadow">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
            </svg>
        </div>
        <div>
            <h1 class="text-xl font-bold text-slate-900">Tugas Patroli</h1>
            <p class="text-sm text-slate-500">Daftar tugas patroli yang ditugaskan kepada Anda</p>
        </div>
    </div>

    {{-- Active patrol alert --}}
    @if ($activePatrol)
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-sm text-emerald-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            <span>Patroli sedang berlangsung.
                <a class="font-semibold underline underline-offset-2 hover:text-emerald-900" href="{{ route('ranger.patrols.show', $activePatrol) }}">
                    Lanjutkan {{ $activePatrol->code }}
                </a>
            </span>
        </div>
    @endif

    {{-- Patrol grid --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3">
        @forelse ($patrols as $patrol)
            @php
                $isInProgress = $patrol->status === \App\Enums\PatrolStatus::InProgress;
                $isCompleted  = $patrol->status === \App\Enums\PatrolStatus::Completed;
            @endphp
            <div class="group flex flex-col rounded-2xl border border-slate-200 bg-white shadow-sm transition-all duration-200 hover:border-forest-300 hover:shadow-md">
                {{-- Card top accent --}}
                <div class="h-1 w-full rounded-t-2xl {{ $isInProgress ? 'bg-emerald-500' : ($isCompleted ? 'bg-slate-300' : 'bg-amber-400') }}"></div>

                <div class="flex flex-1 flex-col gap-3 p-5">
                    {{-- Header row --}}
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-mono text-xs font-medium text-slate-400">{{ $patrol->code }}</p>
                        {{-- Status badge --}}
                        @if ($isInProgress)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                                Berlangsung
                            </span>
                        @elseif ($isCompleted)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Selesai
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Ditugaskan
                            </span>
                        @endif
                    </div>

                    {{-- Area name --}}
                    <h2 class="text-base font-semibold leading-snug text-slate-800">{{ $patrol->forestArea->name }}</h2>

                    {{-- Meta info --}}
                    <div class="space-y-1.5 text-sm text-slate-500">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            <span>{{ $patrol->scheduled_at->timezone(config('app.timezone'))->format('d M Y, H:i') }} {{ config('app.timezone') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span>{{ $patrol->patrol_checkpoints_count }} checkpoint</span>
                        </div>
                    </div>

                    {{-- CTA --}}
                    <div class="mt-auto pt-3">
                        <a href="{{ route('ranger.patrols.show', $patrol) }}"
                           class="inline-flex w-full items-center justify-center gap-2 rounded-lg {{ $isInProgress ? 'bg-forest-700 text-white hover:bg-forest-800' : 'border border-slate-200 bg-slate-50 text-forest-700 hover:bg-forest-50 hover:border-forest-300' }} px-4 py-2 text-sm font-medium transition-colors duration-150">
                            @if ($isInProgress)
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                Lanjutkan Patroli
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Buka Detail
                            @endif
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full flex flex-col items-center gap-3 rounded-2xl border border-dashed border-slate-200 bg-white p-10 text-center text-slate-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                </svg>
                <p class="font-medium">Belum ada tugas patroli untuk Anda.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-5">{{ $patrols->links() }}</div>
@endsection
