@extends('layouts.app')

@section('title', 'Lelang & Live Stage — Welcome Party 2026')

@section('content')
@php
    $catalogList = collect($catalog ?? [])->values()->map(function ($c, $idx) {
        $c['code'] = $c['code'] ?? ('BRG-' . str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT));
        $c['currency'] = $c['currency'] ?? 'coins';
        return $c;
    })->all();
@endphp

<div
    x-data="auctionDesk({
        searchUrl: '{{ route("admin.participants.search") }}',
        deductUrl: '{{ route("admin.auction.deduct") }}',
        liveUrl: '{{ route("admin.auction.live") }}',
        recent: @js($recent),
        catalog: @js($catalogList),
        live: @js($liveAuction ?? ['active' => false])
    })"
    x-init="init()"
    class="space-y-8"
>
    {{-- BAGIAN 0: KONTROL LIVE BIDDING BIG SCREEN --}}
    <div class="rounded-2xl border border-emerald-500/40 bg-zinc-900/90 p-6 shadow-xl">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-zinc-800 pb-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full" :class="live.active ? 'animate-ping bg-emerald-400' : 'bg-zinc-600'"></span>
                    <h2 class="text-lg font-bold text-emerald-400">Kontrol Live Bidding — Big Screen Lelang</h2>
                </div>
                <p class="mt-0.5 text-xs text-zinc-400">Ubah item, nominal penawaran tertinggi (Current Bid), dan nama penawar (Current Bidder) secara real-time di proyektor & HP peserta.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.payment.settings') }}" class="rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs font-semibold text-zinc-200 hover:border-gold-500/50">
                    ⚙ Atur Rekening & QRIS
                </a>
                <a href="{{ route('bigscreen.auction') }}" target="_blank" class="rounded-xl border border-emerald-500/40 bg-emerald-500/15 px-3.5 py-2 text-xs font-bold text-emerald-300 hover:bg-emerald-500/25">
                    Buka Layar Lelang ↗
                </a>
            </div>
        </div>

        <div x-show="liveToast" x-cloak class="mt-4 rounded-xl border border-emerald-500/40 bg-emerald-950/60 px-4 py-2.5 text-xs text-emerald-200" x-text="liveToast"></div>

        <div class="mt-5 grid gap-5 lg:grid-cols-12">
            {{-- Kolom Kiri: Item & Mata Uang --}}
            <div class="space-y-3 lg:col-span-5">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-semibold text-zinc-400">Item Sedang Dilelang</label>
                    <select @change="loadItemToLive($event.target.value); $event.target.value = ''" class="rounded-lg border border-zinc-700 bg-zinc-950 px-2.5 py-1 text-[11px] text-emerald-300">
                        <option value="">📺 Pilih item dari katalog...</option>
                        <template x-for="c in catalog" :key="c.id">
                            <option :value="c.id" x-text="'[' + c.code + '] ' + c.name + (c.currency === 'cash' ? ' (Rp)' : ' (Coins)')"></option>
                        </template>
                    </select>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <input type="text" x-model="liveForm.code" placeholder="ID (BRG-01)" class="rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-xs font-bold uppercase text-gold-400">
                    <input type="text" x-model="liveForm.name" placeholder="Nama Item..." class="col-span-2 rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-white">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="liveForm.currency = 'coins'"
                        :class="liveForm.currency === 'coins' ? 'border-amber-400 bg-amber-500/20 text-amber-300' : 'border-zinc-700 bg-zinc-950 text-zinc-400'"
                        class="rounded-xl border py-2 text-xs font-bold">
                        🪙 Poin (Coins)
                    </button>
                    <button type="button" @click="liveForm.currency = 'cash'"
                        :class="liveForm.currency === 'cash' ? 'border-emerald-400 bg-emerald-500/20 text-emerald-300' : 'border-zinc-700 bg-zinc-950 text-zinc-400'"
                        class="rounded-xl border py-2 text-xs font-bold">
                        💵 Uang Asli (Rp)
                    </button>
                </div>

                <input type="text" x-model="liveForm.description" placeholder="Deskripsi singkat item..." class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-white">
            </div>

            {{-- Kolom Tengah: Current Bid & Current Bidder --}}
            <div class="space-y-3 lg:col-span-4">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-zinc-400" x-text="liveForm.currency === 'cash' ? 'Current Bid (Rupiah)' : 'Current Bid (Coins)'"></label>
                    <input type="number" x-model.number="liveForm.current_bid" min="0" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3.5 py-2 font-mono text-lg font-extrabold text-white">
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        <template x-if="liveForm.currency === 'coins'">
                            <div class="flex gap-1.5">
                                <button type="button" @click="liveForm.current_bid = Number(liveForm.current_bid || 0) + 100" class="rounded-lg border border-zinc-700 bg-zinc-950 px-2.5 py-1 font-mono text-[11px] text-amber-300 hover:bg-zinc-800">+100</button>
                                <button type="button" @click="liveForm.current_bid = Number(liveForm.current_bid || 0) + 500" class="rounded-lg border border-zinc-700 bg-zinc-950 px-2.5 py-1 font-mono text-[11px] text-amber-300 hover:bg-zinc-800">+500</button>
                                <button type="button" @click="liveForm.current_bid = Number(liveForm.current_bid || 0) + 1000" class="rounded-lg border border-zinc-700 bg-zinc-950 px-2.5 py-1 font-mono text-[11px] text-amber-300 hover:bg-zinc-800">+1.000</button>
                            </div>
                        </template>
                        <template x-if="liveForm.currency === 'cash'">
                            <div class="flex gap-1.5">
                                <button type="button" @click="liveForm.current_bid = Number(liveForm.current_bid || 0) + 5000" class="rounded-lg border border-zinc-700 bg-zinc-950 px-2.5 py-1 font-mono text-[11px] text-emerald-300 hover:bg-zinc-800">+5rb</button>
                                <button type="button" @click="liveForm.current_bid = Number(liveForm.current_bid || 0) + 10000" class="rounded-lg border border-zinc-700 bg-zinc-950 px-2.5 py-1 font-mono text-[11px] text-emerald-300 hover:bg-zinc-800">+10rb</button>
                                <button type="button" @click="liveForm.current_bid = Number(liveForm.current_bid || 0) + 50000" class="rounded-lg border border-zinc-700 bg-zinc-950 px-2.5 py-1 font-mono text-[11px] text-emerald-300 hover:bg-zinc-800">+50rb</button>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="relative">
                    <label class="mb-1 block text-xs font-semibold text-zinc-400">Current Bidder (Ketik NIM atau Nama Akun)</label>
                    <div class="grid grid-cols-3 gap-2">
                        <input type="text" x-model="liveForm.bidder_nim" @input.debounce.150ms="searchLiveBidder()" placeholder="NIM (092)" class="rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-xs font-bold text-gold-400">
                        <input type="text" x-model="liveForm.bidder_name" @input.debounce.150ms="searchLiveBidderByName()" placeholder="Nama Bidder..." class="col-span-2 rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-white">
                    </div>
                    <div x-show="liveResults.length > 0" x-cloak class="absolute z-30 mt-1 max-h-44 w-full divide-y divide-zinc-800 overflow-y-auto rounded-xl border border-zinc-700 bg-zinc-950 shadow-2xl">
                        <template x-for="p in liveResults" :key="p.nim_code">
                            <button type="button" @click="pickLiveBidder(p)" class="flex w-full items-center justify-between px-3 py-2 text-left text-xs hover:bg-zinc-900">
                                <span><strong class="font-mono text-gold-400" x-text="'#' + p.nim_code"></strong> <span class="text-white" x-text="p.name"></span></span>
                                <span class="font-mono text-[11px] text-zinc-400" x-text="formatNum(p.balance) + ' Coins'"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Kolom Kanan: Tombol Push ke Big Screen & Deal --}}
            <div class="flex flex-col justify-between gap-2 rounded-xl border border-zinc-800 bg-zinc-950/70 p-4 lg:col-span-3">
                <div class="space-y-2">
                    <button type="button" @click="pushLive(true, false)" :disabled="liveBusy || !liveForm.name"
                        class="w-full rounded-xl bg-emerald-500 py-2.5 text-xs font-extrabold text-zinc-950 hover:bg-emerald-400 disabled:opacity-40">
                        🚀 Update & Tampilkan di Layar
                    </button>
                    <button type="button" @click="dealFromLive()" :disabled="!liveForm.name || !liveForm.bidder_nim || !liveForm.current_bid"
                        class="w-full rounded-xl border border-gold-500/50 bg-gold-500/15 py-2 text-xs font-bold text-gold-300 hover:bg-gold-500/25 disabled:opacity-40">
                        🔨 Deal! Masukkan ke Pemenang ↓
                    </button>
                </div>
                <button type="button" @click="pushLive(false, false)" :disabled="!live.active || liveBusy"
                    class="w-full rounded-xl border border-zinc-700 bg-zinc-900 py-2 text-xs text-zinc-400 hover:text-white disabled:opacity-40">
                    Tutup Layar Lelang (Standby)
                </button>
            </div>
        </div>
    </div>

    {{-- BAGIAN 1: INPUT PEMENANG LELANG & RIWAYAT LELANG --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-gold-500/30 bg-zinc-900/80 p-6">
            <h1 class="text-xl font-bold text-gold-400">Input Pemenang Lelang (Final Deal)</h1>
            <p class="mt-1 text-xs text-zinc-400">Semua akun terdaftar dapat menjadi pemenang. Pilih metode Coins atau Uang Asli (Rp).</p>

            <div x-show="successMsg" x-cloak class="mt-4 rounded-xl border border-emerald-500/40 bg-emerald-950/60 p-3 text-xs text-emerald-200" x-text="successMsg"></div>
            <div x-show="errorMsg" x-cloak class="mt-4 rounded-xl border border-red-500/40 bg-red-950/60 p-3 text-xs text-red-200" x-text="errorMsg"></div>

            <form @submit.prevent="submitAuction" class="mt-5 space-y-4">
                {{-- Switch Currency --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-zinc-400">Mata Uang Lelang</label>
                    <div class="grid grid-cols-2 gap-2.5">
                        <button type="button" @click="currency = 'coins'"
                            :class="currency === 'coins' ? 'border-amber-400 bg-amber-500/20 text-amber-300' : 'border-zinc-700 bg-zinc-950 text-zinc-400'"
                            class="rounded-xl border py-2.5 text-xs font-bold transition">
                            🪙 Poin (Potong Coins)
                        </button>
                        <button type="button" @click="currency = 'cash'"
                            :class="currency === 'cash' ? 'border-emerald-400 bg-emerald-500/20 text-emerald-300' : 'border-zinc-700 bg-zinc-950 text-zinc-400'"
                            class="rounded-xl border py-2.5 text-xs font-bold transition">
                            💵 Uang Asli (Rupiah / Rp)
                        </button>
                    </div>
                </div>

                {{-- Pilih Pemenang Lelang (Semua akun bisa ngebid) --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-zinc-400">Pemenang Lelang (Ketik 3 Digit NIM atau Nama Akun)</label>
                    <template x-if="winner">
                        <div class="flex items-center justify-between rounded-xl border border-gold-500/50 bg-gold-500/10 p-3.5">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded bg-gold-500/20 px-1.5 py-0.5 font-mono text-xs font-bold text-gold-400" x-text="'#' + winner.nim_code"></span>
                                    <span class="font-bold text-white" x-text="winner.name"></span>
                                </div>
                                <div class="mt-1 text-xs text-zinc-400">
                                    Kelompok: <span class="text-zinc-200" x-text="winner.group_name || '-'"></span> •
                                    Saldo Koin: <strong class="font-mono text-emerald-400" x-text="formatNum(winner.balance) + ' Coins'"></strong>
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
                                placeholder="Ketik NIM (mis. 056) atau nama akun..."
                                class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-sm text-white focus:border-gold-500 focus:outline-none"
                            >
                            <div x-show="searching" x-cloak class="mt-1.5 text-[11px] text-zinc-400">Mencari akun...</div>
                            <div x-show="!searching && q.trim() !== '' && results.length === 0" x-cloak class="mt-2 rounded-xl border border-zinc-800 bg-zinc-950 px-4 py-3 text-xs text-zinc-500">
                                Akun tidak ditemukan.
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
                                    <option
                                        :value="c.id"
                                        :disabled="c.is_sold"
                                        x-text="'[' + c.code + '] ' + c.name + (c.is_sold ? ' (TERJUAL)' : ' (' + (c.currency === 'cash' ? 'Rp ' : '') + formatNum(c.starting_price) + ')')"
                                    ></option>
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
                    <label class="mb-1.5 block text-xs font-semibold text-zinc-400" x-text="currency === 'cash' ? 'Harga Deal Akhir (Rupiah / Rp)' : 'Harga Deal Akhir (Coins)'"></label>
                    <input
                        id="auction-price-input"
                        type="number"
                        x-model.number="price"
                        min="1"
                        required
                        placeholder="0"
                        class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 font-mono text-xl font-bold"
                        :class="currency === 'cash' ? 'text-emerald-400' : 'text-gold-400'"
                    >
                </div>

                {{-- Preview Kalkulasi Real-Time --}}
                <div x-show="winner || price > 0" x-cloak class="space-y-1.5 rounded-xl border border-zinc-800 bg-zinc-950/90 p-3.5 text-xs">
                    <div class="flex items-center justify-between text-zinc-400">
                        <span>Item Lelang</span>
                        <span class="font-semibold text-zinc-200" x-text="(itemCode ? '[' + itemCode + '] ' : '') + (item || '-')"></span>
                    </div>
                    <div class="flex items-center justify-between text-zinc-400">
                        <span>Harga Deal</span>
                        <span class="font-mono font-bold"
                              :class="currency === 'cash' ? 'text-emerald-400' : 'text-amber-400'"
                              x-text="currency === 'cash' ? ('Rp ' + formatNum(price || 0) + ' (Uang Murni)') : ('-' + formatNum(price || 0) + ' Coins')"></span>
                    </div>
                    <template x-if="winner && currency === 'coins'">
                        <div class="flex items-center justify-between border-t border-zinc-800 pt-1.5">
                            <span class="font-semibold text-zinc-300">Estimasi Sisa Saldo Koin Pemenang</span>
                            <span
                                class="font-mono text-sm font-extrabold"
                                :class="(winner.balance - (price || 0)) < 0 ? 'text-red-400' : 'text-emerald-400'"
                                x-text="formatNum(winner.balance - (price || 0)) + ' Coins'"
                            ></span>
                        </div>
                    </template>
                    <div x-show="currency === 'cash'" class="border-t border-zinc-800 pt-1.5 text-[11px] text-emerald-400">
                        ✓ Uang murni: Saldo Coins peserta tetap utuh, dan detail Rekening/QRIS pembayaran otomatis muncul di HP pemenang.
                    </div>
                    <div x-show="winner && currency === 'coins' && (winner.balance - (price || 0)) < 0" class="pt-1 text-[11px] font-semibold text-red-400">
                        ⚠ Saldo koin pemenang tidak mencukupi untuk harga deal ini!
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="busy || !winner || !price || !item || (currency === 'coins' && (winner.balance - price) < 0)"
                    class="w-full rounded-xl py-3 text-sm font-bold text-zinc-950 transition disabled:cursor-not-allowed disabled:opacity-40"
                    :class="currency === 'cash' ? 'bg-emerald-500 hover:bg-emerald-400' : 'bg-gold-500 hover:bg-gold-400'"
                    x-text="currency === 'cash' ? 'Simpan Pemenang Lelang (Uang Asli Rp)' : 'Potong Saldo Coins Pemenang'"
                ></button>
            </form>
        </div>

        {{-- Riwayat Lelang Terbaru --}}
        <div x-data="{ auctionExported: false }" class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-base font-bold text-white">Riwayat Lelang Terbaru</h2>
                <div class="flex items-center gap-2">
                    <a
                        href="{{ route('admin.auction.export') }}"
                        @click="auctionExported = true"
                        class="rounded-lg border border-emerald-500/40 bg-emerald-500/15 px-2.5 py-1.5 text-[11px] font-bold text-emerald-300 hover:bg-emerald-500/25"
                    >
                        1. Export XLS
                    </a>
                    <form
                        method="POST"
                        action="{{ route('admin.auction.reset') }}"
                        onsubmit="return confirm('Hapus seluruh riwayat transaksi lelang dan kembalikan status barang di katalog menjadi belum terjual?')"
                    >
                        @csrf
                        <button
                            type="submit"
                            :disabled="!auctionExported"
                            :title="auctionExported ? 'Reset riwayat lelang' : 'Klik Export XLS terlebih dahulu'"
                            class="rounded-lg border border-red-500/40 bg-red-500/15 px-2.5 py-1.5 text-[11px] font-bold text-red-300 hover:bg-red-500/25 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            2. Reset Riwayat
                        </button>
                    </form>
                </div>
            </div>
            <template x-if="recent.length === 0">
                <div class="py-12 text-center text-xs text-zinc-500">Belum ada transaksi pemotongan lelang.</div>
            </template>
            <div class="space-y-3">
                <template x-for="tx in recent" :key="tx.id">
                    <div class="flex items-center justify-between rounded-xl border border-zinc-800 bg-zinc-950/70 p-3.5 text-xs">
                        <div class="min-w-0 pr-3">
                            <div class="font-bold text-white" x-text="tx.item_label || tx.note || tx.title || tx.label || 'Item Lelang'"></div>
                            <div class="mt-0.5 font-medium text-emerald-400">
                                Terjual ke: <span x-text="tx.winner_text || tx.subtitle || '-'"></span>
                            </div>
                            <div class="mt-0.5 text-[11px] text-zinc-500" x-text="tx.time_text || tx.time || ''"></div>
                        </div>
                        <div class="text-right">
                            <div class="font-mono text-sm font-bold"
                                 :class="tx.is_rupiah ? 'text-emerald-400' : 'text-amber-400'"
                                 x-text="tx.is_rupiah ? ('Rp ' + formatNum(tx.amount)) : ('-' + formatNum(tx.amount))"></div>
                            <div class="text-[10px] text-zinc-500" x-text="tx.is_rupiah ? 'Uang Asli' : 'Coins'"></div>
                        </div>
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
                <p class="text-xs text-zinc-400">Setiap barang otomatis diberi ID unik (<strong class="font-mono text-zinc-200">BRG-01</strong>, dst.) dengan pilihan mata uang Poin atau Uang Asli (Rp).</p>
            </div>
        </div>

        {{-- Form Tambah Item Katalog --}}
        <form method="POST" action="{{ route('admin.auction.catalog.store') }}" class="mt-5 grid gap-3 rounded-xl border border-zinc-800 bg-zinc-950/60 p-4 sm:grid-cols-2 lg:grid-cols-5">
            @csrf
            <div>
                <label class="mb-1 block text-xs text-zinc-400">Nama Item *</label>
                <input type="text" name="name" required placeholder="Contoh: Mystery Box #1" class="w-full rounded-xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-white">
            </div>
            <div>
                <label class="mb-1 block text-xs text-zinc-400">Mata Uang *</label>
                <select name="currency" class="w-full rounded-xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-white">
                    <option value="coins">🪙 Poin (Coins)</option>
                    <option value="cash">💵 Uang Asli (Rp)</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-zinc-400">Open Bid *</label>
                <input type="number" name="starting_price" min="0" value="1000" required class="w-full rounded-xl border border-zinc-700 bg-zinc-900 px-3 py-2 font-mono text-xs text-white">
            </div>
            <div>
                <label class="mb-1 block text-xs text-zinc-400">Deskripsi Singkat</label>
                <input type="text" name="description" placeholder="Benefit / keterangan hadiah" class="w-full rounded-xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-white">
            </div>
            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <label class="mb-1 block text-xs text-zinc-400">URL Gambar</label>
                    <input type="text" name="image_url" placeholder="https://..." class="w-full rounded-xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-white">
                </div>
                <button type="submit" class="rounded-xl bg-emerald-500 px-4 py-2 text-xs font-bold text-zinc-950 hover:bg-emerald-400">
                    + Tambah
                </button>
            </div>
        </form>

        {{-- Daftar Item Katalog --}}
        <template x-if="catalog.length === 0">
            <div class="py-10 text-center text-xs text-zinc-500">
                Belum ada item di katalog lelang. Tambahkan item baru menggunakan form di atas.
            </div>
        </template>

        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <template x-for="c in catalog" :key="c.id">
                <div
                    class="flex flex-col justify-between rounded-2xl border p-4"
                    :class="c.is_sold ? 'border-red-500/40 bg-zinc-950/90' : (c.is_revealed ? 'border-emerald-500/40 bg-zinc-950' : 'border-zinc-800 bg-zinc-950/50')"
                >
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="rounded bg-gold-500/20 px-2 py-0.5 font-mono text-[11px] font-extrabold text-gold-400" x-text="c.code"></span>
                                <span
                                    class="rounded px-2 py-0.5 text-[10px] font-bold uppercase"
                                    :class="c.currency === 'cash' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-amber-500/20 text-amber-300'"
                                    x-text="c.currency === 'cash' ? 'UANG ASLI (RP)' : 'COINS'"
                                ></span>
                                <template x-if="c.is_sold">
                                    <span class="rounded-full border border-red-500/40 bg-red-500/15 px-2 py-0.5 text-[10px] font-bold uppercase text-red-300">TERJUAL</span>
                                </template>
                                <template x-if="!c.is_sold && c.is_revealed">
                                    <span class="rounded-full border border-emerald-500/40 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-400">DIBUKA</span>
                                </template>
                                <template x-if="!c.is_sold && !c.is_revealed">
                                    <span class="rounded-full border border-zinc-700 bg-zinc-900 px-2 py-0.5 text-[10px] font-bold uppercase text-zinc-400">TERSEMBUNYI</span>
                                </template>
                            </div>

                            <form method="POST" :action="`{{ url('/admin/auction/catalog') }}/${c.id}`" @submit="if(!confirm(`Hapus [${c.code}] ${c.name} dari katalog?`)) $event.preventDefault()">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:underline">Hapus</button>
                            </form>
                        </div>

                        <template x-if="c.image_url">
                            <img :src="c.image_url" :alt="c.name" class="mt-3 h-32 w-full rounded-xl bg-zinc-900 object-cover">
                        </template>

                        <div class="mt-3 text-base font-bold text-white" x-text="c.name"></div>
                        <p x-show="c.description" class="mt-1 text-xs text-zinc-400" x-text="c.description"></p>
                        <div class="mt-2 font-mono text-xs font-bold" :class="c.currency === 'cash' ? 'text-emerald-400' : 'text-gold-400'">
                            Open Bid: <span x-text="c.currency === 'cash' ? ('Rp ' + formatNum(c.starting_price || 0)) : (formatNum(c.starting_price || 0) + ' Coins')"></span>
                        </div>

                        <template x-if="c.is_sold">
                            <div class="mt-3 rounded-xl border border-red-500/30 bg-red-950/30 px-3 py-2 text-xs">
                                <div class="font-bold text-red-300">TERJUAL</div>
                                <div class="mt-0.5 text-zinc-200">
                                    Pemenang: <strong x-text="(c.winner_name || '-') + (c.winner_nim ? ' (#' + c.winner_nim + ')' : '')"></strong>
                                </div>
                                <div class="font-mono text-amber-400">
                                    Harga Deal: <strong x-text="(c.currency === 'cash' ? 'Rp ' : '') + formatNum(c.sold_price || 0) + (c.currency === 'cash' ? '' : ' Coins')"></strong>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-zinc-800 pt-3">
                        <form method="POST" :action="`{{ url('/admin/auction/catalog') }}/${c.id}/toggle`" class="flex-1">
                            @csrf
                            <button
                                type="submit"
                                class="w-full rounded-xl py-2 text-xs font-bold"
                                :class="c.is_revealed ? 'border border-amber-500/40 bg-amber-500/10 text-amber-300 hover:bg-amber-500/20' : 'bg-emerald-500 text-zinc-950 hover:bg-emerald-400'"
                                x-text="c.is_revealed ? 'Sembunyikan' : 'Buka ke Peserta'"
                            ></button>
                        </form>
                        <button
                            type="button"
                            @click="pushCatalogCardToLive(c)"
                            class="rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-2.5 py-2 text-xs font-bold text-emerald-300 hover:bg-emerald-500/20"
                            title="Tampilkan ke Big Screen Lelang"
                        >
                            📺 Layar
                        </button>
                        <button
                            type="button"
                            @click="selectCatalogItem(c)"
                            class="rounded-xl border px-3 py-2 text-xs font-bold"
                            :class="c.is_sold ? 'border-zinc-700 bg-zinc-900 text-zinc-400' : 'border-gold-500/40 bg-zinc-900 text-gold-400 hover:bg-gold-500/15'"
                            x-text="c.is_sold ? 'Terjual ✓' : 'Deal →'"
                        ></button>
                    </div>
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
        searching: false,
        winner: null,
        currency: 'coins',
        itemCode: '',
        item: '',
        price: '',
        recent: cfg.recent,
        catalog: cfg.catalog || [],
        busy: false,
        successMsg: '',
        errorMsg: '',
        idemKey: (crypto.randomUUID ? crypto.randomUUID() : String(Date.now())),

        // Live Bidding State
        live: cfg.live || { active: false },
        liveForm: {
            code: cfg.live?.code || '',
            name: cfg.live?.name || '',
            description: cfg.live?.description || '',
            image_url: cfg.live?.image_url || '',
            currency: cfg.live?.currency || 'coins',
            current_bid: cfg.live?.current_bid || 0,
            bidder_nim: cfg.live?.bidder_nim || '',
            bidder_name: cfg.live?.bidder_name || '',
        },
        liveResults: [],
        liveBusy: false,
        liveToast: '',

        init() {},

        loadItemToLive(id) {
            const c = this.catalog.find(x => x.id === id);
            if (!c) return;
            this.liveForm.code = c.code || '';
            this.liveForm.name = c.name || '';
            this.liveForm.description = c.description || '';
            this.liveForm.image_url = c.image_url || '';
            this.liveForm.currency = c.currency || 'coins';
            this.liveForm.current_bid = Number(c.starting_price || 0);
            this.liveForm.bidder_nim = '';
            this.liveForm.bidder_name = '';
        },

        async pushCatalogCardToLive(c) {
            this.loadItemToLive(c.id);
            await this.pushLive(true, false);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        async searchLiveBidder() {
            const q = (this.liveForm.bidder_nim || '').trim();
            if (!q) { this.liveResults = []; return; }
            const res = await fetch(`${cfg.searchUrl}?q=${encodeURIComponent(q)}`, { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const list = (await res.json()).results || [];
                this.liveResults = list;
                if (/^\d{3}$/.test(q) && list.length === 1 && list[0].nim_code === q) {
                    this.pickLiveBidder(list[0]);
                }
            }
        },

        async searchLiveBidderByName() {
            const q = (this.liveForm.bidder_name || '').trim();
            if (q.length < 2) { this.liveResults = []; return; }
            const res = await fetch(`${cfg.searchUrl}?q=${encodeURIComponent(q)}`, { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                this.liveResults = (await res.json()).results || [];
            }
        },

        pickLiveBidder(p) {
            this.liveForm.bidder_nim = p.nim_code;
            this.liveForm.bidder_name = p.name;
            this.liveResults = [];
        },

        async pushLive(active, isSold = false) {
            this.liveBusy = true;
            try {
                const res = await window.apiPost(cfg.liveUrl, {
                    active: active,
                    is_sold: isSold,
                    ...this.liveForm
                });
                this.live = res.live;
                this.liveToast = res.message;
                setTimeout(() => this.liveToast = '', 3000);
            } catch (e) {
                alert(e.message);
            } finally {
                this.liveBusy = false;
            }
        },

        dealFromLive() {
            this.itemCode = this.liveForm.code;
            this.item = this.liveForm.name;
            this.currency = this.liveForm.currency;
            this.price = this.liveForm.current_bid;
            if (this.liveForm.bidder_nim) {
                this.winner = {
                    nim_code: this.liveForm.bidder_nim,
                    name: this.liveForm.bidder_name || ('#' + this.liveForm.bidder_nim),
                    group_name: '',
                    balance: 999999999
                };
                // Ambil saldo real akun pemenang
                fetch(`${cfg.searchUrl}?q=${encodeURIComponent(this.liveForm.bidder_nim)}`, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(d => {
                        const exact = (d.results || []).find(x => x.nim_code === this.liveForm.bidder_nim);
                        if (exact) this.winner = exact;
                    });
            }
            document.getElementById('auction-price-input')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },

        selectCatalogItem(c) {
            this.itemCode = c.code || '';
            this.item = c.name || '';
            this.currency = c.currency || 'coins';
            this.price = c.starting_price || '';
            document.getElementById('auction-price-input')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => document.getElementById('auction-price-input')?.focus(), 250);
        },

        pickFromDropdown(id) {
            const found = this.catalog.find(c => c.id === id);
            if (!found) return;
            this.selectCatalogItem(found);
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
                // Tanpa checked_in=1 agar siapa pun pemilik akun bisa ngebid
                const res = await fetch(`${cfg.searchUrl}?q=${encodeURIComponent(query)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const list = (await res.json()).results || [];
                    this.results = list;
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
            const nominalText = this.currency === 'cash'
                ? `Rp ${formatNum(this.price)} (Uang Asli)`
                : `${formatNum(this.price)} Coins`;
            if (!confirm(`Tetapkan ${this.winner.name} (#${this.winner.nim_code}) sebagai pemenang "${label}" senilai ${nominalText}?`)) return;
            this.busy = true;
            this.errorMsg = '';
            try {
                const res = await window.apiPost(cfg.deductUrl, {
                    winner: this.winner.nim_code,
                    price: Number(this.price),
                    currency: this.currency,
                    item_code: this.itemCode,
                    item: this.item,
                    idempotency_key: this.idemKey
                });
                this.successMsg = res.message;
                this.recent.unshift(res.transaction);

                if (res.sold_code) {
                    const target = this.catalog.find(c => (c.code || '').toUpperCase() === res.sold_code.toUpperCase());
                    if (target) {
                        target.is_sold = true;
                        target.currency = res.currency;
                        target.winner_name = res.winner_name;
                        target.winner_nim = res.winner_nim;
                        target.sold_price = res.sold_price;
                    }
                }

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
