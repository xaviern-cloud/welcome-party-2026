@extends('layouts.app')

@section('title', 'Dashboard Panitia — Welcome Party 2026')

@section('content')
<div
    x-data="adminControl({
        frozen: {{ $frozen ? 'true' : 'false' }},
        bs: @js($bigScreen),
        participants: @js($participants),
        freezeUrl: '{{ route("admin.freeze") }}',
        bsActivateUrl: '{{ route("admin.bigscreen.activate") }}',
        bsRevealUrl: '{{ route("admin.bigscreen.reveal") }}',
        bsHideUrl: '{{ route("admin.bigscreen.hide") }}'
    })"
    class="space-y-6"
>
    {{-- Header & Export --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">Command Center Panitia</h1>
            <p class="text-xs text-zinc-400">Kontrol transaksi, lelang, perangkat peserta, dan layar proyektor.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.export.balances', ['format' => 'xlsx']) }}" class="rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/20">
                ↓ Export Rekap (.XLSX)
            </a>
            <a href="{{ route('admin.export.balances', ['format' => 'csv']) }}" class="rounded-xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs font-medium text-zinc-300 hover:text-white">
                .CSV
            </a>
        </div>
    </div>

    {{-- Toast Notification --}}
    <div x-show="toast" x-cloak class="rounded-xl border border-gold-500/50 bg-gold-500/15 px-4 py-3 text-sm text-gold-200" x-text="toast"></div>

    {{-- Statistik Utama --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-4">
            <div class="text-xs text-zinc-400">Kehadiran Peserta</div>
            <div class="mt-1 font-mono text-2xl font-bold text-white">
                {{ $stats['checked_in'] }} <span class="text-sm font-normal text-zinc-500">/ {{ $stats['total'] }}</span>
            </div>
        </div>
        <div class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-4">
            <div class="text-xs text-zinc-400">Total Uang Beredar</div>
            <div class="mt-1 font-mono text-2xl font-bold text-gold-400">{{ number_format($stats['circulation'], 0, ',', '.') }}</div>
        </div>
        <div class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-4">
            <div class="text-xs text-zinc-400">Total Transfer P2P</div>
            <div class="mt-1 font-mono text-2xl font-bold text-white">{{ number_format($stats['transfers'], 0, ',', '.') }}</div>
        </div>
        <div class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-4">
            <div class="text-xs text-zinc-400">Transaksi Lelang ({{ $stats['auctions'] }}x)</div>
            <div class="mt-1 font-mono text-2xl font-bold text-amber-400">{{ number_format($stats['auction_total'], 0, ',', '.') }}</div>
        </div>
    </div>

    {{-- Panel Kontrol Utama: Global Freeze & Big Screen --}}
    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Global Freeze --}}
        <div class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-base font-bold text-white">Global Freeze Transaksi</h2>
                    <p class="mt-1 text-xs text-zinc-400">Bekukan seluruh transfer antar peserta saat sesi lelang dimulai.</p>
                </div>
                <span
                    class="rounded-full px-3 py-1 text-xs font-bold"
                    :class="frozen ? 'bg-red-500/20 text-red-400 border border-red-500/40' : 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40'"
                    x-text="frozen ? 'TERKUNCI (FREEZE)' : 'AKTIF NORMAL'"
                ></span>
            </div>

            <div class="mt-5 flex gap-3">
                <button
                    type="button"
                    @click="setFreeze(true)"
                    :disabled="frozen || busy"
                    class="flex-1 rounded-xl bg-red-600 py-2.5 text-xs font-bold text-white transition hover:bg-red-500 disabled:opacity-40"
                >
                    Bekukan Transaksi (Freeze)
                </button>
                <button
                    type="button"
                    @click="setFreeze(false)"
                    :disabled="!frozen || busy"
                    class="flex-1 rounded-xl bg-emerald-600 py-2.5 text-xs font-bold text-white transition hover:bg-emerald-500 disabled:opacity-40"
                >
                    Buka Kembali (Unfreeze)
                </button>
            </div>
        </div>

        {{-- Kontrol Layar Proyektor Big Screen --}}
        <div class="rounded-2xl border border-gold-500/30 bg-zinc-900/80 p-6">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-base font-bold text-gold-400">Kontrol Big Screen Leaderboard</h2>
                    <p class="mt-1 text-xs text-zinc-400">Tampilkan peringkat dari bawah ke atas secara bertahap di proyektor.</p>
                </div>
                <a href="{{ route('bigscreen') }}" target="_blank" class="text-xs font-semibold text-gold-400 underline">Buka Layar ↗</a>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-xs text-zinc-400">Top:</label>
                    <input type="number" x-model.number="bsSize" min="1" max="20" class="w-16 rounded-lg border border-zinc-700 bg-zinc-950 px-2.5 py-1.5 text-center font-mono text-xs text-white">
                </div>
                <label class="flex items-center gap-2 text-xs text-zinc-300">
                    <input type="checkbox" x-model="bsShowBalance" class="rounded border-zinc-700 bg-zinc-950 text-gold-500">
                    Tampilkan Nominal Saldo
                </label>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    @click="activateBs()"
                    :disabled="busy"
                    class="rounded-xl bg-gold-500 px-4 py-2 text-xs font-bold text-zinc-950 hover:bg-gold-400"
                >
                    1. Aktifkan / Reset Leaderboard
                </button>
                <button
                    type="button"
                    @click="revealNext()"
                    :disabled="!bs.active || bs.revealed_count >= bs.total || busy"
                    class="rounded-xl bg-amber-600 px-4 py-2 text-xs font-bold text-white hover:bg-amber-500 disabled:opacity-40"
                >
                    2. Reveal Berikutnya (<span x-text="bs.revealed_count"></span>/<span x-text="bs.total"></span>)
                </button>
                <button
                    type="button"
                    @click="hideBs()"
                    :disabled="!bs.active || busy"
                    class="rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-300 hover:text-white disabled:opacity-40"
                >
                    Tutup Layar
                </button>
            </div>
        </div>
    </div>

    {{-- Daftar Peserta & Reset Perangkat --}}
    <div class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-white">Manajemen Perangkat & Saldo Peserta</h2>
                <p class="text-xs text-zinc-400">Gunakan tombol Reset Perangkat jika peserta ganti HP atau terkunci sesi tunggal.</p>
            </div>
            <input
                type="text"
                x-model="filterQuery"
                placeholder="Cari NIM / Nama / Kelompok..."
                class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3.5 py-2 text-xs text-white sm:w-64 focus:border-gold-500 focus:outline-none"
            >
        </div>

        <div class="max-h-96 overflow-auto rounded-xl border border-zinc-800">
            <table class="w-full text-left text-xs">
                <thead class="sticky top-0 bg-zinc-950 text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">NIM</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Kelompok</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Saldo</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/70">
                    <template x-for="p in filteredParticipants()" :key="p.nim_code">
                        <tr class="hover:bg-zinc-900/90">
                            <td class="px-4 py-2.5 font-mono font-bold text-gold-400" x-text="p.nim_code"></td>
                            <td class="px-4 py-2.5 font-medium text-white" x-text="p.name"></td>
                            <td class="px-4 py-2.5 text-zinc-400" x-text="p.group_name || '-'"></td>
                            <td class="px-4 py-2.5">
                                <span
                                    class="rounded-md px-2 py-0.5 text-[11px] font-semibold"
                                    :class="p.is_checked_in ? 'bg-emerald-500/15 text-emerald-400' : 'bg-zinc-800 text-zinc-400'"
                                    x-text="p.is_checked_in ? ('Hadir #' + p.arrival_order) : 'Belum Hadir'"
                                ></span>
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono font-semibold text-zinc-200" x-text="formatNum(p.balance)"></td>
                            <td class="px-4 py-2.5 text-right">
                                <button
                                    type="button"
                                    x-show="p.has_session"
                                    @click="resetDevice(p)"
                                    class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-2.5 py-1 text-[11px] font-semibold text-amber-300 hover:bg-amber-500/20"
                                >
                                    Reset Perangkat
                                </button>
                                <span x-show="!p.has_session" class="text-[11px] text-zinc-600">Tidak login</span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function adminControl(cfg) {
    return {
        frozen: cfg.frozen,
        bs: cfg.bs,
        bsSize: cfg.bs.total || 10,
        bsShowBalance: cfg.bs.show_balance ?? true,
        participants: cfg.participants,
        filterQuery: '',
        busy: false,
        toast: '',

        filteredParticipants() {
            const q = this.filterQuery.trim().toLowerCase();
            if (!q) return this.participants;
            return this.participants.filter(p =>
                p.nim_code.toLowerCase().includes(q) ||
                p.name.toLowerCase().includes(q) ||
                (p.group_name || '').toLowerCase().includes(q)
            );
        },
        async setFreeze(val) {
            this.busy = true;
            try {
                const res = await window.apiPost(cfg.freezeUrl, { frozen: val });
                this.frozen = res.frozen;
                this.toast = res.message;
            } catch (e) { this.toast = e.message; }
            finally { this.busy = false; }
        },
        async activateBs() {
            this.busy = true;
            try {
                const res = await window.apiPost(cfg.bsActivateUrl, { size: this.bsSize, show_balance: this.bsShowBalance });
                this.bs = res.state;
                this.toast = res.message;
            } catch (e) { this.toast = e.message; }
            finally { this.busy = false; }
        },
        async revealNext() {
            this.busy = true;
            try {
                const res = await window.apiPost(cfg.bsRevealUrl, { expected_revealed: this.bs.revealed_count });
                this.bs = res.state;
                this.toast = res.message;
            } catch (e) { this.toast = e.message; }
            finally { this.busy = false; }
        },
        async hideBs() {
            this.busy = true;
            try {
                const res = await window.apiPost(cfg.bsHideUrl, {});
                this.bs = res.state;
                this.toast = res.message;
            } catch (e) { this.toast = e.message; }
            finally { this.busy = false; }
        },
        async resetDevice(p) {
            if (!confirm(`Reset sesi perangkat milik ${p.name} (${p.nim_code})?`)) return;
            try {
                const res = await window.apiPost(`/admin/participants/${p.nim_code}/reset-device`, {});
                p.has_session = false;
                this.toast = res.message;
            } catch (e) { this.toast = e.message; }
        }
    };
}
</script>
@endpush
