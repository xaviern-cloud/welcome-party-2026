@extends('layouts.app')

@section('title', 'Voting Perform & Fashion Show — Welcome Party 2026')

@section('content')
<div
    x-data="{
        overview: @js($overview),
        busyCtrl: false,
        toast: '',
        async toggleControl(payload) {
            this.busyCtrl = true;
            try {
                const res = await window.apiPost('{{ route('admin.showcase-voting.control') }}', payload);
                this.overview = res.overview;
                this.toast = res.message;
                setTimeout(() => this.toast = '', 3000);
            } catch (e) {
                alert(e.message);
            } finally {
                this.busyCtrl = false;
            }
        },
        init() {
            setInterval(async () => {
                try {
                    const r = await fetch('{{ route('admin.showcase-voting.status') }}', { headers: { 'Accept': 'application/json' } });
                    if (r.ok) this.overview = await r.json();
                } catch (e) {}
            }, 3000);
        }
    }"
    x-init="init()"
    class="space-y-8"
>
    {{-- KONTROL UTAMA: BUKA/TUTUP VOTING & BUKA/SEMBUNYIKAN HASIL --}}
    <div class="rounded-2xl border border-gold-500/40 bg-zinc-900/90 p-6 shadow-xl">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-zinc-800 pb-4">
            <div>
                <h1 class="text-xl font-bold text-gold-400">Manajemen Voting Perform & Fashion Show</h1>
                <p class="mt-0.5 text-xs text-zinc-400">Input peserta nominasi, atur sesi voting, dan tentukan kapan hasil suara dibuka ke peserta.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.showcase-voting.export') }}" class="rounded-xl border border-emerald-500/40 bg-emerald-500/15 px-3.5 py-2 text-xs font-bold text-emerald-300 hover:bg-emerald-500/25">
                    Export Hasil XLS
                </a>
                <form method="POST" action="{{ route('admin.showcase-voting.reset') }}" onsubmit="return confirm('Reset seluruh suara voting menjadi 0? Daftar peserta nominasi tetap tersimpan.')">
                    @csrf
                    <button type="submit" class="rounded-xl border border-red-500/40 bg-red-500/15 px-3.5 py-2 text-xs font-bold text-red-300 hover:bg-red-500/25">
                        Reset Suara
                    </button>
                </form>
            </div>
        </div>

        <div x-show="toast" x-cloak class="mt-4 rounded-xl border border-emerald-500/40 bg-emerald-950/60 px-4 py-2 text-xs text-emerald-200" x-text="toast"></div>

        <div class="mt-5 grid gap-4 md:grid-cols-2">
            {{-- Saklar 1: Status Voting Peserta --}}
            <div class="flex items-center justify-between rounded-2xl border p-4"
                 :class="overview.voting_open ? 'border-emerald-500/50 bg-emerald-950/25' : 'border-zinc-800 bg-zinc-950'">
                <div class="pr-3">
                    <div class="flex items-center gap-2">
                        <span class="rounded px-2 py-0.5 text-[10px] font-extrabold uppercase"
                              :class="overview.voting_open ? 'bg-emerald-500/20 text-emerald-300' : 'bg-zinc-800 text-zinc-400'"
                              x-text="overview.voting_open ? 'VOTING DIBUKA' : 'VOTING DITUTUP'"></span>
                        <span class="text-sm font-bold text-white">1. Akses Voting Peserta</span>
                    </div>
                    <p class="mt-1 text-xs text-zinc-400">Izinkan peserta memilih kandidat Perform & Fashion Show dari HP masing-masing.</p>
                </div>
                <button type="button"
                        :disabled="busyCtrl"
                        @click="toggleControl({ voting_open: !overview.voting_open })"
                        class="shrink-0 rounded-xl px-4 py-2.5 text-xs font-extrabold transition"
                        :class="overview.voting_open ? 'bg-red-500 text-white hover:bg-red-400' : 'bg-emerald-500 text-zinc-950 hover:bg-emerald-400'"
                        x-text="overview.voting_open ? 'Tutup Voting' : 'Buka Voting'">
                </button>
            </div>

            {{-- Saklar 2: Visibilitas Hasil Voting --}}
            <div class="flex items-center justify-between rounded-2xl border p-4"
                 :class="overview.results_revealed ? 'border-gold-500/50 bg-amber-950/25' : 'border-zinc-800 bg-zinc-950'">
                <div class="pr-3">
                    <div class="flex items-center gap-2">
                        <span class="rounded px-2 py-0.5 text-[10px] font-extrabold uppercase"
                              :class="overview.results_revealed ? 'bg-gold-500/20 text-gold-300' : 'bg-zinc-800 text-zinc-400'"
                              x-text="overview.results_revealed ? 'HASIL TERBUKA' : 'HASIL DISEMBUNYIKAN'"></span>
                        <span class="text-sm font-bold text-white">2. Tampilkan Hasil ke Peserta</span>
                    </div>
                    <p class="mt-1 text-xs text-zinc-400">Selama disembunyikan, peserta hanya bisa vote tanpa melihat jumlah suara maupun peringkat.</p>
                </div>
                <button type="button"
                        :disabled="busyCtrl"
                        @click="toggleControl({ results_revealed: !overview.results_revealed })"
                        class="shrink-0 rounded-xl px-4 py-2.5 text-xs font-extrabold transition"
                        :class="overview.results_revealed ? 'border border-zinc-700 bg-zinc-900 text-zinc-200 hover:bg-zinc-800' : 'bg-gold-500 text-zinc-950 hover:bg-gold-400'"
                        x-text="overview.results_revealed ? 'Sembunyikan Hasil' : 'Buka Hasil'">
                </button>
            </div>
        </div>
    </div>

    {{-- FORM TAMBAH KANDIDAT & TAMBAH KATEGORI --}}
    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Input Peserta Perform / Fashion Show --}}
        <form method="POST" action="{{ route('admin.showcase-voting.nominees.store') }}" class="space-y-4 rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6 lg:col-span-8">
            @csrf
            <div class="text-base font-bold text-white">+ Input Peserta Perform / Fashion Show</div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs text-zinc-400">Kategori *</label>
                    <select name="category_id" required class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-xs font-semibold text-gold-400">
                        <template x-for="cat in overview.categories" :key="cat.id">
                            <option :value="cat.id" x-text="cat.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-zinc-400">Nama Penampil / Peserta *</label>
                    <input type="text" name="name" required placeholder="Contoh: Band Semester 3 / Raka & Dinda" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-xs text-white">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-zinc-400">Angkatan *</label>
                    <input type="text" name="angkatan" list="angkatan-list" required placeholder="Contoh: 2025" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2.5 font-mono text-xs text-white">
                    <datalist id="angkatan-list">
                        <option value="2024">
                        <option value="2025">
                        <option value="2026">
                        <option value="Kolaborasi">
                    </datalist>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs text-zinc-400">Jenis Penampilan / Tema Outfit</label>
                    <input type="text" name="performer_info" placeholder="Contoh: Acoustic / Formal Noir" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-white">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-zinc-400">Keterangan Singkat</label>
                    <input type="text" name="description" placeholder="Judul lagu / konsep busana" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-white">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-zinc-400">URL Foto (Opsional)</label>
                    <input type="text" name="photo_url" placeholder="https://..." class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-white">
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="rounded-xl bg-emerald-500 px-5 py-2.5 text-xs font-extrabold text-zinc-950 hover:bg-emerald-400">
                    Simpan ke Daftar Voting
                </button>
            </div>
        </form>

        {{-- Tambah Kategori Baru --}}
        <form method="POST" action="{{ route('admin.showcase-voting.categories.store') }}" class="flex flex-col justify-between space-y-4 rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6 lg:col-span-4">
            <div class="space-y-3">
                <div class="text-base font-bold text-white">+ Tambah Kategori Voting</div>
                <p class="text-xs text-zinc-400">Default sudah tersedia <strong>Perform / Talent Show</strong> dan <strong>Fashion Show</strong>. Tambahkan kategori baru jika diperlukan.</p>
                <div>
                    <label class="mb-1 block text-xs text-zinc-400">Nama Kategori Baru</label>
                    <input type="text" name="name" required placeholder="Contoh: Best Couple / Best Costume" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-xs text-white">
                </div>
            </div>
            <button type="submit" class="w-full rounded-xl border border-gold-500/50 bg-gold-500/15 py-2.5 text-xs font-bold text-gold-400 hover:bg-gold-500/25">
                Tambah Kategori
            </button>
        </form>
    </div>

    {{-- LIVE HASIL VOTING & DAFTAR KANDIDAT PER KATEGORI --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <template x-for="cat in overview.categories" :key="cat.id">
            <div class="rounded-2xl border border-zinc-800 bg-zinc-900/85 p-6">
                <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                    <div>
                        <h2 class="text-lg font-bold text-gold-400" x-text="cat.name"></h2>
                        <div class="text-xs text-zinc-400">Total suara masuk: <strong class="text-white" x-text="cat.total_votes + ' suara'"></strong></div>
                    </div>
                    <span class="rounded-full bg-zinc-800 px-3 py-1 font-mono text-xs text-zinc-300" x-text="cat.nominees.length + ' kandidat'"></span>
                </div>

                <template x-if="cat.nominees.length === 0">
                    <div class="py-10 text-center text-xs text-zinc-500">Belum ada peserta pada kategori ini.</div>
                </template>

                <div class="mt-4 space-y-3">
                    <template x-for="(n, idx) in cat.nominees" :key="n.id">
                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/90 p-3.5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg font-mono text-xs font-extrabold"
                                         :class="idx === 0 && n.votes > 0 ? 'bg-gold-500 text-zinc-950' : 'bg-zinc-800 text-zinc-300'"
                                         x-text="'#' + (idx + 1)"></div>
                                    <div>
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="text-sm font-bold text-white" x-text="n.name"></span>
                                            <span x-show="n.angkatan" class="rounded bg-gold-500/20 px-1.5 py-0.5 font-mono text-[10px] font-bold text-gold-400" x-text="'Angkatan ' + n.angkatan"></span>
                                            <span x-show="n.performer_info" class="rounded bg-zinc-800 px-1.5 py-0.5 text-[10px] text-zinc-300" x-text="n.performer_info"></span>
                                        </div>
                                        <p x-show="n.description" class="mt-0.5 text-xs text-zinc-400" x-text="n.description"></p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 text-right">
                                    <div>
                                        <div class="font-mono text-sm font-extrabold text-emerald-400" x-text="n.votes + ' suara'"></div>
                                        <div class="font-mono text-[11px] text-zinc-400" x-text="n.percentage + '%'"></div>
                                    </div>
                                    <form method="POST" :action="`{{ url('/admin/showcase-voting/nominees') }}/${n.id}`" @submit="if(!confirm(`Hapus ${n.name} dari daftar voting?`)) $event.preventDefault()">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-red-400 hover:underline">Hapus</button>
                                    </form>
                                </div>
                            </div>

                            {{-- Progress Bar --}}
                            <div class="mt-2.5 h-2 w-full overflow-hidden rounded-full bg-zinc-800">
                                <div class="h-full rounded-full bg-gradient-to-r from-gold-500 to-emerald-400 transition-all duration-500"
                                     :style="`width: ${n.percentage}%`"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
</div>
@endsection