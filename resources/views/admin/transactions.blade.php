@extends('layouts.app')

@section('title', 'Audit Transaksi — Welcome Party 2026')

@section('content')
<div class="space-y-5">
    <div>
        <h1 class="text-2xl font-bold text-white">Log Audit Seluruh Transaksi</h1>
        <p class="text-xs text-zinc-400">Rekam jejak permanen modal awal, transfer P2P, dan potongan lelang.</p>
    </div>

    <form method="GET" action="{{ route('admin.transactions') }}" class="flex flex-wrap gap-3">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Filter 3-digit NIM..." class="rounded-xl border border-zinc-700 bg-zinc-950 px-3.5 py-2 font-mono text-xs text-white">
        <select name="type" class="rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-white">
            <option value="">Semua Jenis</option>
            @foreach($types as $key => $label)
                <option value="{{ $key }}" {{ $filters['type'] === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-xl bg-gold-500 px-4 py-2 text-xs font-bold text-zinc-950">Cari</button>
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
