@extends('layouts.app')

@section('title', 'Lelang & Katalog — Welcome Party 2026')

@section('content')
<div class="space-y-8">
    {{-- BAGIAN 1: POTONG SALDO PEMENANG & RIWAYAT LELANG --}}
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
                    <input id="auction-item-input" type="text" x-model="item" required placeholder="Contoh: Voucher Bebas Tugas / Merchandise Eksklusif" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-2.5 text-sm text-white">
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-zinc-400">Harga Deal Akhir</label>
                    <input id="auction-price-input" type="number" x-model.number="price" min="1" required placeholder="0" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 font-mono text-xl font-bold text-gold-400">
                </div>

                <button type="submit" :disabled="busy || !winner || !price || !item" class="w-full rounded-xl bg-gold-500 py-3 text-sm font-bold text-zinc-950 hover:bg-gold-400 disabled:opacity-40">
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
                <p class="text-xs text-zinc-400">Item baru berstatus <strong class="text-zinc-200">Tersembunyi</strong>. Klik <strong class="text-emerald-400">Buka ke Peserta</strong> saat siap ditampilkan di HP peserta.</p>
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
                    <input type="url" name="image_url" placeholder="https://..." class="w-full rounded-xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-white">
                </div>
                <button type="submit" class="rounded-xl bg-emerald-500 px-4 py-2 text-xs font-bold text-zinc-950 hover:bg-emerald-400">
                    + Tambah
                </button>
            </div>
        </form>

        {{-- Daftar Item Katalog --}}
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($catalog ?? [] as $c)
                <div class="flex flex-col justify-between rounded-2xl border {{ !empty($c['is_revealed']) ? 'border-emerald-500/40 bg-zinc-950' : 'border-zinc-800 bg-zinc-950/50' }} p-4">
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            @if(!empty($c['is_revealed']))
                                <span class="rounded-full border border-emerald-500/40 bg-emerald-500/10 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-400">
                                    DIBUKA KE PESERTA
                                </span>
                            @else
                                <span class="rounded-full border border-zinc-700 bg-zinc-900 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-zinc-400">
                                    TERSEMBUNYI
                                </span>
                            @endif

                            <form method="POST" action="{{ route('admin.auction.catalog.destroy', $c['id']) }}" onsubmit="return confirm('Hapus item {{ $c['name'] }} dari katalog?')">
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
                            @click="window.dispatchEvent(new CustomEvent('pick-catalog-item', { detail: { name: @js($c['name']), price: {{ (int) ($c['starting_price'] ?? 0) }} } }))"
                            class="rounded-xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs font-semibold text-zinc-200 hover:border-gold-500/50"
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
        winner: null,
        item: '',
        price: '',
        recent: cfg.recent,
        busy: false,
        successMsg: '',
        errorMsg: '',
        idemKey: (crypto.randomUUID ? crypto.randomUUID() : String(Date.now())),

        init() {
            window.addEventListener('pick-catalog-item', (e) => {
                this.item = e.detail.name;
                this.price = e.detail.price || '';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        },

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
