@extends('layouts.app')

@section('title', 'Voting Perform & Fashion Show — Welcome Party 2026')

@section('content')
<div
    x-data="{
        data: @js($payload),
        busyId: null,
        toast: '',
        err: '',
        async vote(categoryId, nomineeId) {
            this.busyId = nomineeId;
            this.err = '';
            try {
                const res = await window.apiPost('{{ $voteUrl }}', {
                    category_id: categoryId,
                    nominee_id: nomineeId
                });
                this.data = res.payload;
                this.toast = res.message;
                setTimeout(() => this.toast = '', 2500);
            } catch (e) {
                this.err = e.message;
            } finally {
                this.busyId = null;
            }
        },
        init() {
            setInterval(async () => {
                try {
                    const r = await fetch('{{ $stateUrl }}?t=' + Date.now(), {
                        cache: 'no-store',
                        headers: { 'Accept': 'application/json' }
                    });
                    if (r.ok) this.data = await r.json();
                } catch (e) {}
            }, 3500);
        }
    }"
    x-init="init()"
    class="mx-auto max-w-2xl space-y-6"
>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-white">Voting Perform & Fashion Show</h1>
            <p class="text-xs text-zinc-400">Pilih penampilan & outfit favoritmu di tiap kategori</p>
        </div>
        <a href="{{ route('participant.dashboard') }}" class="rounded-xl border border-zinc-700 bg-zinc-900 px-3.5 py-2 text-xs font-semibold text-zinc-200 hover:bg-zinc-800">
            ← Dompet
        </a>
    </div>

    <div x-show="toast" x-cloak class="rounded-xl border border-emerald-500/40 bg-emerald-950/70 px-4 py-2.5 text-xs font-semibold text-emerald-200" x-text="toast"></div>
    <div x-show="err" x-cloak class="rounded-xl border border-red-500/40 bg-red-950/70 px-4 py-2.5 text-xs text-red-200" x-text="err"></div>

    {{-- Banner Status Sesi Voting --}}
    <div class="rounded-2xl border p-4 text-xs"
         :class="data.voting_open ? 'border-emerald-500/40 bg-emerald-950/25 text-emerald-200' : 'border-amber-500/40 bg-amber-950/25 text-amber-200'">
        <template x-if="data.voting_open">
            <div>🟢 <strong>Sesi Voting Sedang Dibuka!</strong> Silakan pilih 1 favorit pada masing-masing kategori di bawah ini.</div>
        </template>
        <template x-if="!data.voting_open">
            <div>⏸ <strong>Sesi Voting Belum Dibuka / Sudah Ditutup.</strong> Tunggu aba-aba dari MC & panitia.</div>
        </template>
    </div>

    {{-- Daftar Kategori --}}
    <template x-for="cat in data.categories" :key="cat.id">
        <div class="space-y-3 rounded-2xl border border-zinc-800 bg-zinc-900/85 p-5 shadow-xl">
            <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                <div>
                    <h2 class="text-base font-extrabold text-gold-400" x-text="cat.name"></h2>
                    <div class="mt-0.5 text-[11px] text-zinc-400">
                        <span x-show="cat.my_vote" class="font-semibold text-emerald-400">✓ Kamu sudah memilih pada kategori ini</span>
                        <span x-show="!cat.my_vote">Belum memilih</span>
                    </div>
                </div>
                <template x-if="data.results_revealed && cat.total_votes !== null">
                    <span class="rounded-full border border-gold-500/40 bg-gold-500/15 px-3 py-1 font-mono text-[11px] font-bold text-gold-300"
                          x-text="'Total: ' + cat.total_votes + ' suara'"></span>
                </template>
            </div>

            <template x-if="cat.nominees.length === 0">
                <div class="py-8 text-center text-xs text-zinc-500">Belum ada kandidat pada kategori ini.</div>
            </template>

            <div class="space-y-3">
                <template x-for="(n, idx) in cat.nominees" :key="n.id">
                    <div class="rounded-2xl border p-4 transition"
                         :class="cat.my_vote === n.id ? 'border-emerald-500/70 bg-emerald-950/25' : 'border-zinc-800 bg-zinc-950/90'">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <template x-if="n.photo_url">
                                    <img :src="n.photo_url" :alt="n.name" class="h-12 w-12 shrink-0 rounded-xl border border-zinc-700 object-cover">
                                </template>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span x-show="data.results_revealed && idx === 0 && n.votes > 0" class="rounded bg-gold-500 px-1.5 py-0.5 text-[10px] font-black text-zinc-950">🏆 #1</span>
                                        <span class="truncate text-sm font-bold text-white" x-text="n.name"></span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                        <span x-show="n.angkatan" class="rounded bg-gold-500/20 px-2 py-0.5 font-mono text-[10px] font-bold text-gold-400" x-text="'Angkatan ' + n.angkatan"></span>
                                        <span x-show="n.performer_info" class="rounded bg-zinc-800 px-2 py-0.5 text-[10px] text-zinc-300" x-text="n.performer_info"></span>
                                    </div>
                                    <p x-show="n.description" class="mt-1 text-xs text-zinc-400" x-text="n.description"></p>
                                </div>
                            </div>

                            <div class="shrink-0">
                                <button
                                    type="button"
                                    x-show="data.voting_open"
                                    :disabled="busyId === n.id"
                                    @click="vote(cat.id, n.id)"
                                    class="rounded-xl px-4 py-2 text-xs font-extrabold transition"
                                    :class="cat.my_vote === n.id
                                        ? 'bg-emerald-500 text-zinc-950'
                                        : 'border border-gold-500/50 bg-gold-500/15 text-gold-300 hover:bg-gold-500 hover:text-zinc-950'"
                                    x-text="cat.my_vote === n.id ? 'Dipilih ✓' : 'Vote'"
                                ></button>

                                <span x-show="!data.voting_open && cat.my_vote === n.id"
                                      class="rounded-xl bg-emerald-500/20 px-3 py-1.5 text-[11px] font-bold text-emerald-300">
                                    Pilihanmu ✓
                                </span>
                            </div>
                        </div>

                        {{-- HASIL HANYA MUNCUL JIKA SUDAH DIBUKA ADMIN --}}
                        <template x-if="data.results_revealed">
                            <div class="mt-3 border-t border-zinc-800/80 pt-2.5">
                                <div class="mb-1 flex items-center justify-between font-mono text-xs">
                                    <span class="font-bold text-emerald-400" x-text="(n.votes ?? 0) + ' suara'"></span>
                                    <span class="text-zinc-400" x-text="(n.percentage ?? 0) + '%'"></span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-800">
                                    <div class="h-full rounded-full bg-gradient-to-r from-gold-500 to-emerald-400 transition-all duration-500"
                                         :style="`width: ${n.percentage ?? 0}%`"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
@endsection