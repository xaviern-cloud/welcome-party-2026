@extends('layouts.app')

@section('title', 'Import Peserta — Welcome Party 2026')

@section('content')
<div class="mx-auto max-w-xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-white">Import Data Peserta</h1>
            <p class="text-xs text-zinc-400">Mendukung format Tabel Datar maupun Blok per Kelompok (.xlsx, .xls, .csv).</p>
        </div>
        <a href="{{ route('admin.pre-event.index') }}" class="rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-xs text-zinc-300">← Kembali</a>
    </div>

    <div class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6">
        <form method="POST" action="{{ route('admin.import.upload') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-zinc-300">Pilih File Excel / CSV (Maks. 5 MB)</label>
                <input type="file" name="file" accept=".csv,.xlsx,.xls,.txt" required class="w-full rounded-xl border border-zinc-700 bg-zinc-950 p-3 text-xs text-zinc-300 file:mr-3 file:rounded-lg file:border-0 file:bg-gold-500 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-zinc-950">
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-zinc-300">Nama Sheet Excel (Opsional)</label>
                <input type="text" name="sheet" placeholder="Kosongkan untuk membaca sheet pertama" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3.5 py-2.5 text-xs text-white">
            </div>

            <button type="submit" class="w-full rounded-xl bg-gold-500 py-3 text-xs font-bold text-zinc-950 hover:bg-gold-400">
                Upload & Lihat Pratinjau (Dry-Run)
            </button>
        </form>

        <div class="mt-6 border-t border-zinc-800 pt-4 text-xs text-zinc-400">
            Unduh contoh template:
            <a href="{{ route('admin.import.template', ['format' => 'xlsx']) }}" class="ml-2 font-semibold text-gold-400 underline">Template .XLSX</a>
            <a href="{{ route('admin.import.template', ['format' => 'csv']) }}" class="ml-2 font-semibold text-gold-400 underline">Template .CSV</a>
        </div>
    </div>
</div>
@endsection
