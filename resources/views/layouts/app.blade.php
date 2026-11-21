<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#166534">
    <meta name="mobile-web-app-capable" content="yes">
    <title>@yield('title', 'ForestGuard')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
    @php
        $user = auth()->user();
        $navItems = [];
        if ($user?->isCitizen()) {
            $navItems[] = ['label' => 'Laporan Saya', 'href' => route('citizen.home'), 'active' => 'citizen.home'];
            $navItems[] = ['label' => 'Buat Laporan', 'href' => route('citizen.incidents.create'), 'active' => 'citizen.incidents.create'];
        }
        if ($user?->isRanger()) {
            $navItems[] = ['label' => 'Beranda Ranger', 'href' => route('ranger.home'), 'active' => 'ranger.home'];
            $navItems[] = ['label' => 'Patroli', 'href' => route('ranger.patrols.index'), 'active' => 'ranger.patrols.*'];
            $navItems[] = ['label' => 'Lapor Insiden', 'href' => route('ranger.incidents.create'), 'active' => 'ranger.incidents.*'];
        }
        if ($user?->isAdmin()) {
            $navItems[] = ['label' => 'Dasbor', 'href' => route('admin.dashboard'), 'active' => 'admin.dashboard'];
            $navItems[] = ['label' => 'Kasus', 'href' => route('admin.incidents.index'), 'active' => 'admin.incidents.*'];
            $navItems[] = ['label' => 'Pengguna', 'href' => route('admin.users.index'), 'active' => 'admin.users.*'];
            $navItems[] = ['label' => 'Patroli', 'href' => route('admin.patrols.index'), 'active' => 'admin.patrols.*'];
            $navItems[] = ['label' => 'Data Master', 'href' => route('admin.master.forest-areas.index'), 'active' => 'admin.master.*'];
        }
    @endphp

    <div class="min-h-dvh md:flex">
        <aside class="hidden w-64 shrink-0 bg-forest-900 text-white md:flex md:flex-col">
            <div class="px-5 py-6">
                <p class="text-lg font-semibold">ForestGuard</p>
                <p class="mt-1 text-sm text-forest-100">{{ $user?->name }}</p>
            </div>
            <nav class="flex flex-1 flex-col gap-1 px-3">
                @foreach ($navItems as $item)
                    <a href="{{ $item['href'] }}"
                       class="rounded-lg px-3 py-2 text-sm {{ request()->routeIs($item['active']) ? 'bg-forest-700' : 'hover:bg-forest-800' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
            <form method="POST" action="{{ route('logout') }}" class="p-3">
                @csrf
                <button type="submit" class="w-full rounded-lg bg-forest-800 px-3 py-2 text-sm hover:bg-forest-700">Keluar</button>
            </form>
        </aside>

        <div class="flex min-h-dvh min-w-0 flex-1 flex-col pb-20 md:pb-0">
            <header class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 md:hidden">
                <div>
                    <p class="text-sm font-semibold text-forest-800">ForestGuard</p>
                    <p class="text-xs text-slate-500">{{ $user?->name }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-forest-700 px-3 py-1.5 text-sm text-white">Keluar</button>
                </form>
            </header>

            <main class="flex-1 px-4 py-5 md:px-8">
                @if ($errors->any())
                    <x-alert type="error" class="mb-4">{{ $errors->first() }}</x-alert>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    @if (session('status') || session('warning') || session('error') || $errors->any())
        <div id="app-toast"
             class="fixed inset-0 z-[9999] flex items-center justify-center px-4"
             role="status"
             aria-live="polite">
            <div class="w-full max-w-md rounded-xl border bg-white p-5 text-center shadow-2xl
                        {{ session('warning') || session('error') || $errors->any() ? 'border-amber-200' : 'border-emerald-200' }}">
                <p class="text-base font-semibold text-slate-900">
                    {{ session('warning') || session('error') || $errors->any() ? 'Perhatian' : 'Berhasil' }}
                </p>
                <p class="mt-2 text-sm text-slate-600">
                    {{ session('status') ?? session('warning') ?? session('error') ?? $errors->first() }}
                </p>
                <button type="button"
                        data-toast-close
                        class="mt-4 rounded-lg bg-forest-700 px-4 py-2 text-sm font-semibold text-white hover:bg-forest-800">
                    Tutup
                </button>
            </div>
        </div>
    @endif

    <div id="crud-confirmation"
         class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-950/40 px-4"
         role="dialog"
         aria-modal="true"
         aria-labelledby="crud-confirmation-title">
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
            <h2 id="crud-confirmation-title" class="text-lg font-semibold text-slate-900">Konfirmasi tindakan</h2>
            <p id="crud-confirmation-message" class="mt-2 text-sm text-slate-600"></p>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button"
                        data-confirm-cancel
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Batal
                </button>
                <button type="button"
                        data-confirm-continue
                        class="rounded-lg bg-forest-700 px-4 py-2 text-sm font-semibold text-white hover:bg-forest-800">
                    Ya, lanjutkan
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toast = document.querySelector('#app-toast');
            const closeToast = () => toast?.remove();

            document.querySelector('[data-toast-close]')?.addEventListener('click', closeToast);
            if (toast) {
                window.setTimeout(closeToast, 7000);
            }

            const modal = document.querySelector('#crud-confirmation');
            const message = document.querySelector('#crud-confirmation-message');
            const cancel = document.querySelector('[data-confirm-cancel]');
            const proceed = document.querySelector('[data-confirm-continue]');
            let pendingForm = null;

            const closeModal = () => {
                pendingForm = null;
                modal?.classList.add('hidden');
                modal?.classList.remove('flex');
            };

            document.querySelectorAll('form[data-confirm]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    if (form.dataset.confirmed === 'true') {
                        delete form.dataset.confirmed;
                        return;
                    }

                    event.preventDefault();
                    pendingForm = form;
                    message.textContent = form.dataset.confirm;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    proceed.focus();
                });
            });

            cancel?.addEventListener('click', closeModal);
            proceed?.addEventListener('click', () => {
                if (!pendingForm) {
                    return;
                }

                const form = pendingForm;
                closeModal();
                if (!form.reportValidity()) {
                    return;
                }

                form.dataset.confirmed = 'true';
                form.requestSubmit();
            });

            modal?.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });
        });
    </script>

    <nav class="fixed inset-x-0 bottom-0 border-t border-slate-200 bg-white md:hidden">
        <div class="mx-auto flex max-w-lg">
            @foreach ($navItems as $item)
                <a href="{{ $item['href'] }}"
                   class="flex-1 py-3 text-center text-sm {{ request()->routeIs($item['active']) ? 'font-semibold text-forest-700' : 'text-slate-500' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
    </nav>
</body>
</html>
