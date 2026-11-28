@extends('layouts.app')

@section('title', 'Dompet Saya — Welcome Party 2026')

@section('content')
<div
    x-data="participantWallet({
        balance: {{ (int) $user->balance }},
        frozen: {{ $frozen ? 'true' : 'false' }},
        transactions: @js($transactions),
        tableState: @js($tableState ?? null),
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

    {{-- PANEL SESI VOTING & TRANSFER JURU BICARA MEJA --}}
    <template x-if="tableState && tableState.has_table && tableState.session_status !== 'idle'">
        <div class="space-y-4 rounded-3xl border-2 p-5 shadow-2xl"
             :class="tableState.catalog_locked ? 'border-red-500/70 bg-red-950/30' : 'border-gold-500/60 bg-zinc-900'">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <span class="rounded bg-gold-500/20 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-gold-400" x-text="tableState.table_name"></span>
                    <h2 class="mt-1 text-base font-extrabold text-white">Sesi Juru Bicara Meja</h2>
                </div>
                <span class="rounded-full px-2.5 py-1 text-[10px] font-extrabold uppercase"
                      :class="tableState.session_status === 'open' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-red-500/20 text-red-300'"
                      x-text="tableState.session_status === 'open' ? 'SESI AKTIF' : 'SESI DITUTUP'"></span>
            </div>

            {{-- Peringatan jika Katalog Terkunci --}}
            <template x-if="tableState.catalog_locked">
                <div class="rounded-2xl border border-red-500/50 bg-red-950/70 p-3.5 text-xs text-red-200">
                    <div class="font-extrabold text-red-300">🔒 Akses Katalog Lelang Diblokir</div>
                    <p class="mt-1">Sesi pengumpulan saldo telah ditutup panitia dan Anda belum mentransfer saldo ke Juru Bicara meja. Hubungi panitia untuk membuka kembali sesi agar Anda dapat mentransfer saldo.</p>
                </div>
            </template>

            {{-- Langkah 1: Voting Anggota Meja --}}
            <div class="space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-zinc-300">1. Voting Juru Bicara Meja</span>
                    <span class="text-zinc-400" x-text="tableState.votes_cast + '/' + tableState.votes_required + ' suara'"></span>
                </div>

                <div class="space-y-2">
                    <template x-for="m in tableState.members" :key="m.nim_code">
                        <div class="flex items-center justify-between rounded-xl border px-3.5 py-2.5 text-xs"
                             :class="m.is_spokesperson ? 'border-emerald-500/60 bg-emerald-950/30' : (tableState.my_vote === m.nim_code ? 'border-gold-500/60 bg-gold-500/10' : 'border-zinc-800 bg-zinc-950')">
                            <div>
                                <div class="font-bold text-white">
                                    <span class="font-mono text-gold-400" x-text="'#' + m.nim_code"></span>
                                    <span x-text="m.name"></span>
                                    <span x-show="m.is_spokesperson" class="ml-1 rounded bg-emerald-500 px-1.5 py-0.5 text-[10px] font-black text-zinc-950">JURU BICARA</span>
                                </div>
                                <div class="mt-0.5 text-[11px] text-zinc-400">
                                    Perolehan suara: <strong class="text-white" x-text="m.votes"></strong>
                                    <span x-show="!m.is_spokesperson && m.has_transferred" class="ml-2 text-emerald-400">• Sudah transfer ✓</span>
                                </div>
                            </div>

                            <button
                                type="button"
                                x-show="tableState.session_status === 'open' && !tableState.i_have_transferred"
                                @click="castTableVote(m.nim_code)"
                                :disabled="tableBusy"
                                class="rounded-lg px-3 py-1.5 text-[11px] font-bold transition"
                                :class="tableState.my_vote === m.nim_code ? 'bg-gold-500 text-zinc-950' : 'border border-zinc-700 bg-zinc-900 text-zinc-200 hover:border-gold-500'"
                                x-text="tableState.my_vote === m.nim_code ? 'Dipilih ✓' : 'Pilih'"
                            ></button>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Langkah 2: Tombol Transfer 100% Saldo ke Juru Bicara --}}
            <template x-if="tableState.voting_complete">
                <div class="rounded-2xl border border-emerald-500/40 bg-zinc-950 p-4 text-xs">
                    <div class="font-bold text-emerald-300">
                        2. Juru Bicara Terpilih: <span x-text="tableState.spokesperson_name + ' (#' + tableState.spokesperson_nim + ')'"></span>
                    </div>

                    <template x-if="tableState.i_am_spokesperson">
                        <p class="mt-1.5 text-zinc-300">Anda terpilih sebagai <strong>Juru Bicara Meja</strong>. Anggota meja akan mentransfer saldo mereka ke akun Anda untuk dipakai saat lelang.</p>
                    </template>

                    <template x-if="!tableState.i_am_spokesperson && !tableState.i_have_transferred && tableState.session_status === 'open'">
                        <div class="mt-2.5 space-y-2.5">
                            <p class="text-zinc-300">Wajib mentransfer seluruh saldo Anda (<strong class="font-mono text-gold-400" x-text="formatNum(balance) + ' Coins'"></strong>) ke Juru Bicara agar Katalog Lelang tetap terbuka.</p>
                            <button
                                type="button"
                                @click="transferAllToSpokesperson()"
                                :disabled="tableBusy"
                                class="w-full rounded-xl bg-emerald-500 py-3 text-xs font-extrabold text-zinc-950 shadow-lg hover:bg-emerald-400 disabled:opacity-50"
                                x-text="'💸 Transfer Seluruh Saldo (' + formatNum(balance) + ' Coins) ke Juru Bicara'"
                            ></button>
                        </div>
                    </template>

                    <template x-if="!tableState.i_am_spokesperson && tableState.i_have_transferred">
                        <div class="mt-2 font-semibold text-emerald-400">
                            ✓ Anda sudah menyelesaikan transfer saldo ke Juru Bicara Meja. Akses Katalog Lelang aman!
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </template>

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
                    <template x-if="tableState && tableState.has_table">
                        <span>• <strong class="text-gold-400" x-text="tableState.table_name"></strong></span>
                    </template>
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
                :class="(tableState && tableState.catalog_locked) ? 'pointer-events-none border-red-500/40 bg-red-950/40 text-red-300 opacity-75' : 'border-gold-500/40 bg-zinc-900 text-gold-300 hover:bg-zinc-800'"
                class="flex items-center justify-center gap-2 rounded-xl border py-3 text-sm font-semibold transition"
            >
                <span x-text="(tableState && tableState.catalog_locked) ? '🔒 Katalog Terkunci' : 'Katalog Lelang'">Katalog Lelang</span>
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
        tableState: cfg.tableState,
        tableBusy: false,
        isIncoming(tx) {
            if (typeof tx.is_incoming === 'boolean') return tx.is_incoming;
            if (tx.direction === 'in' || tx.type === 'in' || tx.type === 'credit') return true;
            if (tx.direction === 'out' || tx.type === 'out' || tx.type === 'debit') return false;
            if (typeof tx.delta === 'number') return tx.delta >= 0;
            const title = (tx.title || tx.label || '').toLowerCase();
            return title.startsWith('transfer dari') || title.startsWith('saldo awal') || title.startsWith('bonus');
        },
        async castTableVote(candidateNim) {
            this.tableBusy = true;
            try {
                const res = await window.apiPost('{{ route("participant.table.vote") }}', { candidate_nim: candidateNim });
                this.tableState = res.table_state;
            } catch (e) { alert(e.message); }
            finally { this.tableBusy = false; }
        },
        async transferAllToSpokesperson() {
            if (!confirm('Transfer SELURUH saldo Anda (' + formatNum(this.balance) + ' Coins) ke Juru Bicara Meja?')) return;
            this.tableBusy = true;
            try {
                const res = await window.apiPost('{{ route("participant.table.transfer") }}', {});
                this.balance = res.balance;
                this.tableState = res.table_state;
                alert(res.message);
            } catch (e) { alert(e.message); }
            finally { this.tableBusy = false; }
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
                    if (data.table_state) this.tableState = data.table_state;
                } catch (e) {}
            }, 3500);
        }
    };
}
</script>
@endpush
