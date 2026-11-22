@extends('layouts.app')

@section('title', 'Potong Saldo Lelang — Welcome Party 2026')

@section('content')
<div
    x-data="auctionDesk({
        searchUrl: '{{ route("admin.participants.search") }}',
        deductUrl: '{{ route("admin.auction.deduct") }}',
        recent: @js($recent)
    })"
    class="grid gap-6 lg:grid-cols-2"
>
    <div class="rounded-2xl border border-gold-500/30 bg-zinc-900/80 p-6">
        <h1 class="text-xl font-bold text-gold-400">Input Pemenang Lelang</h1>
        <p class="mt-1 text-xs text-zinc-400">Cari pemenang untuk melihat saldo real-time sebelum memotong harga deal.</p>

        <div x-show="successMsg" x-cloak class="mt-4 rounded-xl border border-emerald-500/40 bg-emerald-950/60 p-3 text-xs text-emerald-200" x-text="successMsg"></div>
        <div x-show="errorMsg" x-cloak class="mt-4 rounded-xl border border-red-500/40 bg-red-950/60 p-3 text-xs text-red-200" x-text="errorMsg"></div>

        <form @submit.prevent="submitAuction" class="mt-5 space-y-4">
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-zinc-400">Pemenang Lelang (3 Digit NIM / Nama)</label>
                <template x-if="winner">
                    <div class="flex items-center justify-between rounded-xl border border-gold-500/50 bg-gold-500/10 p-3.5">
                        <div>
                            <div class="font-bold text-white" x-text="winner.name + ' (#' + winner.nim_code + ')'"></div>
                            <div class="text-xs text-emerald-400">Saldo saat ini: <strong class="font-mono" x-text="formatNum(winner.balance)"></strong></div>
                        </div>
                        <button type="button" @click="winner = null" class="rounded-lg bg-zinc-900 px-2.5 py-1 text-xs text-zinc-300">Ganti</button>
                    </div>
                </template>
                <template x-if="!winner">
                    <div>
                        <input type="text" x-model="q" @input.debounce.200ms="search()" placeholder="Ketik 3 digit NIM pemenang..." class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-sm text-white">
                        <div x-show="results.length > 0" class="mt-2 divide-y divide-zinc-800 rounded-xl border border-zinc-700 bg-zinc-950">
                            <template x-for="item in results" :key="item.nim_code">
                                <button type="button" @click="winner = item; results = []; q = ''" class="flex w-full items-center justify-between px-4 py-2.5 text-left hover:bg-zinc-900">
                                    <div>
                                        <div class="text-sm font-semibold text-white" x-text="item.name"></div>
                                        <div class="text-xs text-zinc-400" x-text="'#' + item.nim_code + ' • ' + (item.group_name || '-')"></div>
                                    </div>
                                    <span class="font-mono text-xs font-bold text-emerald-400" x-text="formatNum(item.balance)"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-zinc-400">Nama Item Lelang</label>
                <input type="text" x-model="item" required placeholder="Contoh: Voucher Bebas Tugas / Merchandise Eksklusif" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-2.5 text-sm text-white">
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-zinc-400">Harga Deal Akhir</label>
                <input type="number" x-model.number="price" min="1" required placeholder="0" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 font-mono text-xl font-bold text-gold-400">
            </div>

            <button type="submit" :disabled="busy || !winner || !price || !item" class="w-full rounded-xl bg-gold-500 py-3 text-sm font-bold text-zinc-950 hover:bg-gold-400 disabled:opacity-40">
                Potong Saldo Pemenang
            </button>
        </form>
    </div>

    <div class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6">
        <h2 class="mb-4 text-base font-bold text-white">Riwayat Lelang Terbaru</h2>
        <div class="space-y-3">
            <template x-for="tx in recent" :key="tx.id">
                <div class="flex items-center justify-between rounded-xl border border-zinc-800 bg-zinc-950/70 p-3.5 text-xs">
                    <div>
                        <div class="font-bold text-white" x-text="tx.title || tx.label"></div>
                        <div class="text-zinc-400" x-text="tx.subtitle || tx.time"></div>
                    </div>
                    <div class="font-mono text-sm font-bold text-amber-400" x-text="'-' + formatNum(tx.amount)"></div>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function auctionDesk(cfg) {
    return {
        q: '',
        results: [],
        winner: null,
        item: '',
        price: '',
        recent: cfg.recent,
        busy: false,
        successMsg: '',
        errorMsg: '',
        idemKey: (crypto.randomUUID ? crypto.randomUUID() : String(Date.now())),

        async search() {
            if (!this.q.trim()) { this.results = []; return; }
            const res = await fetch(`${cfg.searchUrl}?checked_in=1&q=${encodeURIComponent(this.q)}`, { headers: { 'Accept': 'application/json' } });
            if (res.ok) this.results = (await res.json()).results || [];
        },
        async submitAuction() {
            if (!confirm(`Potong saldo ${this.winner.name} sebesar ${formatNum(this.price)} untuk "${this.item}"?`)) return;
            this.busy = true;
            this.errorMsg = '';
            try {
                const res = await window.apiPost(cfg.deductUrl, {
                    winner: this.winner.nim_code,
                    price: Number(this.price),
                    item: this.item,
                    idempotency_key: this.idemKey
                });
                this.successMsg = res.message;
                this.recent.unshift(res.transaction);
                this.winner = null;
                this.item = '';
                this.price = '';
                this.idemKey = (crypto.randomUUID ? crypto.randomUUID() : String(Date.now()));
            } catch (e) { this.errorMsg = e.message; }
            finally { this.busy = false; }
        }
    };
}
</script>
@endpush
