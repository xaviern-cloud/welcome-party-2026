@extends('layouts.app')

@section('title', 'Katalog Lelang — Welcome Party 2026')

@section('content')
<script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.5.0/model-viewer.min.js"></script>

<div
    x-data="{
        items: @js($items),
        live: @js($liveAuction ?? ['active' => false]),
        payCfg: @js($paymentConfig ?? []),
        tableState: @js($tableState ?? ['catalog_locked' => false]),
        myNim: '{{ $myNim }}',
        activePayItem: null,
        copiedText: '',
        init() {
            setInterval(async () => {
                try {
                    const res = await fetch('{{ $stateUrl }}?t=' + Date.now(), {
                        cache: 'no-store',
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (data.catalog) this.items = data.catalog;
                    if (data.live_auction) this.live = data.live_auction;
                    if (data.payment_config) this.payCfg = data.payment_config;
                    if (data.table_state) this.tableState = data.table_state;
                } catch (e) {}
            }, 3000);
        },
        copy(txt) {
            navigator.clipboard?.writeText(txt);
            this.copiedText = txt;
            setTimeout(() => this.copiedText = '', 2000);
        }
    }"
    x-init="init()"
    class="mx-auto max-w-3xl space-y-6"
>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-white">Katalog Lelang</h1>
            <p class="text-xs text-zinc-400">Putar item 3D dengan jari • Status terbarui otomatis</p>
        </div>
        <a href="{{ route('participant.dashboard') }}" class="rounded-xl border border-zinc-700 bg-zinc-900 px-3.5 py-2 text-xs font-semibold text-zinc-200 hover:bg-zinc-800">
            ← Dompet
        </a>
    </div>

    {{-- LAYAR TERKUNCI JIKA BELUM TRANSFER SALDO KE JURU BICARA MEJA --}}
    <template x-if="tableState && tableState.catalog_locked">
        <div class="rounded-3xl border-2 border-red-500/60 bg-red-950/40 p-8 text-center shadow-2xl">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-red-500/20 text-2xl">🔒</div>
            <h2 class="mt-4 text-xl font-extrabold text-white">Katalog Lelang Terkunci</h2>
            <p class="mt-2 text-xs leading-relaxed text-red-200">
                Sesi pengumpulan saldo ke Juru Bicara Meja telah ditutup oleh operator, dan akun Anda tercatat <strong>belum mentransfer saldo</strong> ke Juru Bicara (<span x-text="tableState.table_name"></span>).
            </p>
            <a href="{{ route('participant.dashboard') }}" class="mt-5 inline-block rounded-xl bg-gold-500 px-5 py-2.5 text-xs font-extrabold text-zinc-950">
                ← Kembali ke Dompet
            </a>
        </div>
    </template>

    {{-- KONTEN UTAMA KATALOG (JIKA TIDAK TERKUNCI) --}}
    <div x-show="!(tableState && tableState.catalog_locked)" class="space-y-6">
        {{-- Modal / Halaman Pembayaran Uang Asli Pemenang --}}
        <template x-if="activePayItem">
            <div class="space-y-4 rounded-3xl border-2 border-emerald-500/50 bg-zinc-900 p-6 shadow-2xl">
                <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                    <div>
                        <span class="rounded bg-emerald-500/20 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-300">Pembayaran Lelang Uang Asli</span>
                        <h2 class="mt-1 text-lg font-extrabold text-white" x-text="'[' + activePayItem.code + '] ' + activePayItem.name"></h2>
                    </div>
                    <button type="button" @click="activePayItem = null" class="rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-xs text-zinc-300">Tutup ✕</button>
                </div>

                <div class="rounded-2xl border border-emerald-500/30 bg-emerald-950/30 p-4 text-center">
                    <div class="text-xs text-zinc-400">Total Tagihan Deal Lelang</div>
                    <div class="mt-1 font-mono text-3xl font-black text-emerald-400" x-text="'Rp ' + formatNum(activePayItem.sold_price || 0)"></div>
                </div>

                <p class="text-xs text-zinc-300" x-text="payCfg.instructions"></p>

                <template x-if="payCfg.qris_image_url">
                    <div class="flex flex-col items-center rounded-2xl border border-zinc-800 bg-zinc-950 p-4">
                        <div class="mb-2 text-xs font-bold text-gold-400" x-text="payCfg.qris_label || 'QRIS Panitia'"></div>
                        <img :src="payCfg.qris_image_url" alt="QRIS" class="max-h-64 rounded-xl bg-white p-2">
                    </div>
                </template>

                <div class="space-y-2">
                    <template x-for="(acc, i) in (payCfg.accounts || [])" :key="i">
                        <div class="flex items-center justify-between rounded-xl border border-zinc-800 bg-zinc-950 p-3 text-xs">
                            <div>
                                <div class="font-bold text-gold-400" x-text="acc.provider"></div>
                                <div class="font-mono text-sm font-extrabold text-white" x-text="acc.number"></div>
                                <div class="text-[11px] text-zinc-400" x-text="'a.n. ' + acc.holder"></div>
                            </div>
                            <button type="button" @click="copy(acc.number)" class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-[11px] font-bold text-emerald-300">
                                <span x-text="copiedText === acc.number ? 'Tersalin ✓' : 'Salin'"></span>
                            </button>
                        </div>
                    </template>
                </div>

                <template x-if="payCfg.whatsapp">
                    <a :href="'https://wa.me/' + payCfg.whatsapp + '?text=' + encodeURIComponent('Halo Panitia, saya #' + myNim + ' ingin konfirmasi pembayaran lelang [' + activePayItem.code + '] ' + activePayItem.name + ' senilai Rp ' + formatNum(activePayItem.sold_price || 0))"
                       target="_blank"
                       class="block w-full rounded-xl bg-emerald-500 py-3 text-center text-xs font-extrabold text-zinc-950 hover:bg-emerald-400">
                        💬 Kirim Bukti Pembayaran via WhatsApp
                    </a>
                </template>
            </div>
        </template>

        {{-- Banner Live Bidding Aktif di Panggung --}}
        <template x-if="live && live.active && !activePayItem">
            <div class="rounded-2xl border border-emerald-500/50 bg-gradient-to-r from-emerald-950/50 via-zinc-900 to-zinc-950 p-4 shadow-lg">
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-2 text-[11px] font-extrabold uppercase tracking-wider text-emerald-400">
                        <span class="h-2 w-2 animate-ping rounded-full bg-emerald-400"></span>
                        Sedang Dilelang di Panggung
                    </span>
                    <span class="rounded bg-gold-500/20 px-2 py-0.5 font-mono text-[11px] font-bold text-gold-400" x-text="live.code"></span>
                </div>
                <div class="mt-2 flex items-center justify-between gap-4">
                    <div>
                        <div class="text-base font-extrabold text-white" x-text="live.name"></div>
                        <div class="text-xs text-zinc-400">
                            Highest Bidder: <strong class="text-zinc-200" x-text="live.bidder_name ? (live.bidder_name + ' (#' + live.bidder_nim + ')') : 'Belum ada'"></strong>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] uppercase text-zinc-400">Current Bid</div>
                        <div class="font-mono text-lg font-black"
                             :class="live.currency === 'cash' ? 'text-emerald-400' : 'text-gold-400'"
                             x-text="live.currency === 'cash' ? ('Rp ' + formatNum(live.current_bid)) : (formatNum(live.current_bid) + ' Coins')"></div>
                    </div>
                </div>
            </div>
        </template>

        {{-- Grid Katalog Item dengan Interactive 3D Viewer --}}
        <div x-show="!activePayItem">
            <template x-if="items.length === 0">
                <div class="rounded-2xl border border-zinc-800 bg-zinc-900/70 py-14 text-center text-xs text-zinc-500">
                    Belum ada item lelang yang dibuka oleh panitia.
                </div>
            </template>

            <div class="grid gap-4 sm:grid-cols-2">
                <template x-for="item in items" :key="item.id">
                    <div class="flex flex-col justify-between overflow-hidden rounded-2xl border bg-zinc-900/85 shadow-xl"
                         :class="item.is_sold ? 'border-red-500/40' : 'border-zinc-800'">
                        <div>
                            {{-- Interactive 3D Viewer (GLB atau 3D Spin Gambar) --}}
                            <template x-if="item.image_url">
                                <div
                                    x-data="{
                                        rY: 0, dragging: false, lastX: 0,
                                        isGlb(u) { return /\.(glb|gltf)(\?|$)/i.test(u || ''); },
                                        init() { setInterval(() => { if (!this.dragging) this.rY = (this.rY + 0.7) % 360; }, 20); }
                                    }"
                                    class="relative h-52 w-full overflow-hidden bg-zinc-950"
                                >
                                    <template x-if="isGlb(item.image_url)">
                                        <model-viewer
                                            :src="item.image_url"
                                            :alt="item.name"
                                            auto-rotate
                                            auto-rotate-delay="0"
                                            rotation-per-second="35deg"
                                            camera-controls
                                            environment-image="neutral"
                                            style="width: 100%; height: 100%;"
                                        ></model-viewer>
                                    </template>
                                    <template x-if="!isGlb(item.image_url)">
                                        <div
                                            @touchstart.passive="dragging = true; lastX = $event.touches[0].clientX"
                                            @touchmove.passive="if(dragging){ rY += ($event.touches[0].clientX - lastX)*0.8; lastX = $event.touches[0].clientX; }"
                                            @touchend="dragging = false"
                                            @mousedown="dragging = true; lastX = $event.clientX"
                                            @mousemove="if(dragging){ rY += ($event.clientX - lastX)*0.8; lastX = $event.clientX; }"
                                            @mouseup="dragging = false"
                                            @mouseleave="dragging = false"
                                            style="perspective: 900px;"
                                            class="flex h-full w-full cursor-grab items-center justify-center p-3 select-none"
                                        >
                                            <img :src="item.image_url" :alt="item.name" draggable="false"
                                                 :style="`transform: rotateY(${rY}deg);`"
                                                 class="h-40 w-40 rounded-xl border border-gold-500/40 object-cover shadow-xl">
                                        </div>
                                    </template>
                                    <span class="pointer-events-none absolute bottom-2 right-2 rounded bg-black/75 px-2 py-0.5 text-[9px] font-bold text-gold-400">🔄 3D • Geser</span>
                                </div>
                            </template>

                            <div class="p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="rounded bg-gold-500/20 px-2 py-0.5 font-mono text-[11px] font-extrabold text-gold-400" x-text="item.code"></span>
                                    <span class="rounded px-2 py-0.5 text-[10px] font-bold uppercase"
                                          :class="item.currency === 'cash' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-amber-500/20 text-amber-300'"
                                          x-text="item.currency === 'cash' ? 'UANG ASLI (RP)' : 'COINS'"></span>
                                </div>

                                <h3 class="mt-2 text-base font-bold text-white" x-text="item.name"></h3>
                                <p x-show="item.description" class="mt-1 text-xs text-zinc-400" x-text="item.description"></p>

                                <div class="mt-3 font-mono text-xs font-bold" :class="item.currency === 'cash' ? 'text-emerald-400' : 'text-gold-400'">
                                    Open Bid: <span x-text="item.currency === 'cash' ? ('Rp ' + formatNum(item.starting_price || 0)) : (formatNum(item.starting_price || 0) + ' Coins')"></span>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-zinc-800/80 px-4 py-3">
                            <template x-if="!item.is_sold">
                                <div class="text-center text-[11px] font-semibold text-emerald-400">Tersedia untuk Dilelang</div>
                            </template>
                            <template x-if="item.is_sold">
                                <div class="space-y-2 text-xs">
                                    <div class="flex items-center justify-between text-red-300">
                                        <span class="font-bold">TERJUAL</span>
                                        <span class="font-mono font-bold" x-text="item.currency === 'cash' ? ('Rp ' + formatNum(item.sold_price || 0)) : (formatNum(item.sold_price || 0) + ' Coins')"></span>
                                    </div>
                                    <div class="text-[11px] text-zinc-400">
                                        Pemenang: <strong class="text-white" x-text="(item.winner_name || '-') + (item.winner_nim ? ' (#' + item.winner_nim + ')' : '')"></strong>
                                    </div>
                                    <template x-if="item.currency === 'cash' && item.winner_nim === myNim">
                                        <button type="button" @click="activePayItem = item"
                                                class="w-full rounded-xl bg-emerald-500 py-2 text-xs font-extrabold text-zinc-950 hover:bg-emerald-400">
                                            💳 Bayar Sekarang (Lihat QRIS / Rekening)
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection
