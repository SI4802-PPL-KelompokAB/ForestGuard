@extends('layouts.app')

@section('title', 'Penugasan patroli')

@section('content')
    <h1 class="mb-5 text-xl font-semibold text-forest-900">Penugasan patroli</h1>
    <div class="grid min-w-0 gap-6 xl:grid-cols-3">
        <x-card class="min-w-0 xl:col-span-2">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr><th class="p-2">Kode</th><th class="p-2">Ranger</th><th class="p-2">Area</th><th class="p-2">Jadwal</th><th class="p-2">Status</th><th class="p-2">Jarak</th></tr></thead>
                    <tbody>
                    @forelse ($patrols as $patrol)
                        <tr class="border-t border-slate-200">
                            <td class="p-2"><a class="text-forest-700 underline" href="{{ route('admin.patrols.show', $patrol) }}">{{ $patrol->code }}</a></td>
                            <td class="p-2">{{ $patrol->ranger->name }}</td><td class="p-2">{{ $patrol->forestArea->name }}</td>
                            <td class="p-2">{{ $patrol->scheduled_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                            <td class="p-2">{{ $patrol->status->value }}</td><td class="p-2">{{ $patrol->total_distance_km === null ? '—' : $patrol->total_distance_km.' km' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4">Belum ada penugasan patroli.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $patrols->links() }}</div>
        </x-card>
        <x-card>
            <h2 class="mb-4 font-semibold">Penugasan patroli baru</h2>
            @if ($rangers->isEmpty())
                <p>Belum ada Ranger aktif. <a class="underline" href="{{ route('admin.users.index') }}">Kelola pengguna</a>.</p>
            @endif
            @if ($areas->isEmpty())<p>Belum ada area hutan tersedia.</p>@endif
            <form method="POST" action="{{ route('admin.patrols.store') }}" data-patrol-assignment class="space-y-4">
                @csrf
                <div>
                    <label for="ranger_id" class="block text-sm font-medium">Ranger</label>
                    <select id="ranger_id" name="ranger_id" required aria-describedby="ranger_id-error" class="w-full rounded-lg border border-slate-300 p-2">
                        <option value="">Pilih Ranger</option>
                        @foreach ($rangers as $ranger)<option value="{{ $ranger->id }}" @selected(old('ranger_id') == $ranger->id)>{{ $ranger->name }}</option>@endforeach
                    </select>
                    @error('ranger_id')<p id="ranger_id-error" class="text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="forest_area_id" class="block text-sm font-medium">Area hutan</label>
                    <select id="forest_area_id" name="forest_area_id" required aria-describedby="forest_area_id-error" class="w-full rounded-lg border border-slate-300 p-2">
                        <option value="">Pilih area</option>
                        @foreach ($areas as $area)<option value="{{ $area->id }}" data-checkpoints-url="{{ route('admin.patrols.checkpoints', $area) }}" @selected(old('forest_area_id') == $area->id)>{{ $area->name }}</option>@endforeach
                    </select>
                    @error('forest_area_id')<p id="forest_area_id-error" class="text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <x-input name="scheduled_at" type="datetime-local" label="Jadwal ({{ config('app.timezone') }})" required aria-describedby="scheduled_at-error" />
                @error('scheduled_at')<p id="scheduled_at-error" class="text-sm text-red-700">{{ $message }}</p>@enderror
                <fieldset aria-describedby="checkpoint_ids-error">
                    <legend class="text-sm font-medium">Checkpoint wajib kunjung</legend>
                    <div data-checkpoints class="space-y-2 py-2">
                        @forelse ($checkpoints as $checkpoint)
                            <label class="flex items-start gap-2"><input type="checkbox" name="checkpoint_ids[]" value="{{ $checkpoint->id }}" @checked(in_array($checkpoint->id, (array) old('checkpoint_ids', [])))><span>{{ $loop->iteration }}. {{ $checkpoint->code }} — {{ $checkpoint->name }}</span></label>
                        @empty
                            <p>Pilih area dengan checkpoint tersedia.</p>
                        @endforelse
                    </div>
                    <p data-checkpoint-state role="status" class="text-sm"></p>
                    <p id="checkpoint_ids-error" class="text-sm text-red-700">{{ $errors->first('checkpoint_ids') ?: $errors->first('checkpoint_ids.*') }}</p>
                </fieldset>
                <x-button data-assign-submit :disabled="$rangers->isEmpty() || $areas->isEmpty() || $checkpoints->isEmpty()">Tugaskan patroli</x-button>
            </form>
        </x-card>
    </div>
@endsection
