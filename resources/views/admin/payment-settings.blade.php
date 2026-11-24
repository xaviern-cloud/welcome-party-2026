@extends('layouts.app')

@section('title', 'Kustomisasi Pembayaran Lelang — Welcome Party 2026')

@section('content')
<div
    x-data="{
        accounts: @js(count($config['accounts']) > 0 ? $config['accounts'] : [['provider' => '', 'number' => '', 'holder' => '']]),
        qrisUrl: @js($config['qris_image_url']),
        addAccount() {
            this.accounts.push({ provider: '', number: '', holder: '' });
        },
        removeAccount(idx) {
            this.accounts.splice(idx, 1);
            if (this.accounts.length === 0) this.addAccount();
        }
    }"
    class="mx-auto max-w-4xl space-y-6"
>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">Kustomisasi Halaman Pembayaran Pemenang</h1>
            <p class="text-xs text-zinc-400">Informasi rekening & QRIS ini otomatis muncul <strong>khusus di HP pemenang</strong> saat memenangkan lelang Uang Asli (Rp).</p>
        </div>
        <a href="{{ route('admin.auction') }}" class="rounded-xl border border-zinc-700 bg-zinc-900 px-4 py-2 text-xs font-semibold text-zinc-200 hover:border-gold-500/50">
            ← Kembali ke Lelang
        </a>
    </div>

    <form method="POST" action="{{ route('admin.payment.settings.save') }}" class="space-y-6">
        @csrf

        {{-- Pengaturan QRIS --}}
        <div class="grid gap-6 rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6 md:grid-cols-3">
            <div class="space-y-4 md:col-span-2">
                <h2 class="text-base font-bold text-emerald-400">1. QRIS / Kode QR Pembayaran</h2>
                <div>
                    <label class="mb-1 block text-xs text-zinc-400">Judul / Nama Merchant QRIS</label>
                    <input type="text" name="qris_label" value="{{ $config['qris_label'] }}" placeholder="Contoh: QRIS Panitia Welcome Party 2026" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3.5 py-2.5 text-sm text-white">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-zinc-400">URL Gambar QRIS (Direct Link .jpg / .png)</label>
                    <input type="text" name="qris_image_url" x-model="qrisUrl" placeholder="https://i.ibb.co/..." class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3.5 py-2.5 text-sm text-white">
                    <p class="mt-1 text-[11px] text-zinc-500">Unggah foto QRIS ke ImgBB lalu tempel Direct Link berakhiran .jpg / .png.</p>
                </div>
            </div>

            <div class="flex flex-col items-center justify-center rounded-xl border border-zinc-800 bg-zinc-950 p-4 text-center">
                <template x-if="qrisUrl">
                    <img :src="qrisUrl" alt="Preview QRIS" class="max-h-44 rounded-lg object-contain">
                </template>
                <template x-if="!qrisUrl">
                    <div class="py-10 text-xs text-zinc-600">Preview QRIS akan tampil di sini</div>
                </template>
            </div>
        </div>

        {{-- Daftar Rekening Bank & E-Wallet --}}
        <div class="space-y-4 rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gold-400">2. Rekening Bank & E-Wallet</h2>
                    <p class="text-xs text-zinc-400">Tambahkan satu atau beberapa rekening tujuan transfer (BCA, Mandiri, SeaBank, Dana, GoPay, dll).</p>
                </div>
                <button type="button" @click="addAccount()" class="rounded-xl bg-gold-500 px-3.5 py-2 text-xs font-bold text-zinc-950 hover:bg-gold-400">
                    + Tambah Rekening
                </button>
            </div>

            <div class="space-y-3">
                <template x-for="(acc, idx) in accounts" :key="idx">
                    <div class="grid gap-3 rounded-xl border border-zinc-800 bg-zinc-950/80 p-3.5 sm:grid-cols-12">
                        <div class="sm:col-span-3">
                            <label class="mb-1 block text-[11px] text-zinc-400">Bank / E-Wallet</label>
                            <input type="text" :name="`accounts[${idx}][provider]`" x-model="acc.provider" placeholder="BCA / SeaBank / DANA" class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-white">
                        </div>
                        <div class="sm:col-span-4">
                            <label class="mb-1 block text-[11px] text-zinc-400">Nomor Rekening / HP</label>
                            <input type="text" :name="`accounts[${idx}][number]`" x-model="acc.number" placeholder="08123456789" class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 font-mono text-xs text-white">
                        </div>
                        <div class="sm:col-span-4">
                            <label class="mb-1 block text-[11px] text-zinc-400">Atas Nama</label>
                            <input type="text" :name="`accounts[${idx}][holder]`" x-model="acc.holder" placeholder="Nama Bendahara" class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-white">
                        </div>
                        <div class="flex items-end justify-end sm:col-span-1">
                            <button type="button" @click="removeAccount(idx)" class="rounded-lg border border-red-500/30 bg-red-500/10 px-2.5 py-2 text-xs text-red-400 hover:bg-red-500/20">
                                Hapus
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Kontak WA & Catatan --}}
        <div class="space-y-4 rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6">
            <h2 class="text-base font-bold text-white">3. Kontak Konfirmasi & Instruksi</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs text-zinc-400">Nomor WhatsApp Bendahara (Format 628...)</label>
                    <input type="text" name="whatsapp" value="{{ $config['whatsapp'] }}" placeholder="6285729724629" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3.5 py-2.5 font-mono text-sm text-white">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-zinc-400">Instruksi Singkat untuk Pemenang</label>
                    <input type="text" name="instructions" value="{{ $config['instructions'] }}" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3.5 py-2.5 text-sm text-white">
                </div>
            </div>
        </div>

        <button type="submit" class="w-full rounded-xl bg-emerald-500 py-3.5 text-sm font-bold text-zinc-950 hover:bg-emerald-400">
            Simpan Pengaturan Halaman Pembayaran
        </button>
    </form>
</div>
@endsection
