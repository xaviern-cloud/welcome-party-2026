@extends('layouts.app')

@section('title', 'Kirim Saldo — Welcome Party 2026')

@section('content')
<div
    x-data="transferForm({
        balance: {{ (int) $balance }},
        frozen: {{ $frozen ? 'true' : 'false' }},
        receiver: @js($receiver),
        lookupUrl: '{{ $lookupUrl }}',
        storeUrl: '{{ $storeUrl }}'
    })"
    class="mx-auto max-w-lg space-y-5"
>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-white">Kirim Saldo</h1>
            <p class="text-xs text-zinc-400">Saldo tersedia: <strong class="font-mono text-gold-400" x-text="formatNum(balance)"></strong></p>
        </div>
        <a href="{{ route('participant.dashboard') }}" class="rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-xs text-zinc-300 hover:text-white">
            ← Kembali
        </a>
    </div>

    @if($toError)
        <div class="rounded-xl border border-amber-500/40 bg-amber-950/50 p-3.5 text-xs text-amber-200">
            {{ $toError }}
        </div>
    @endif

    <div x-show="frozen" x-cloak class="rounded-xl border border-red-500/50 bg-red-950/60 p-4 text-center text-xs font-semibold text-red-300">
        Transaksi sedang dibekukan oleh panitia. Pengiriman saldo tidak dapat dilakukan saat ini.
    </div>

    <div x-show="errorMsg" x-cloak class="rounded-xl border border-red-500/50 bg-red-950/60 p-3.5 text-xs text-red-200" x-text="errorMsg"></div>

    <form @submit.prevent="submitTransfer" class="space-y-5 rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6">
        {{-- Pilih Penerima --}}
        <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-zinc-400">
                Penerima (3 Digit NIM atau Nama)
            </label>

            <template x-if="selected">
                <div class="flex items-center justify-between rounded-xl border border-gold-500/50 bg-gold-500/10 p-3.5">
                    <div>
                        <div class="text-sm font-bold text-white" x-text="selected.name"></div>
                        <div class="font-mono text-xs text-gold-400">
                            #<span x-text="selected.nim_code"></span> • <span x-text="selected.group_name || 'Peserta'"></span>
                        </div>
                    </div>
                    <button type="button" @click="clearReceiver()" class="rounded-lg bg-zinc-900 px-2.5 py-1 text-xs text-zinc-300 hover:text-white">
                        Ganti
                    </button>
                </div>
            </template>

            <template x-if="!selected">
                <div class="relative">
                    <input
                        type="text"
                        x-model="query"
                        @input.debounce.250ms="search()"
                        placeholder="Ketik 3 digit NIM (mis. 056) atau nama..."
                        class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-sm text-white placeholder-zinc-600 focus:border-gold-500 focus:outline-none"
                    >
                    <div x-show="results.length > 0" class="mt-2 divide-y divide-zinc-800 overflow-hidden rounded-xl border border-zinc-700 bg-zinc-950">
                        <template x-for="item in results" :key="item.nim_code">
                            <button
                                type="button"
                                @click="pick(item)"
                                class="flex w-full items-center justify-between px-4 py-3 text-left transition hover:bg-zinc-900"
                            >
                                <div>
                                    <div class="text-sm font-semibold text-white" x-text="item.name"></div>
                                    <div class="text-xs text-zinc-400" x-text="item.group_name || 'Peserta'"></div>
                                </div>
                                <span class="rounded-md bg-gold-500/15 px-2 py-0.5 font-mono text-xs font-bold text-gold-400" x-text="'#' + item.nim_code"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- Nominal --}}
        <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-zinc-400">
                Nominal Transfer
            </label>
            <input
                type="number"
                x-model.number="amount"
                min="1"
                :max="balance"
                required
                placeholder="0"
                class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 font-mono text-2xl font-bold text-gold-400 placeholder-zinc-700 focus:border-gold-500 focus:outline-none"
            >
            <div class="mt-2 flex flex-wrap gap-2">
                <template x-for="preset in [100, 500, 1000, 2500]" :key="preset">
                    <button
                        type="button"
                        @click="amount = Math.min(balance, (Number(amount) || 0) + preset)"
                        class="rounded-lg border border-zinc-700 bg-zinc-950 px-2.5 py-1 font-mono text-xs text-zinc-300 hover:border-gold-500/50 hover:text-gold-400"
                        x-text="'+' + formatNum(preset)"
                    ></button>
                </template>
                <button
                    type="button"
                    @click="amount = balance"
                    class="rounded-lg border border-gold-500/40 bg-gold-500/10 px-2.5 py-1 text-xs font-semibold text-gold-400"
                >
                    Semua
                </button>
            </div>
        </div>

        {{-- Catatan Opsional --}}
        <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-zinc-400">
                Catatan (Opsional)
            </label>
            <input
                type="text"
                x-model="note"
                maxlength="100"
                placeholder="Contoh: Patungan lelang"
                class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-2.5 text-sm text-white placeholder-zinc-600 focus:border-gold-500 focus:outline-none"
            >
        </div>

        <button
            type="submit"
            :disabled="loading || frozen || !selected || !amount || amount < 1"
            class="w-full rounded-xl bg-gradient-to-r from-gold-600 via-gold-500 to-amber-400 py-3.5 text-sm font-bold text-zinc-950 shadow-lg transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-40"
        >
            <span x-show="!loading">Kirim Sekarang</span>
            <span x-show="loading">Memproses Transaksi...</span>
        </button>
    </form>
</div>
@endsection

@push('scripts')
<script>
function transferForm(cfg) {
    return {
        balance: cfg.balance,
        frozen: cfg.frozen,
        selected: cfg.receiver,
        query: '',
        results: [],
        amount: '',
        note: '',
        loading: false,
        errorMsg: '',
        idemKey: (crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random()),

        async search() {
            if (this.query.trim().length < 1) { this.results = []; return; }
            const res = await fetch(`${cfg.lookupUrl}?q=${encodeURIComponent(this.query)}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const data = await res.json();
                this.results = data.results || [];
            }
        },
        pick(item) {
            this.selected = item;
            this.results = [];
            this.query = '';
            this.errorMsg = '';
        },
        clearReceiver() {
            this.selected = null;
            this.results = [];
        },
        async submitTransfer() {
            if (!this.selected || this.loading) return;
            this.loading = true;
            this.errorMsg = '';
            try {
                await window.apiPost(cfg.storeUrl, {
                    receiver: this.selected.nim_code,
                    amount: Number(this.amount),
                    note: this.note || null,
                    idempotency_key: this.idemKey,
                });
                window.location.href = '{{ route("participant.dashboard") }}';
            } catch (e) {
                this.errorMsg = e.message;
                this.idemKey = (crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random());
            } finally {
                this.loading = false;
            }
        }
    };
}
</script>
@endpush
