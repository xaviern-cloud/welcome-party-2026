@extends('layouts.app')

@section('title', 'Lelang & Katalog — Welcome Party 2026')

@section('content')
@php
    $catalogList = collect($catalog ?? [])->values()->map(function ($c, $idx) {
        $c['code'] = $c['code'] ?? ('BRG-' . str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT));
        return $c;
    })->all();
@endphp

<div class="space-y-8">
    {{-- BAGIAN 1: POTONG SALDO PEMENANG & RIWAYAT LELANG --}}
    <div
        x-data="auctionDesk({
            searchUrl: '{{ route("admin.participants.search") }}',
            deductUrl: '{{ route("admin.auction.deduct") }}',
            recent: @js($recent),
            catalog: @js($catalogList)
        })"
        x-init="init()"
        class="grid gap-6 lg:grid-cols-2"
    >
        <div class="rounded-2xl border border-gold-500/30 bg-zinc-900/80 p-6">
            <h1 class="text-xl font-bold text-gold-400">Input Pemenang Lelang</h1>
            <p class="mt-1 text-xs text-zinc-400">Pilih pemenang & barang untuk melihat kalkulasi sisa saldo secara real-time.</p>

            <div x-show="successMsg" x-cloak class="mt-4 rounded-xl border border-emerald-500/40 bg-emerald-950/60 p-3 text-xs text-emerald-200" x-text="successMsg"></div>
            <div x-show="errorMsg" x-cloak class="mt-4 rounded-xl border border-red-500/40 bg-red-950/60 p-3 text-xs text-red-200" x-text="errorMsg"></div>

            <form @submit.prevent="submitAuction" class="mt-5 space-y-4">
                {{-- Pilih Pemenang Lelang --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-zinc-400">Pemenang Lelang (Ketik 3 Digit NIM atau Nama)</label>
                    <template x-if="winner">
                        <div class="flex items-center justify-between rounded-xl border border-gold-500/50 bg-gold-500/10 p-3.5">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded bg-gold-500/20 px-1.5 py-0.5 font-mono text-xs font-bold text-gold-400" x-text="'#' + winner.nim_code"></span>
                                    <span class="font-bold text-white" x-text="winner.name"></span>
                                </div>
                                <div class="mt-1 text-xs text-zinc-400">
                                    Kelompok: <span class="text-zinc-200" x-text="winner.group_name || '-'"></span> •
                                    Saldo saat ini: <strong class="font-mono text-emerald-400" x-text="formatNum(winner.balance) + ' Coins'"></strong>
                                </div>
                            </div>
                            <button type="button" @click="winner = null; q = ''; results = []" class="rounded-lg border border-zinc-700 bg-zinc-900 px-2.5 py-1 text-xs text-zinc-300 hover:text-white">Ganti</button>
                        </div>
                    </template>
                    <template x-if="!winner">
                        <div class="relative">
                            <input
                                type="text"
                                x-model="q"
                                @input.debounce.150ms="search()"
                                @focus="if (q.trim()) search()"
                                placeholder="Ketik NIM (mis. 056) atau nama peserta..."
                                class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-sm text-white focus:border-gold-500 focus:outline-none"
                            >
                            <div x-show="searching" x-cloak class="mt-1.5 text-[11px] text-zinc-400">Mencari peserta hadir...</div>
                            <div x-show="!searching && q.trim() !== '' && results.length === 0" x-cloak class="mt-2 rounded-xl border border-zinc-800 bg-zinc-950 px-4 py-3 text-xs text-zinc-500">
                                Peserta belum check-in atau tidak ditemukan.
                            </div>
                            <div x-show="results.length > 0" x-cloak class="mt-2 max-h-60 divide-y divide-zinc-800 overflow-y-auto rounded-xl border border-zinc-700 bg-zinc-950 shadow-2xl">
                                <template x-for="person in results" :key="person.nim_code">
                                    <button type="button" @click="selectWinner(person)" class="flex w-full items-center justify-between px-4 py-3 text-left transition hover:bg-zinc-900">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono text-xs font-bold text-gold-400" x-text="'#' + person.nim_code"></span>
                                                <span class="text-sm font-semibold text-white" x-text="person.name"></span>
                                            </div>
                                            <div class="text-xs text-zinc-400" x-text="person.group_name || 'Tanpa Kelompok'"></div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-mono text-xs font-bold text-emerald-400" x-text="formatNum(person.balance)"></div>
                                            <div class="text-[10px] text-zinc-500">Coins</div>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Pilih dari Katalog atau Manual --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-semibold text-zinc-400">ID & Nama Item Lelang</label>
                        <template x-if="catalog.length > 0">
                            <select
                                @change="pickFromDropdown($event.target.value); $event.target.value = ''"
                                class="rounded-lg border border-zinc-700 bg-zinc-950 px-2.5 py-1 text-[11px] text-gold-400 focus:border-gold-500 focus:outline-none"
                            >
                                <option value="">⚡ Pilih cepat dari katalog...</option>
                                <template x-for="c in catalog" :key="c.id">
                                    <option :value="c.id" x-text="'[' + c.code + '] ' + c.name + ' (Open: ' + formatNum(c.starting_price) + ')'"></option>
                                </template>
                            </select>
                        </template>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <input
                            type="text"
                            x-model="itemCode"
                            placeholder="ID (BRG-01)"
                            class="rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2.5 font-mono text-xs font-bold uppercase text-gold-400 placeholder:font-sans placeholder:font-normal placeholder:text-zinc-600"
                        >
                        <input
                            id="auction-item-input"
                            type="text"
                            x-model="item"
                            required
                            placeholder="Nama item lelang..."
                            class="col-span-2 rounded-xl border border-zinc-700 bg-zinc-950 px-3.5 py-2.5 text-sm text-white"
                        >
                    </div>
                </div>

                {{-- Harga Deal Akhir --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-zinc-400">Harga Deal Akhir (Coins)</label>
                    <input
                        id="auction-price-input"
                        type="number"
                        x-model.number="price"
                        min="1"
                        required
                        placeholder="0"
                        class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 font-mono text-xl font-bold text-gold-400"
                    >
                </div>

                {{-- Preview Kalkulasi Real-Time --}}
                <div x-show="winner || price > 0" x-cloak class="rounded-xl border border-zinc-800 bg-zinc-950/90 p-3.5 text-xs space-y-1.5">
                    <div class="flex items-center justify-between text-zinc-400">
                        <span>Item Lelang</span>
                        <span class="font-semibold text-zinc-200" x-text="(itemCode ? '[' + itemCode + '] ' : '') + (item || '-')"></span>
                    </div>
                    <div class="flex items-center justify-between text-zinc-400">
                        <span>Harga Deal</span>
                        <span class="font-mono font-bold text-amber-400" x-text="'-' + formatNum(price || 0) + ' Coins'"></span>
                    </div>
                    <template x-if="winner">
                        <div class="flex items-center justify-between border-t border-zinc-800 pt-1.5">
                            <span class="font-semibold text-zinc-300">Estimasi Sisa Saldo Pemenang</span>
                            <span
                                class="font-mono text-sm font-extrabold"
                                :class="(winner.balance - (price || 0)) < 0 ? 'text-red-400' : 'text-emerald-400'"
                                x-text="formatNum(winner.balance - (price || 0)) + ' Coins'"
                            ></span>
                        </div>
                    </template>
                    <div x-show="winner && (winner.balance - (price || 0)) < 0" class="pt-1 text-[11px] font-semibold text-red-400">
                        ⚠ Saldo pemenang tidak mencukupi untuk harga deal ini!
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="busy || !winner || !price || !item || (winner.balance - price) < 0"
                    class="w-full rounded-xl bg-gold-500 py-3 text-sm font-bold text-zinc-950 transition hover:bg-gold-400 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Potong Saldo Pemenang
                </button>
            </form>
        </div>

        <div class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6">
            <h2 class="mb-4 text-base font-bold text-white">Riwayat Lelang Terbaru</h2>
            <template x-if="recent.length === 0">
                <div class="py-12 text-center text-xs text-zinc-500">Belum ada transaksi pemotongan lelang.</div>
            </template>
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

    {{-- BAGIAN 2: MANAJEMEN KATALOG ITEM LELANG (BUKA / SEMBUNYIKAN) --}}
    <div class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-zinc-800 pb-4">
            <div>
                <h2 class="text-lg font-bold text-gold-400">Katalog Item Lelang (Reveal Control)</h2>
                <p class="text-xs text-zinc-400">Setiap barang otomatis diberi ID unik (<strong class="font-mono text-zinc-200">BRG-01</strong>, dst.) agar tidak tertukar saat lelang.</p>
            </div>
        </div>

        {{-- Form Tambah Item Katalog --}}
        <form method="POST" action="{{ route('admin.auction.catalog.store') }}" class="mt-5 grid gap-3 rounded-xl border border-zinc-800 bg-zinc-950/60 p-4 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            <div>
                <label class="mb-1 block text-xs text-zinc-400">Nama Item *</label>
                <input type="text" name="name" required placeholder="Contoh: Mystery Box #1" class="w-full rounded-xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-white">
            </div>
            <div>
                <label class="mb-1 block text-xs text-zinc-400">Open Bid (Coins) *</label>
                <input type="number" name="starting_price" min="0" value="1000" required class="w-full rounded-xl border border-zinc-700 bg-zinc-900 px-3 py-2 font-mono text-xs text-white">
            </div>
            <div>
                <label class="mb-1 block text-xs text-zinc-400">Deskripsi Singkat (Opsional)</label>
                <input type="text" name="description" placeholder="Benefit / keterangan hadiah" class="w-full rounded-xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-white">
            </div>
            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <label class="mb-1 block text-xs text-zinc-400">URL Gambar (Opsional)</label>
                    <input type="text" name="image_url" placeholder="https://..." class="w-full rounded-xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-white">
                </div>
                <button type="submit" class="rounded-xl bg-emerald-500 px-4 py-2 text-xs font-bold text-zinc-950 hover:bg-emerald-400">
                    + Tambah
                </button>
            </div>
        </form>

        {{-- Daftar Item Katalog --}}
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($catalogList as $c)
                <div class="flex flex-col justify-between rounded-2xl border {{ !empty($c['is_revealed']) ? 'border-emerald-500/40 bg-zinc-950' : 'border-zinc-800 bg-zinc-950/50' }} p-4">
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5">
                                <span class="rounded bg-gold-500/20 px-2 py-0.5 font-mono text-[11px] font-extrabold text-gold-400">
                                    {{ $c['code'] }}
                                </span>
                                @if(!empty($c['is_revealed']))
                                    <span class="rounded-full border border-emerald-500/40 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-400">
                                        DIBUKA
                                    </span>
                                @else
                                    <span class="rounded-full border border-zinc-700 bg-zinc-900 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-zinc-400">
                                        TERSEMBUNYI
                                    </span>
                                @endif
                            </div>

                            <form method="POST" action="{{ route('admin.auction.catalog.destroy', $c['id']) }}" onsubmit="return confirm('Hapus [{{ $c['code'] }}] {{ $c['name'] }} dari katalog?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:underline">Hapus</button>
                            </form>
                        </div>

                        @if(!empty($c['image_url']))
                            <img src="{{ $c['image_url'] }}" alt="{{ $c['name'] }}" class="mt-3 h-32 w-full rounded-xl object-cover bg-zinc-900">
                        @endif

                        <div class="mt-3 text-base font-bold text-white">{{ $c['name'] }}</div>
                        @if(!empty($c['description']))
                            <p class="mt-1 text-xs text-zinc-400">{{ $c['description'] }}</p>
                        @endif
                        <div class="mt-2 font-mono text-xs font-bold text-gold-400">
                            Open Bid: {{ number_format((int) ($c['starting_price'] ?? 0), 0, ',', '.') }} Coins
                        </div>
                    </div>

                    <div class="mt-4 flex items-center gap-2 border-t border-zinc-800 pt-3">
                        <form method="POST" action="{{ route('admin.auction.catalog.toggle', $c['id']) }}" class="flex-1">
                            @csrf
                            <button type="submit" class="w-full rounded-xl {{ !empty($c['is_revealed']) ? 'border border-amber-500/40 bg-amber-500/10 text-amber-300 hover:bg-amber-500/20' : 'bg-emerald-500 text-zinc-950 hover:bg-emerald-400' }} py-2 text-xs font-bold">
                                {{ !empty($c['is_revealed']) ? 'Sembunyikan' : 'Buka ke Peserta' }}
                            </button>
                        </form>
                        <button
                            type="button"
                            @click="window.dispatchEvent(new CustomEvent('pick-catalog-item', { detail: { code: @js($c['code']), name: @js($c['name']), price: {{ (int) ($c['starting_price'] ?? 0) }} } }))"
                            class="rounded-xl border border-gold-500/40 bg-zinc-900 px-3 py-2 text-xs font-bold text-gold-400 hover:bg-gold-500/15"
                            title="Masukkan ke form pemenang lelang di atas"
                        >
                            Lelang →
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-10 text-center text-xs text-zinc-500">
                    Belum ada item di katalog lelang. Tambahkan item baru menggunakan form di atas.
                </div>
            @endforelse
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
        searching: false,
        winner: null,
        itemCode: '',
        item: '',
        price: '',
        recent: cfg.recent,
        catalog: cfg.catalog || [],
        busy: false,
        successMsg: '',
        errorMsg: '',
        idemKey: (crypto.randomUUID ? crypto.randomUUID() : String(Date.now())),

        init() {
            window.addEventListener('pick-catalog-item', (e) => {
                this.itemCode = e.detail.code || '';
                this.item = e.detail.name || '';
                this.price = e.detail.price || '';
                window.scrollTo({ top: 0, behavior: 'smooth' });
                setTimeout(() => document.getElementById('auction-price-input')?.focus(), 250);
            });
        },

        pickFromDropdown(id) {
            const found = this.catalog.find(c => c.id === id);
            if (!found) return;
            this.itemCode = found.code || '';
            this.item = found.name || '';
            this.price = found.starting_price || '';
        },

        selectWinner(person) {
            this.winner = person;
            this.results = [];
            this.q = '';
        },

        async search() {
            const query = this.q.trim();
            if (!query) { this.results = []; return; }
            this.searching = true;
            try {
                const res = await fetch(`${cfg.searchUrl}?checked_in=1&q=${encodeURIComponent(query)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const list = (await res.json()).results || [];
                    this.results = list;
                    // Jika mengetik tepat 3 digit NIM dan cocok 1 peserta, langsung pilih otomatis
                    if (/^\d{3}$/.test(query) && list.length === 1 && list[0].nim_code === query) {
                        this.selectWinner(list[0]);
                    }
                }
            } finally {
                this.searching = false;
            }
        },

        async submitAuction() {
            const label = (this.itemCode ? `[${this.itemCode}] ` : '') + this.item;
            if (!confirm(`Potong saldo ${this.winner.name} (#${this.winner.nim_code}) sebesar ${formatNum(this.price)} Coins untuk "${label}"?`)) return;
            this.busy = true;
            this.errorMsg = '';
            try {
                const res = await window.apiPost(cfg.deductUrl, {
                    winner: this.winner.nim_code,
                    price: Number(this.price),
                    item_code: this.itemCode,
                    item: this.item,
                    idempotency_key: this.idemKey
                });
                this.successMsg = res.message;
                this.recent.unshift(res.transaction);
                this.winner = null;
                this.itemCode = '';
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
