@extends('layouts.app')

@section('title', 'Audit Transaksi — Welcome Party 2026')

@section('content')
<div x-data="{ exported: false }" class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">Log Audit Seluruh Transaksi</h1>
            <p class="text-xs text-zinc-400">Filter berdasarkan rentang waktu, ekspor arsip XLS terlebih dahulu, lalu reset data jika diperlukan.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('admin.transactions.export', $filters) }}"
                @click="exported = true"
                class="rounded-xl border border-emerald-500/40 bg-emerald-500/15 px-4 py-2 text-xs font-bold text-emerald-300 hover:bg-emerald-500/25"
            >
                1. Export XLS Dulu (.XLSX)
            </a>

            <form
                method="POST"
                action="{{ route('admin.transactions.reset') }}"
                onsubmit="return confirm('Hapus permanen seluruh log transaksi yang sesuai filter waktu/jenis saat ini?')"
            >
                @csrf
                <input type="hidden" name="q" value="{{ $filters['q'] }}">
                <input type="hidden" name="type" value="{{ $filters['type'] }}">
                <input type="hidden" name="from" value="{{ $filters['from'] }}">
                <input type="hidden" name="to" value="{{ $filters['to'] }}">
                <button
                    type="submit"
                    :disabled="!exported"
                    :title="exported ? 'Reset data transaksi terfilter' : 'Klik Export XLS Dulu sebelum mereset data'"
                    class="rounded-xl border border-red-500/40 bg-red-500/15 px-4 py-2 text-xs font-bold text-red-300 transition hover:bg-red-500/25 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    2. Reset Data Terfilter
                </button>
            </form>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.transactions') }}" class="grid gap-3 rounded-2xl border border-zinc-800 bg-zinc-900/70 p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <label class="mb-1 block text-[11px] text-zinc-400">3-Digit NIM</label>
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Mis. 092" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-xs text-white">
        </div>
        <div>
            <label class="mb-1 block text-[11px] text-zinc-400">Jenis Transaksi</label>
            <select name="type" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-white">
                <option value="">Semua Jenis</option>
                @foreach($types as $key => $label)
                    <option value="{{ $key }}" {{ $filters['type'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-[11px] text-zinc-400">Dari Waktu (WIB)</label>
            <input type="datetime-local" name="from" value="{{ $filters['from'] }}" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-xs text-white">
        </div>
        <div>
            <label class="mb-1 block text-[11px] text-zinc-400">Sampai Waktu (WIB)</label>
            <input type="datetime-local" name="to" value="{{ $filters['to'] }}" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-xs text-white">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 rounded-xl bg-gold-500 py-2 text-xs font-bold text-zinc-950 hover:bg-gold-400">Terapkan Filter</button>
            <a href="{{ route('admin.transactions') }}" class="rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-300 hover:text-white">Reset Filter</a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-2xl border border-zinc-800 bg-zinc-900/80">
        <table class="w-full text-left text-xs">
            <thead class="bg-zinc-950 text-zinc-400">
                <tr>
                    <th class="px-4 py-3">Waktu</th>
                    <th class="px-4 py-3">Jenis</th>
                    <th class="px-4 py-3">Keterangan</th>
                    <th class="px-4 py-3 text-right">Nominal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @forelse($transactions as $tx)
                <tr>
                    <td class="px-4 py-3 font-mono text-zinc-400">{{ $tx['time'] ?? '-' }}</td>
                    <td class="px-4 py-3 font-semibold text-gold-400">{{ $tx['label'] ?? $tx['type'] }}</td>
                    <td class="px-4 py-3 text-white">{{ $tx['title'] ?? '-' }} <span class="text-zinc-400">{{ $tx['subtitle'] ?? '' }}</span></td>
                    <td class="px-4 py-3 text-right font-mono font-bold text-zinc-100">{{ number_format($tx['amount'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-zinc-500">Tidak ada transaksi yang cocok.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $transactions->links() }}</div>
</div>
@endsection
