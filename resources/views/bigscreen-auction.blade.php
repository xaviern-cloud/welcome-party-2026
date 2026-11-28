<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Auction — Welcome Party 2026</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.5.0/model-viewer.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700;800;900&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        [x-cloak] { display: none !important; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: radial-gradient(circle at 20% 15%, rgba(212, 175, 55, 0.16), transparent 45%),
                        radial-gradient(circle at 80% 85%, rgba(16, 185, 129, 0.14), transparent 45%),
                        #070709;
        }
        .font-display { font-family: 'Cinzel', serif; }
    </style>
</head>
<body class="min-h-screen text-white antialiased selection:bg-amber-400 selection:text-black">
    <div
        x-data="{
            live: @js($initialState),
            init() {
                const sync = async () => {
                    try {
                        const res = await fetch('{{ $stateUrl }}?t=' + Date.now(), {
                            cache: 'no-store',
                            headers: { 'Accept': 'application/json' }
                        });
                        if (res.ok) this.live = await res.json();
                    } catch (e) {}
                };
                setInterval(sync, 1000);
            },
            fmt(n) {
                return new Intl.NumberFormat('id-ID').format(Number(n || 0));
            }
        }"
        x-init="init()"
        class="flex min-h-screen flex-col justify-between px-8 py-6 lg:px-14 lg:py-8"
    >
        {{-- Header --}}
        <header class="flex items-center justify-between border-b border-zinc-800/80 pb-4">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-amber-400/50 bg-amber-400/10 font-display text-2xl font-black text-amber-400">
                    W
                </div>
                <div>
                    <div class="font-display text-xl font-extrabold tracking-widest text-amber-400">WELCOME PARTY 2026</div>
                    <div class="text-xs font-semibold uppercase tracking-[0.25em] text-zinc-400">Exclusive Live Auction Stage</div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="flex items-center gap-2 rounded-full border border-emerald-500/40 bg-emerald-500/10 px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-emerald-300">
                    <span class="h-2.5 w-2.5 animate-ping rounded-full bg-emerald-400"></span>
                    LIVE SYNC
                </span>
            </div>
        </header>

        {{-- Standby Mode --}}
        <template x-if="!live || !live.active">
            <main class="my-auto flex flex-col items-center justify-center py-16 text-center">
                <div class="rounded-full border border-amber-400/30 bg-amber-400/10 px-5 py-1.5 text-xs font-bold uppercase tracking-[0.3em] text-amber-300">
                    The Penthouse of Freedom
                </div>
                <h1 class="mt-6 font-display text-5xl font-black tracking-wide text-white lg:text-7xl">
                    LIVE AUCTION
                </h1>
                <p class="mt-4 max-w-xl text-lg text-zinc-400">
                    Siapkan saldo koin dan penawaran terbaikmu. Item eksklusif berikutnya segera naik ke panggung!
                </p>
            </main>
        </template>

        {{-- Active Bidding Stage --}}
        <template x-if="live && live.active">
            <main class="my-auto grid items-center gap-10 py-6 lg:grid-cols-12">
                {{-- Kiri: Interactive 3D Product Viewer (Auto-Rotate + Mouse Drag 360°) --}}
                <div class="lg:col-span-5">
                    <div
                        x-data="{
                            rotY: 0,
                            rotX: 0,
                            dragging: false,
                            lastX: 0,
                            lastY: 0,
                            isGlb(url) { return /\.(glb|gltf)(\?|$)/i.test(url || ''); },
                            initSpin() {
                                setInterval(() => {
                                    if (!this.dragging) this.rotY = (this.rotY + 0.6) % 360;
                                }, 16);
                            },
                            startDrag(e) {
                                this.dragging = true;
                                const pt = e.touches ? e.touches[0] : e;
                                this.lastX = pt.clientX;
                                this.lastY = pt.clientY;
                            },
                            onDrag(e) {
                                if (!this.dragging) return;
                                const pt = e.touches ? e.touches[0] : e;
                                this.rotY += (pt.clientX - this.lastX) * 0.7;
                                this.rotX = Math.max(-25, Math.min(25, this.rotX - (pt.clientY - this.lastY) * 0.4));
                                this.lastX = pt.clientX;
                                this.lastY = pt.clientY;
                            },
                            endDrag() { this.dragging = false; }
                        }"
                        x-init="initSpin()"
                        class="relative overflow-hidden rounded-3xl border-2 bg-zinc-900/90 shadow-2xl"
                        :class="live.is_sold ? 'border-red-500/70' : (live.currency === 'cash' ? 'border-emerald-400/60' : 'border-amber-400/60')"
                    >
                        {{-- Model 3D (.glb / .gltf) --}}
                        <template x-if="live.image_url && isGlb(live.image_url)">
                            <div class="aspect-square w-full bg-gradient-to-b from-zinc-800 via-zinc-950 to-black">
                                <model-viewer
                                    :src="live.image_url"
                                    :alt="live.name"
                                    auto-rotate
                                    auto-rotate-delay="0"
                                    rotation-per-second="35deg"
                                    camera-controls
                                    shadow-intensity="1.2"
                                    environment-image="neutral"
                                    style="width: 100%; height: 100%;"
                                ></model-viewer>
                            </div>
                        </template>

                        {{-- Gambar 2D (.jpg / .png) dalam Mode 3D Spin Interaktif --}}
                        <template x-if="live.image_url && !isGlb(live.image_url)">
                            <div
                                @mousedown="startDrag($event)"
                                @mousemove="onDrag($event)"
                                @mouseup="endDrag()"
                                @mouseleave="endDrag()"
                                @touchstart.passive="startDrag($event)"
                                @touchmove.passive="onDrag($event)"
                                @touchend="endDrag()"
                                style="perspective: 1100px;"
                                class="flex aspect-square w-full cursor-grab items-center justify-center bg-gradient-to-b from-zinc-900 via-zinc-950 to-black p-6 select-none active:cursor-grabbing"
                            >
                                <div
                                    :style="`transform: rotateX(${rotX}deg) rotateY(${rotY}deg); transform-style: preserve-3d;`"
                                    class="relative h-4/5 w-4/5 rounded-2xl border-2 border-amber-400/40 bg-zinc-900 shadow-[0_20px_60px_rgba(245,158,11,0.28)]"
                                >
                                    <img :src="live.image_url" :alt="live.name" draggable="false" class="h-full w-full rounded-2xl object-cover">
                                </div>
                            </div>
                        </template>

                        <template x-if="!live.image_url">
                            <div class="flex aspect-square w-full flex-col items-center justify-center bg-zinc-950 text-zinc-600">
                                <span class="font-display text-6xl font-black text-amber-400/40" x-text="live.code || 'ITEM'"></span>
                                <span class="mt-2 text-xs uppercase tracking-widest">Exclusive Auction Item</span>
                            </div>
                        </template>

                        <div x-show="live.image_url && !live.is_sold" class="pointer-events-none absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full border border-zinc-700/80 bg-zinc-950/80 px-3 py-1 text-[10px] font-bold uppercase tracking-widest text-amber-300">
                            🔄 360° 3D View • Drag untuk memutar
                        </div>

                        {{-- Stamp TERJUAL / DEAL --}}
                        <div x-show="live.is_sold" x-cloak class="absolute inset-0 flex items-center justify-center bg-black/70 backdrop-blur-xs">
                            <div class="-rotate-12 rounded-2xl border-4 border-red-500 bg-red-950/90 px-8 py-4 text-center shadow-2xl">
                                <div class="text-4xl font-black tracking-widest text-red-400 lg:text-5xl">TERJUAL!</div>
                                <div class="mt-1 text-sm font-bold text-white" x-text="live.bidder_name ? ('Selamat kepada ' + live.bidder_name) : 'DEAL!'"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Kanan: Detail Item, Current Bid & Current Bidder --}}
                <div class="space-y-6 lg:col-span-7">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="rounded-xl bg-amber-400 px-3.5 py-1 font-mono text-base font-black text-zinc-950" x-text="live.code || 'LIVE'"></span>
                        <span
                            class="rounded-xl border px-4 py-1 text-sm font-extrabold uppercase tracking-wider"
                            :class="live.currency === 'cash' ? 'border-emerald-400/50 bg-emerald-500/20 text-emerald-300' : 'border-amber-400/50 bg-amber-500/20 text-amber-300'"
                            x-text="live.currency === 'cash' ? '💵 LELANG UANG ASLI (RUPIAH)' : '🪙 LELANG POIN (COINS)'"
                        ></span>
                    </div>

                    <div>
                        <h1 class="text-4xl font-black leading-tight text-white lg:text-6xl" x-text="live.name"></h1>
                        <p x-show="live.description" class="mt-3 text-lg leading-relaxed text-zinc-300 lg:text-xl" x-text="live.description"></p>
                    </div>

                    {{-- Kotak Current Bid --}}
                    <div class="rounded-3xl border-2 p-6 lg:p-8"
                         :class="live.currency === 'cash' ? 'border-emerald-500/50 bg-emerald-950/30' : 'border-amber-500/50 bg-amber-950/25'">
                        <div class="text-sm font-bold uppercase tracking-[0.2em] text-zinc-400"
                             x-text="live.is_sold ? 'HARGA DEAL AKHIR' : 'CURRENT HIGHEST BID'"></div>
                        <div class="mt-2 font-mono text-5xl font-black tracking-tight lg:text-7xl"
                             :class="live.currency === 'cash' ? 'text-emerald-400' : 'text-amber-400'"
                             x-text="live.currency === 'cash' ? ('Rp ' + fmt(live.current_bid)) : (fmt(live.current_bid) + ' Coins')">
                        </div>
                    </div>

                    {{-- Kotak Current Bidder --}}
                    <div class="flex items-center justify-between rounded-3xl border border-zinc-800 bg-zinc-900/90 px-6 py-5 lg:px-8">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-[0.2em] text-zinc-400"
                                 x-text="live.is_sold ? 'PEMENANG LELANG' : 'HIGHEST BIDDER SAAT INI'"></div>
                            <div class="mt-1 text-2xl font-extrabold text-white lg:text-4xl"
                                 x-text="live.bidder_name ? live.bidder_name : 'Belum ada penawaran masuk...'"></div>
                        </div>
                        <template x-if="live.bidder_nim">
                            <div class="rounded-2xl border border-amber-400/40 bg-amber-400/15 px-4 py-2 font-mono text-2xl font-black text-amber-300 lg:text-3xl"
                                 x-text="'#' + live.bidder_nim">
                            </div>
                        </template>
                    </div>
                </div>
            </main>
        </template>

        <footer class="border-t border-zinc-900 pt-3 text-center text-xs tracking-widest text-zinc-600 uppercase">
            HIMTI D4 Teknik Informatika • Universitas Airlangga
        </footer>
    </div>
</body>
</html>
