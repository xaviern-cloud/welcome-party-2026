@extends('layouts.app')

@section('title', 'Katalog & Live Lelang — Welcome Party 2026')

@section('content')
<div
    x-data="{
        items: @js($items),
        live: @js($liveAuction ?? ['active' => false]),
        payCfg: @js($paymentConfig ?? []),
        myNim: @js($myNim),
        copied: '',
        init() {
            const sync = async () => {
                try {
                    const res = await fetch('{{ $stateUrl }}?t=' + Date.now(), {
                        cache: 'no-store',
                        headers: { 'Accept': 'application/json' }
                    });
                    if (res.ok) {
                        const data = await res.json();
                        if (Array.isArray(data.catalog)) this.items = data.catalog;
                        if (data.live_auction) this.live = data.live_auction;
                        if (data.payment_config) this.payCfg = data.payment_config;
                    }
                } catch (e) {}
            };
            sync();
            setInterval(sync, 1500);
        },
        fmt(n) {
            return new Intl.NumberFormat('id-ID').format(Number(n || 0));
        },
        copyText(txt) {
            navigator.clipboard?.writeText(txt);
            this.copied = txt;
            setTimeout(() => this.copied = '', 2000);
        }
    }"
    x-init="init()"
    class="mx-auto max-w-2xl space-y-6"
>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-white">Katalog & Live Lelang</h1>
            <p class="text-xs text-zinc-400">Pantau penawaran tertinggi secara real-time di panggung.</p>
        </div>
        <a href="{{ route('participant.dashboard') }}" class="rounded-xl border border-zinc-700 bg-zinc-900 px-3.5 py-2 text-xs font-semibold text-zinc-200 hover:border-gold-500/50">
            ← Dompet
        </a>
    </div>

    {{-- LIVE BIDDING DI PANGGUNG SAAT INI --}}
    <template x-if="live && live.active">
        <div class="overflow-hidden rounded-2xl border-2 p-4 shadow-xl"
             :class="live.currency === 'cash' ? 'border-emerald-500/60 bg-emerald-950/25' : 'border-gold-500/60 bg-amber-950/25'">
            <div class="flex items-center justify-between gap-2">
                <span class="flex items-center gap-1.5 text-[11px] font-extrabold uppercase tracking-wider text-emerald-300">
                    <span class="h-2 w-2 animate-ping rounded-full bg-emerald-400"></span>
                    SEDANG DILELANG DI PANGGUNG
                </span>
                <span class="rounded bg-gold-500/20 px-2 py-0.5 font-mono text-[11px] font-bold text-gold-400" x-text="live.code"></span>
            </div>

            <div class="mt-3 flex gap-3.5">
                <template x-if="live.image_url">
                    <img :src="live.image_url" :alt="live.name" class="h-20 w-20 shrink-0 rounded-xl bg-zinc-950 object-cover">
                </template>
                <div class="min-w-0 flex-1">
                    <div class="text-base font-extrabold text-white" x-text="live.name"></div>
                    <p x-show="live.description" class="mt-0.5 line-clamp-2 text-xs text-zinc-300" x-text="live.description"></p>
                    <div class="mt-2 flex flex-wrap items-baseline justify-between gap-2 rounded-xl bg-zinc-950/80 px-3 py-2">
                        <div>
                            <div class="text-[10px] uppercase text-zinc-400">Current Bid</div>
                            <div class="font-mono text-base font-black"
                                 :class="live.currency === 'cash' ? 'text-emerald-400' : 'text-gold-400'"
                                 x-text="live.currency === 'cash' ? ('Rp ' + fmt(live.current_bid)) : (fmt(live.current_bid) + ' Coins')"></div>
                        </div>
                        <div class="text-right">
                            <div class="text-[10px] uppercase text-zinc-400">Highest Bidder</div>
                            <div class="text-xs font-bold text-white" x-text="live.bidder_name ? (live.bidder_name + ' (#' + live.bidder_nim + ')') : 'Belum ada'"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- DAFTAR KATALOG ITEM --}}
    <template x-if="items.length === 0">
        <div class="rounded-2xl border border-zinc-800 bg-zinc-900/70 px-6 py-14 text-center">
            <div class="text-sm font-bold text-zinc-300">Katalog Masih Tertutup</div>
            <p class="mt-1 text-xs text-zinc-500">Item lelang akan muncul secara otomatis di sini begitu dibuka oleh panitia.</p>
        </div>
    </template>

    <div class="grid gap-4 sm:grid-cols-2">
        <template x-for="(item, idx) in items" :key="item.id">
            <div
                class="overflow-hidden rounded-2xl border bg-zinc-900/90 shadow-lg"
                :class="item.is_sold ? 'border-red-500/40' : (item.currency === 'cash' ? 'border-emerald-500/40' : 'border-gold-500/30')"
            >
                <template x-if="item.image_url">
                    <img :src="item.image_url" :alt="item.name" class="h-44 w-full bg-zinc-950 object-cover">
                </template>
                <div class="p-4">
                    <div class="flex flex-wrap items-center justify-between gap-1.5">
                        <div class="flex items-center gap-1.5">
                            <span class="rounded bg-gold-500/20 px-2 py-0.5 font-mono text-[11px] font-extrabold text-gold-400" x-text="item.code || ('BRG-' + String(idx + 1).padStart(2, '0'))"></span>
                            <span
                                class="rounded px-2 py-0.5 text-[10px] font-bold uppercase"
                                :class="item.currency === 'cash' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-amber-500/20 text-amber-300'"
                                x-text="item.currency === 'cash' ? 'UANG ASLI (RP)' : 'COINS'"
                            ></span>
                        </div>
                        <template x-if="item.is_sold">
                            <span class="rounded-full border border-red-500/40 bg-red-500/15 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-red-300">
                                TERJUAL
                            </span>
                        </template>
                    </div>

                    <div class="mt-2 text-base font-bold text-white" x-text="item.name"></div>
                    <p x-show="item.description" class="mt-1 text-xs text-zinc-400" x-text="item.description"></p>

                    {{-- Jika SUDAH TERJUAL --}}
                    <template x-if="item.is_sold">
                        <div class="mt-4 space-y-2">
                            <div class="space-y-1.5 rounded-xl border border-red-500/30 bg-red-950/30 p-3 text-xs">
                                <div class="flex items-center justify-between text-zinc-400">
                                    <span>Terjual Kepada</span>
                                    <span class="font-bold text-white" x-text="(item.winner_name || 'Pemenang') + (item.winner_nim ? ' (#' + item.winner_nim + ')' : '')"></span>
                                </div>
                                <div class="flex items-center justify-between border-t border-red-500/20 pt-1.5">
                                    <span class="text-zinc-400">Harga Deal</span>
                                    <span class="font-mono text-sm font-extrabold"
                                          :class="item.currency === 'cash' ? 'text-emerald-400' : 'text-amber-400'"
                                          x-text="item.currency === 'cash' ? ('Rp ' + fmt(item.sold_price || 0)) : (fmt(item.sold_price || 0) + ' Coins')"></span>
                                </div>
                            </div>

                            {{-- KHUSUS PEMENANG UANG ASLI: Tampilkan Halaman Pembayaran Rekening & QRIS --}}
                            <template x-if="item.currency === 'cash' && String(item.winner_nim || '').toUpperCase() === String(myNim || '').toUpperCase()">
                                <div class="space-y-3 rounded-xl border-2 border-emerald-500/60 bg-emerald-950/40 p-3.5 text-xs">
                                    <div class="font-extrabold text-emerald-300">🎉 Selamat! Anda Pemenang Item Ini</div>
                                    <p class="text-[11px] text-zinc-300" x-text="payCfg.instructions || 'Silakan selesaikan pembayaran sebesar nominal deal di bawah ini:'"></p>

                                    <div class="rounded-lg bg-zinc-950 px-3 py-2 text-center">
                                        <div class="text-[10px] uppercase text-zinc-400">Total Pembayaran</div>
                                        <div class="font-mono text-lg font-black text-emerald-400" x-text="'Rp ' + fmt(item.sold_price || 0)"></div>
                                    </div>

                                    {{-- QRIS --}}
                                    <template x-if="payCfg.qris_image_url">
                                        <div class="rounded-xl border border-zinc-800 bg-white p-3 text-center text-zinc-900">
                                            <div class="mb-1.5 text-[11px] font-bold" x-text="payCfg.qris_label || 'QRIS Panitia'"></div>
                                            <img :src="payCfg.qris_image_url" alt="QRIS Pembayaran" class="mx-auto max-h-52 rounded object-contain">
                                        </div>
                                    </template>

                                    {{-- Daftar Rekening / E-Wallet --}}
                                    <template x-if="Array.isArray(payCfg.accounts) && payCfg.accounts.length > 0">
                                        <div class="space-y-1.5">
                                            <div class="text-[11px] font-semibold text-zinc-300">Transfer Rekening / E-Wallet:</div>
                                            <template x-for="(acc, aIdx) in payCfg.accounts" :key="aIdx">
                                                <div class="flex items-center justify-between rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2">
                                                    <div>
                                                        <div class="font-bold text-gold-400" x-text="acc.provider"></div>
                                                        <div class="font-mono text-xs font-bold text-white" x-text="acc.number"></div>
                                                        <div class="text-[10px] text-zinc-400" x-text="'a.n. ' + acc.holder"></div>
                                                    </div>
                                                    <button type="button" @click="copyText(acc.number)" class="rounded-lg border border-zinc-700 bg-zinc-900 px-2.5 py-1 text-[10px] font-semibold text-zinc-200">
                                                        <span x-text="copied === acc.number ? 'Tersalin ✓' : 'Salin'"></span>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    {{-- Tombol Konfirmasi WhatsApp --}}
                                    <template x-if="payCfg.whatsapp">
                                        <a
                                            :href="`https://wa.me/${payCfg.whatsapp}?text=${encodeURIComponent('Halo Panitia Welcome Party 2026, saya pemenang lelang [' + item.code + '] ' + item.name + ' senilai Rp ' + fmt(item.sold_price) + '. Berikut bukti pembayaran saya:')}`"
                                            target="_blank"
                                            class="block w-full rounded-xl bg-emerald-500 py-2.5 text-center text-xs font-extrabold text-zinc-950 hover:bg-emerald-400"
                                        >
                                            Konfirmasi Bukti Bayar via WhatsApp ↗
                                        </a>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Jika BELUM TERJUAL --}}
                    <template x-if="!item.is_sold">
                        <div class="mt-4 flex items-center justify-between border-t border-zinc-800 pt-3">
                            <span class="text-[11px] uppercase tracking-wider text-zinc-500">Open Bid</span>
                            <span class="font-mono text-sm font-extrabold"
                                  :class="item.currency === 'cash' ? 'text-emerald-400' : 'text-gold-400'"
                                  x-text="item.currency === 'cash' ? ('Rp ' + fmt(item.starting_price || 0)) : (fmt(item.starting_price || 0) + ' Coins')"></span>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
</div>
@endsection
