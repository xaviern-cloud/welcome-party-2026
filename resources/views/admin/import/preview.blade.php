@extends('layouts.app')

@section('title', 'Pratinjau Import — Welcome Party 2026')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-white">Pratinjau Hasil Parsing: {{ $meta['file_name'] }}</h1>
            <p class="text-xs text-zinc-400">Format terdeteksi: <strong class="text-gold-400">{{ $meta['format_label'] }}</strong> • Total {{ $meta['row_count'] }} baris</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.import.form') }}" class="rounded-xl border border-zinc-700 bg-zinc-900 px-4 py-2 text-xs text-zinc-300">Batal</a>
            <form method="POST" action="{{ route('admin.import.commit') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <button type="submit" class="rounded-xl bg-emerald-500 px-5 py-2 text-xs font-bold text-zinc-950 hover:bg-emerald-400">
                    Konfirmasi & Simpan ke Database
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4">
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
            <div class="text-xs text-zinc-400">Akan Ditambahkan</div>
            <div class="mt-1 font-mono text-xl font-bold text-emerald-400">{{ $dry['created'] ?? 0 }}</div>
        </div>
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
            <div class="text-xs text-zinc-400">Akan Diperbarui</div>
            <div class="mt-1 font-mono text-xl font-bold text-gold-400">{{ $dry['updated'] ?? 0 }}</div>
        </div>
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
            <div class="text-xs text-zinc-400">Dilewati / Error</div>
            <div class="mt-1 font-mono text-xl font-bold text-red-400">{{ $dry['skipped'] ?? 0 }}</div>
        </div>
    </div>

    @if(!empty($dry['errors']))
        <div class="rounded-xl border border-red-500/40 bg-red-950/50 p-4 text-xs text-red-200">
            <div class="mb-1 font-bold">Catatan Error Baris:</div>
            <ul class="list-disc pl-5 space-y-1">
                @foreach(array_slice($dry['errors'], 0, 15) as $err)
                    <li>{{ is_array($err) ? json_encode($err) : $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="max-h-96 overflow-auto rounded-2xl border border-zinc-800 bg-zinc-900/80">
        <table class="w-full text-left text-xs">
            <thead class="sticky top-0 bg-zinc-950 text-zinc-400">
                <tr>
                    <th class="px-4 py-2.5">NIM</th>
                    <th class="px-4 py-2.5">Nama</th>
                    <th class="px-4 py-2.5">Kelompok</th>
                    <th class="px-4 py-2.5">Jabatan</th>
                    <th class="px-4 py-2.5 text-right">Poin</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @foreach($rows as $r)
                <tr>
                    <td class="px-4 py-2 font-mono text-gold-400">{{ $r['nim_code'] ?? '-' }}</td>
                    <td class="px-4 py-2 text-white">{{ $r['name'] ?? '-' }}</td>
                    <td class="px-4 py-2 text-zinc-400">{{ $r['group_name'] ?? '-' }}</td>
                    <td class="px-4 py-2 text-zinc-300">{{ $r['jabatan'] ?? 'BUKAN' }}</td>
                    <td class="px-4 py-2 text-right font-mono">{{ $r['poin_inisialisasi'] ?? 0 }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
