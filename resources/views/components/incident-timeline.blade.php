@props(['histories'])

<div class="flow-root">
    <ul role="list" class="-mb-8">
        @forelse ($histories as $history)
            <li>
                <div class="relative pb-8">
                    @if (! $loop->last)
                        <span class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-slate-200" aria-hidden="true"></span>
                    @endif
                    <div class="relative flex space-x-3">
                        <div>
                            @php
                                $statusName = $history->toStatus?->name ?? 'Status';
                                $dotColor = match ($statusName) {
                                    'Reported' => 'bg-slate-400',
                                    'Under Verification' => 'bg-amber-500',
                                    'Verified', 'Assigned' => 'bg-blue-500',
                                    'Investigation/Response' => 'bg-orange-500',
                                    'Monitoring' => 'bg-purple-500',
                                    'Resolved' => 'bg-emerald-600',
                                    'Rejected' => 'bg-red-500',
                                    default => 'bg-slate-400',
                                };
                            @endphp
                            <span class="flex h-8 w-8 items-center justify-center rounded-full {{ $dotColor }} ring-4 ring-white text-white text-xs font-bold">
                                {{ substr($statusName, 0, 1) }}
                            </span>
                        </div>
                        <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">
                                    {{ $statusName }}
                                    <span class="text-xs font-normal text-slate-500">oleh {{ $history->changedBy?->name ?? 'Sistem' }}</span>
                                </p>
                                @if ($history->note)
                                    <p class="mt-1 text-sm text-slate-600 bg-slate-50 p-2 rounded border border-slate-100">{{ $history->note }}</p>
                                @endif
                            </div>
                            <div class="whitespace-nowrap text-right text-xs text-slate-400">
                                {{ $history->changed_at?->format('d M Y, H:i') }} WIB
                            </div>
                        </div>
                    </div>
                </div>
            </li>
        @empty
            <li class="text-sm text-slate-500">Belum ada riwayat perubahan status.</li>
        @endforelse
    </ul>
</div>

