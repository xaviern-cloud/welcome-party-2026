@extends('layouts.app')

@section('title', 'Dompet Saya — Welcome Party 2026')

@push('head')
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
@endpush

@section('content')
<div
    x-data="participantWallet({
        balance: {{ (int) $user->balance }},
        frozen: {{ $frozen ? 'true' : 'false' }},
        transactions: @js($transactions),
        stateUrl: '{{ $stateUrl }}',
        transferUrl: '{{ $transferUrl }}'
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
            <button
                type="button"
                @click="showQr = !showQr"
                class="flex items-center justify-center gap-2 rounded-xl border border-zinc-700 bg-zinc-900 py-3 text-sm font-semibold text-white transition hover:border-gold-500/50"
            >
                <svg class="h-4 w-4 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                <span x-text="showQr ? 'Tutup QR' : 'QR Terima'">QR Terima</span>
            </button>
        </div>
    </div>

    {{-- Kartu QR Code Terima Saldo --}}
    <div x-show="showQr" x-transition class="rounded-2xl border border-zinc-800 bg-zinc-900/90 p-5 text-center">
        <div class="text-sm font-bold text-white">QR Code Penerima ({{ $user->nim_code }})</div>
        <p class="mt-1 text-xs text-zinc-400">Minta teman memindai QR ini untuk mengirim saldo langsung ke akunmu.</p>
        <div class="mx-auto my-4 inline-block rounded-2xl bg-white p-3.5 shadow-inner">
            <div id="my-qr-code"></div>
        </div>
        <div class="font-mono text-xs text-gold-400">Kode NIM: {{ $user->nim_code }}</div>
    </div>

    {{-- Rincian Modal Awal (Accordion) --}}
    <div x-data="{ open: false }" class="rounded-2xl border border-zinc-800 bg-zinc-900/70">
        <button type="button" @click="open = !open" class="flex w-full items-center justify-between px-5 py-4 text-left">
            <div>
                <div class="text-sm font-semibold text-zinc-200">Rincian Modal Awal Check-In</div>
                <div class="text-xs text-zinc-400">Total terkunci: <span class="font-semibold text-gold-400">{{ number_format($breakdown['total'], 0, ',', '.') }}</span></div>
            </div>
            <svg class="h-5 w-5 text-zinc-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="open" x-cloak class="space-y-2 border-t border-zinc-800 px-5 py-4 text-xs">
            <div class="flex justify-between text-zinc-300">
                <span>Mata Uang Dasar (Poin {{ $user->poin_inisialisasi }})</span>
                <span class="font-mono font-semibold">{{ number_format($breakdown['dasar'], 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-zinc-300">
                <span>Bonus Kelompok</span>
                <span class="font-mono font-semibold text-emerald-400">+{{ number_format($breakdown['kelompok'], 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-zinc-300">
                <span>Bonus Kecepatan Hadir (#{{ $user->arrival_order }})</span>
                <span class="font-mono font-semibold text-emerald-400">+{{ number_format($breakdown['kecepatan'], 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-zinc-300">
                <span>Bonus Jabatan ({{ $user->jabatan ?? 'ANGGOTA' }})</span>
                <span class="font-mono font-semibold text-emerald-400">+{{ number_format($breakdown['ketua'], 0, ',', '.') }}</span>
            </div>
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
                            :class="tx.is_incoming ? 'text-emerald-400' : 'text-red-400'"
                            x-text="(tx.is_incoming ? '+' : '-') + formatNum(tx.amount)"
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
        showQr: true,
        init() {
            new QRCode(document.getElementById('my-qr-code'), {
                text: cfg.transferUrl,
                width: 180,
                height: 180,
                colorDark: '#09090b',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });

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
