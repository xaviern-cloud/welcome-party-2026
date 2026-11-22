@extends('layouts.app')

@section('title', 'Katalog Lelang — Welcome Party 2026')

@section('content')
<div
    x-data="{
        items: @js($items),
        init() {
            setInterval(async () => {
                try {
                    const res = await fetch('{{ $stateUrl }}', { headers: { 'Accept': 'application/json' } });
                    if (res.ok) {
                        const data = await res.json();
                        if (Array.isArray(data.catalog)) this.items = data.catalog;
                    }
                } catch (e) {}
            }, 4000);
        }
    }"
    x-init="init()"
    class="mx-auto max-w-2xl space-y-5"
>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-white">Katalog Item Lelang</h1>
            <p class="text-xs text-zinc-400">Daftar item eksklusif yang telah dibuka oleh panitia.</p>
        </div>
        <a href="{{ route('participant.dashboard') }}" class="rounded-xl border border-zinc-700 bg-zinc-900 px-3.5 py-2 text-xs font-semibold text-zinc-200 hover:border-gold-500/50">
            ← Dompet
        </a>
    </div>

    <template x-if="items.length === 0">
        <div class="rounded-2xl border border-zinc-800 bg-zinc-900/70 px-6 py-14 text-center">
            <div class="text-sm font-bold text-zinc-300">Katalog Masih Tertutup</div>
            <p class="mt-1 text-xs text-zinc-500">Item lelang akan muncul secara otomatis di sini begitu dibuka oleh panitia.</p>
        </div>
    </template>

    <div class="grid gap-4 sm:grid-cols-2">
        <template x-for="item in items" :key="item.id">
            <div class="overflow-hidden rounded-2xl border border-gold-500/30 bg-zinc-900/90 shadow-lg">
                <template x-if="item.image_url">
                    <img :src="item.image_url" :alt="item.name" class="h-44 w-full object-cover bg-zinc-950">
                </template>
                <div class="p-4">
                    <div class="text-base font-bold text-white" x-text="item.name"></div>
                    <p x-show="item.description" class="mt-1 text-xs text-zinc-400" x-text="item.description"></p>
                    <div class="mt-4 flex items-center justify-between border-t border-zinc-800 pt-3">
                        <span class="text-[11px] uppercase tracking-wider text-zinc-500">Open Bid</span>
                        <span class="font-mono text-sm font-extrabold text-gold-400" x-text="new Intl.NumberFormat('id-ID').format(item.starting_price || 0) + ' Coins'"></span>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
@endsection
