@extends('layouts.app')

@section('title', 'Dompet Saya — Welcome Party 2026')

@section('content')
<div
    x-data="participantWallet({
        balance: {{ (int) $user->balance }},
        frozen: {{ $frozen ? 'true' : 'false' }},
        transactions: @js($transactions),
        stateUrl: '{{ $stateUrl }}'
    })"
    x-init="init()"
    class="mx-auto max-w-xl space-y-5"
>
    {{-- Banner Freeze --}}
    <div x-show="frozen" x-cloak class="rounded-2xl border border-red-500/50 bg-red-950/60 p-4 text-center">
        <div class="text-sm font-bold text-red-300">TRANSAKSI SEDANG DIBEKUKAN PANITIA</div>
        <div class="mt-0.5 text-xs text-red-200/80">Sesi lelang atau perhitungan akhir sedang berlangsung. Transfer antar peserta dinonaktifkan sementara.</div>
    </div>

    {{-- Kartu Saldo Utama --}}
    <div class="relative overflow-hidden rounded-3xl border border-gold-500/40 bg-gradient-to-br from-zinc-900 via-zinc-950 to-amber-950/30 p-6 shadow-2xl">
        <div class="flex items-start justify-between">
            <div>
                <div class="text-xs font-medium uppercase tracking-widest text-gold-400">{{ $user->group_name ?? 'Peserta' }}</div>
                <h1 class="mt-0.5 text-lg font-bold text-white">{{ $user->name }}</h1>
                <div class="mt-1 inline-flex items-center gap-2 rounded-full border border-zinc-700/80 bg-zinc-900/90 px-2.5 py-0.5 font-mono text-xs text-zinc-300">
                    <span>NIM #{{ $user->nim_code }}</span>
                    <span>•</span>
                    <span>Hadir #{{ $user->arrival_order }}</span>
                </div>
            </div>
            <span class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-[11px] font-semibold text-emerald-400">
                AKTIF
            </span>
        </div>

        <div class="mt-6">
            <div class="text-xs uppercase tracking-wider text-zinc-400">Saldo Saat Ini</div>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="font-display text-4xl font-extrabold tracking-tight text-gold-400" x-text="formatNum(balance)">
                    {{ number_format($user->balance, 0, ',', '.') }}
                </span>
                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Coins</span>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-3">
            <a
                href="{{ route('transfer.create') }}"
                :class="frozen ? 'pointer-events-none opacity-50' : ''"
                class="flex items-center justify-center gap-2 rounded-xl bg-gold-500 py-3 text-sm font-bold text-zinc-950 transition hover:bg-gold-400"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                Kirim Saldo
            </a>
            <a
                href="{{ route('participant.catalog') }}"
                class="flex items-center justify-center gap-2 rounded-xl border border-gold-500/40 bg-zinc-900 py-3 text-sm font-semibold text-gold-300 transition hover:bg-zinc-800"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                Katalog Lelang
            </a>
        </div>
    </div>

    {{-- Riwayat Transaksi --}}
    <div class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-sm font-bold uppercase tracking-wider text-zinc-300">Riwayat Transaksi</h2>
            <span class="text-[11px] text-zinc-500">Auto-update</span>
        </div>

        <template x-if="transactions.length === 0">
            <div class="py-8 text-center text-xs text-zinc-500">Belum ada transaksi keluar atau masuk.</div>
        </template>

        <div class="space-y-3">
            <template x-for="tx in transactions" :key="tx.id">
                <div class="flex items-center justify-between rounded-xl border border-zinc-800/80 bg-zinc-950/70 p-3.5">
                    <div class="min-w-0 pr-3">
                        <div class="truncate text-sm font-semibold text-zinc-100" x-text="tx.title || tx.counterparty_name || tx.label"></div>
                        <div class="mt-0.5 text-xs text-zinc-400" x-text="tx.subtitle || tx.note || tx.time"></div>
                    </div>
                    <div class="text-right">
                        <div
                            class="font-mono text-sm font-bold"
                            :class="isIncoming(tx) ? 'text-emerald-400' : 'text-red-400'"
                            x-text="(isIncoming(tx) ? '+' : '-') + formatNum(Math.abs(tx.amount))"
                        ></div>
                        <div class="text-[10px] text-zinc-500" x-text="tx.time"></div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function participantWallet(cfg) {
    return {
        balance: cfg.balance,
        frozen: cfg.frozen,
        transactions: cfg.transactions,
        isIncoming(tx) {
            if (typeof tx.is_incoming === 'boolean') return tx.is_incoming;
            if (tx.direction === 'in' || tx.type === 'in' || tx.type === 'credit') return true;
            if (tx.direction === 'out' || tx.type === 'out' || tx.type === 'debit') return false;
            if (typeof tx.delta === 'number') return tx.delta >= 0;
            const title = (tx.title || tx.label || '').toLowerCase();
            return title.startsWith('transfer dari') || title.startsWith('saldo awal') || title.startsWith('bonus');
        },
        init() {
            setInterval(async () => {
                try {
                    const res = await fetch(cfg.stateUrl, { headers: { 'Accept': 'application/json' } });
                    if (res.status === 401) { window.location.href = '{{ route("login") }}'; return; }
                    if (!res.ok) return;
                    const data = await res.json();
                    this.balance = data.balance;
                    this.frozen = data.frozen;
                    this.transactions = data.transactions;
                } catch (e) {}
            }, 4000);
        }
    };
}
</script>
@endpush
