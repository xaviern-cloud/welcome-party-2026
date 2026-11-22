<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard — {{ $event }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700;800;900&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        [x-cloak] { display: none !important; }
        body {
            background: radial-gradient(circle at 50% 0%, #271c08 0%, #09090b 65%);
            font-family: 'Plus Jakarta Sans', sans-serif;
            overflow-x: hidden;
        }
        .font-display { font-family: 'Cinzel', serif; }
        @keyframes revealPop {
            0% { opacity: 0; transform: scale(0.92) translateY(18px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }
        .animate-reveal { animation: revealPop 0.55s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    </style>
</head>
<body class="min-h-screen text-white">

<div
    x-data="bigScreenApp('{{ $stateUrl }}', {{ (int) $pollMs }})"
    x-init="init()"
    class="mx-auto flex min-h-screen max-w-6xl flex-col justify-between px-8 py-8"
>
    {{-- Header --}}
    <header class="text-center">
        <div class="inline-block rounded-full border border-amber-500/40 bg-amber-500/10 px-4 py-1 text-xs font-bold uppercase tracking-[0.25em] text-amber-300">
            {{ $tagline }}
        </div>
        <h1 class="font-display mt-3 text-4xl font-black tracking-wider text-amber-400 drop-shadow md:text-5xl">
            {{ $event }}
        </h1>
    </header>

    {{-- Mode Standby (Belum Diaktifkan Panitia) --}}
    <template x-if="!isActive()">
        <div class="my-auto py-16 text-center">
            <div class="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-3xl border border-amber-500/40 bg-amber-500/10 font-display text-5xl font-black text-amber-400 shadow-2xl shadow-amber-500/20">
                W
            </div>
            <h2 class="font-display text-3xl font-bold tracking-widest text-zinc-200 md:text-4xl">
                LIVE AUCTION & LEADERBOARD
            </h2>
            <p class="mt-3 text-lg text-zinc-400">Menunggu pengumuman resmi dari panggung utama...</p>
        </div>
    </template>

    {{-- Mode Leaderboard Aktif --}}
    <template x-if="isActive()">
        <div class="my-6 flex-1">
            <div class="mb-5 flex items-center justify-between border-b border-zinc-800 pb-3">
                <span class="text-sm font-bold uppercase tracking-widest text-amber-400">
                    TOP <span x-text="state.total"></span> SULTAN LEADERBOARD
                </span>
                <span class="font-mono text-sm text-zinc-400">
                    Terungkap: <strong class="text-white" x-text="state.revealed_count"></strong> / <span x-text="state.total"></span>
                </span>
            </div>

            <template x-if="(state.entries || []).length === 0">
                <div class="py-20 text-center font-display text-2xl tracking-widest text-zinc-500">
                    BERSIAP UNTUK HITUNG MUNDUR PERINGKAT #<span x-text="state.total"></span>...
                </div>
            </template>

            <div class="grid gap-3">
                <template x-for="row in (state.entries || [])" :key="row.rank">
                    <div
                        class="animate-reveal flex items-center justify-between rounded-2xl border px-6 py-4 shadow-xl"
                        :class="{
                            'border-amber-400 bg-gradient-to-r from-amber-500/25 via-zinc-900 to-amber-500/20 scale-[1.01]': row.rank === 1,
                            'border-zinc-400/60 bg-zinc-900/90': row.rank === 2,
                            'border-amber-700/60 bg-zinc-900/85': row.rank === 3,
                            'border-zinc-800 bg-zinc-900/70': row.rank > 3
                        }"
                    >
                        <div class="flex items-center gap-5">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-xl font-display text-xl font-black"
                                :class="{
                                    'bg-amber-400 text-black': row.rank === 1,
                                    'bg-zinc-300 text-black': row.rank === 2,
                                    'bg-amber-700 text-white': row.rank === 3,
                                    'bg-zinc-800 text-amber-400': row.rank > 3
                                }"
                                x-text="'#' + row.rank"
                            ></div>
                            <div>
                                <div class="text-xl font-extrabold text-white md:text-2xl" x-text="row.name"></div>
                                <div class="font-mono text-xs uppercase tracking-wider text-zinc-400" x-text="row.group || row.group_name || 'Peserta'"></div>
                            </div>
                        </div>

                        <div x-show="row.balance !== null && row.balance !== undefined" class="text-right">
                            <div class="font-display text-2xl font-black text-amber-400 md:text-3xl" x-text="new Intl.NumberFormat('id-ID').format(row.balance || 0)"></div>
                            <div class="text-[11px] font-bold uppercase tracking-widest text-zinc-500">COINS</div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>

    <footer class="text-center text-xs tracking-widest text-zinc-600">
        WELCOME PARTY CURRENCY & AUCTION SYSTEM
    </footer>
</div>

<script>
function bigScreenApp(url, pollMs) {
    return {
        state: { mode: 'hidden', active: false, total: 10, revealed_count: 0, entries: [] },
        isActive() {
            return this.state.mode === 'leaderboard' || Boolean(this.state.active);
        },
        init() {
            const load = async () => {
                try {
                    const res = await fetch(url, { cache: 'no-store' });
                    if (res.ok) this.state = await res.json();
                } catch (e) {}
            };
            load();
            setInterval(load, pollMs);
        }
    };
}
</script>
</body>
</html>