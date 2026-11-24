<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('event.name', 'Welcome Party 2026'))</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        display: ['"Cinzel"', 'serif'],
                    },
                    colors: {
                        obsidian: '#09090b',
                        velvet: '#121217',
                        gold: {
                            400: '#fbbf24',
                            500: '#d4af37',
                            600: '#b48a1e',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        body {
            background-color: #09090b;
            background-image:
                radial-gradient(at 15% 10%, rgba(212, 175, 55, 0.08) 0px, transparent 50%),
                radial-gradient(at 85% 90%, rgba(153, 27, 27, 0.12) 0px, transparent 50%);
            min-height: 100vh;
        }
    </style>
    @stack('head')
</head>
<body class="text-zinc-100 antialiased selection:bg-gold-500 selection:text-black">

    {{-- Top Navigation --}}
    @auth
    <nav class="sticky top-0 z-40 border-b border-zinc-800/80 bg-zinc-950/85 backdrop-blur-md">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl border border-gold-500/40 bg-gold-500/10 font-display text-lg font-bold text-gold-400">
                    W
                </span>
                <div>
                    <div class="font-display text-sm font-bold tracking-wider text-gold-400">WELCOME PARTY</div>
                    <div class="text-[11px] text-zinc-400">{{ auth()->user()->isAdmin() ? 'Panel Panitia' : 'Dompet Peserta' }}</div>
                </div>
            </a>

            @if(auth()->user()->isAdmin())
            <div class="hidden items-center gap-1 md:flex">
                <a href="{{ route('admin.dashboard') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ request()->routeIs('admin.dashboard') ? 'bg-gold-500/15 text-gold-400' : 'text-zinc-400 hover:text-white' }}">Dashboard</a>
                <a href="{{ route('admin.checkin.index') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ request()->routeIs('admin.checkin.*') ? 'bg-gold-500/15 text-gold-400' : 'text-zinc-400 hover:text-white' }}">Check-In</a>
                <a href="{{ route('admin.pre-event.index') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ request()->routeIs('admin.pre-event.*') || request()->routeIs('admin.import.*') ? 'bg-gold-500/15 text-gold-400' : 'text-zinc-400 hover:text-white' }}">Pra-Acara</a>
                <a href="{{ route('admin.auction') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ request()->routeIs('admin.auction') ? 'bg-gold-500/15 text-gold-400' : 'text-zinc-400 hover:text-white' }}">Lelang</a>
                <a href="{{ route('admin.transactions') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ request()->routeIs('admin.transactions') ? 'bg-gold-500/15 text-gold-400' : 'text-zinc-400 hover:text-white' }}">Audit</a>
                <a href="{{ route('bigscreen') }}" target="_blank" class="ml-1 rounded-lg border border-gold-500/40 bg-gold-500/10 px-3 py-1.5 text-xs font-semibold text-gold-400 hover:bg-gold-500/20">Big Screen ↗</a>
            </div>
            @endif

            <div class="flex items-center gap-3">
                <div class="hidden text-right sm:block">
                    <div class="text-xs font-semibold text-zinc-200">{{ auth()->user()->name }}</div>
                    <div class="font-mono text-[11px] text-gold-400">#{{ auth()->user()->nim_code }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-xs font-medium text-zinc-300 hover:border-red-500/50 hover:text-red-400">
                        Keluar
                    </button>
                </form>
            </div>
        </div>

       {{-- Mobile Nav Admin --}}
        @if(auth()->user()->isAdmin())
        <div class="flex gap-1 overflow-x-auto border-t border-zinc-800/60 px-4 py-2 md:hidden">
            <a href="{{ route('admin.dashboard') }}" class="whitespace-nowrap rounded-lg px-2.5 py-1 text-xs {{ request()->routeIs('admin.dashboard') ? 'bg-gold-500/20 text-gold-400' : 'text-zinc-400' }}">Dashboard</a>
            <a href="{{ route('admin.checkin.index') }}" class="whitespace-nowrap rounded-lg px-2.5 py-1 text-xs {{ request()->routeIs('admin.checkin.*') ? 'bg-gold-500/20 text-gold-400' : 'text-zinc-400' }}">Check-In</a>
            <a href="{{ route('admin.pre-event.index') }}" class="whitespace-nowrap rounded-lg px-2.5 py-1 text-xs {{ request()->routeIs('admin.pre-event.*') || request()->routeIs('admin.import.*') ? 'bg-gold-500/20 text-gold-400' : 'text-zinc-400' }}">Pra-Acara</a>
            <a href="{{ route('admin.auction') }}" class="whitespace-nowrap rounded-lg px-2.5 py-1 text-xs {{ request()->routeIs('admin.auction') ? 'bg-gold-500/20 text-gold-400' : 'text-zinc-400' }}">Lelang</a>
            <a href="{{ route('admin.payment.settings') }}" class="whitespace-nowrap rounded-lg px-2.5 py-1 text-xs {{ request()->routeIs('admin.payment.*') ? 'bg-gold-500/20 text-gold-400' : 'text-zinc-400' }}">Pembayaran</a>
            <a href="{{ route('admin.transactions') }}" class="whitespace-nowrap rounded-lg px-2.5 py-1 text-xs {{ request()->routeIs('admin.transactions') ? 'bg-gold-500/20 text-gold-400' : 'text-zinc-400' }}">Audit</a>
            <a href="{{ route('bigscreen') }}" target="_blank" class="whitespace-nowrap rounded-lg border border-gold-500/40 px-2.5 py-1 text-xs text-gold-400">Leaderboard</a>
            <a href="{{ route('bigscreen.auction') }}" target="_blank" class="whitespace-nowrap rounded-lg border border-emerald-500/40 px-2.5 py-1 text-xs text-emerald-300">Layar Lelang</a>
        </div>
        @endif
    </nav>
    @endauth

    {{-- Main Content --}}
    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 {{ auth()->check() && auth()->user()->isParticipant() ? 'pb-24' : '' }}">
        @if(session('success'))
            <div class="mb-5 flex items-center justify-between rounded-xl border border-emerald-500/40 bg-emerald-950/50 px-4 py-3 text-sm text-emerald-200">
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-5 flex items-center justify-between rounded-xl border border-red-500/40 bg-red-950/50 px-4 py-3 text-sm text-red-200">
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="mb-5 rounded-xl border border-red-500/40 bg-red-950/50 px-4 py-3 text-sm text-red-200">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    {{-- Mobile Bottom Navigation untuk Peserta --}}
    @auth
    @if(auth()->user()->isParticipant())
    <div class="fixed bottom-0 left-0 right-0 z-40 border-t border-zinc-800 bg-zinc-950/95 backdrop-blur-md">
        <div class="mx-auto grid max-w-md grid-cols-3">
            <a href="{{ route('participant.dashboard') }}" class="flex flex-col items-center justify-center py-3 text-xs font-medium {{ request()->routeIs('participant.dashboard') ? 'text-gold-400' : 'text-zinc-400 hover:text-white' }}">
                <svg class="mb-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                Dompet
            </a>
            <a href="{{ route('transfer.create') }}" class="flex flex-col items-center justify-center py-3 text-xs font-medium {{ request()->routeIs('transfer.create') ? 'text-gold-400' : 'text-zinc-400 hover:text-white' }}">
                <svg class="mb-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                Kirim Saldo
            </a>
            <a href="{{ route('participant.catalog') }}" class="flex flex-col items-center justify-center py-3 text-xs font-medium {{ request()->routeIs('participant.catalog') ? 'text-gold-400' : 'text-zinc-400 hover:text-white' }}">
                <svg class="mb-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                Katalog
            </a>
        </div>
    </div>
    @endif
    @endauth

    <script>
        window.formatNum = (n) => new Intl.NumberFormat('id-ID').format(Number(n || 0));
        window.apiPost = async (url, payload = {}) => {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                if (res.status === 401 || res.status === 419) {
                    window.location.href = '{{ route("login") }}';
                }
                const firstErr = data.errors ? Object.values(data.errors)[0][0] : null;
                throw new Error(firstErr || data.message || 'Terjadi kesalahan pada server.');
            }
            return data;
        };
    </script>
    @stack('scripts')
</body>
</html>
