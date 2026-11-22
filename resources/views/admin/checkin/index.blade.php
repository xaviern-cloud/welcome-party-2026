@extends('layouts.app')

@section('title', 'Meja Check-In — Welcome Party 2026')

@section('content')
<div
    x-data="checkInDesk({
        searchUrl: '{{ route("admin.checkin.search") }}',
        registerUrl: '{{ route("admin.checkin.register") }}',
        recent: @js($recent),
        nextOrder: {{ (int) $nextOrder }},
        nextSpeedBonus: {{ (int) $nextSpeedBonus }},
        checkedInCount: {{ (int) $stats['checked_in'] }},
        totalCount: {{ (int) $stats['total'] }}
    })"
    class="space-y-6"
>
    {{-- Header Status Antrean --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-4">
            <div class="text-xs text-zinc-400">Sudah Check-In</div>
            <div class="mt-1 font-mono text-2xl font-bold text-emerald-400">
                <span x-text="checkedInCount"></span> / <span x-text="totalCount"></span>
            </div>
        </div>
        <div class="rounded-2xl border border-gold-500/30 bg-zinc-900/80 p-4">
            <div class="text-xs text-zinc-400">Urutan Hadir Berikutnya</div>
            <div class="mt-1 font-mono text-2xl font-bold text-gold-400">#<span x-text="nextOrder"></span></div>
        </div>
        <div class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-4">
            <div class="text-xs text-zinc-400">Bonus Kecepatan Berikutnya</div>
            <div class="mt-1 font-mono text-2xl font-bold text-white">+<span x-text="formatNum(nextSpeedBonus)"></span></div>
        </div>
    </div>

    <div x-show="banner" x-cloak class="rounded-xl border border-emerald-500/40 bg-emerald-950/60 px-4 py-3 text-sm font-medium text-emerald-200" x-text="banner"></div>
    <div x-show="errorMsg" x-cloak class="rounded-xl border border-red-500/40 bg-red-950/60 px-4 py-3 text-sm text-red-200" x-text="errorMsg"></div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Kolom Kiri & Tengah: Pencarian & Konfirmasi Hadir --}}
        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6">
                <h2 class="text-base font-bold text-white">Cari Peserta untuk Check-In</h2>
                <p class="mt-0.5 text-xs text-zinc-400">Ketik 3 digit NIM terakhir, NIM lengkap, atau nama peserta.</p>

                <input
                    type="text"
                    x-model="query"
                    @input.debounce.200ms="search()"
                    autofocus
                    placeholder="Contoh: 092 atau Farrel..."
                    class="mt-3 w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-base text-white placeholder-zinc-600 focus:border-gold-500 focus:outline-none"
                >

                <div class="mt-4 space-y-3">
                    <template x-for="p in results" :key="p.nim_code">
                        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-zinc-800 bg-zinc-950/90 p-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded bg-gold-500/20 px-2 py-0.5 font-mono text-xs font-bold text-gold-400" x-text="'#' + p.nim_code"></span>
                                    <span class="font-bold text-white" x-text="p.name"></span>
                                </div>
                                <div class="mt-1 text-xs text-zinc-400">
                                    Kelompok: <strong class="text-zinc-200" x-text="p.group_name || '-'"></strong>
                                    • Estimasi modal: <span class="font-mono text-gold-400" x-text="formatNum(p.estimated_balance)"></span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <template x-if="!p.is_checked_in">
                                    <div class="flex items-center gap-2">
                                        <select x-model="p.jabatan" class="rounded-lg border border-zinc-700 bg-zinc-900 px-2.5 py-2 text-xs text-white">
                                            <option value="BUKAN">Anggota</option>
                                            <option value="KETUA">Ketua (+{{ number_format($ketuaBonus, 0, ',', '.') }})</option>
                                        </select>
                                        <button
                                            type="button"
                                            @click="confirmCheckIn(p)"
                                            class="rounded-xl bg-emerald-500 px-4 py-2 text-xs font-bold text-zinc-950 hover:bg-emerald-400"
                                        >
                                            Konfirmasi Hadir
                                        </button>
                                    </div>
                                </template>
                                <template x-if="p.is_checked_in">
                                    <span class="rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-3 py-1.5 text-xs font-semibold text-emerald-400">
                                        ✓ Sudah Hadir (#<span x-text="p.arrival_order"></span>)
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Riwayat Check-In Terbaru --}}
            <div class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-5">
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-zinc-400">10 Peserta Terakhir Check-In</h3>
                <div class="divide-y divide-zinc-800/70">
                    <template x-for="r in recent" :key="r.nim_code">
                        <div class="flex items-center justify-between py-2.5 text-xs">
                            <div>
                                <span class="font-mono font-bold text-gold-400" x-text="'#' + r.arrival_order"></span>
                                <span class="ml-2 font-semibold text-white" x-text="r.name"></span>
                                <span class="text-zinc-500" x-text="'(' + r.nim_code + ')'"></span>
                            </div>
                            <span class="font-mono font-semibold text-emerald-400" x-text="formatNum(r.total_mata_uang_final)"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Kolom Kanan: Peserta Susulan (On-the-spot) --}}
        <div class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-6">
            <h2 class="text-base font-bold text-gold-400">Peserta Susulan (Walk-In)</h2>
            <p class="mt-1 text-xs text-zinc-400">Daftarkan peserta baru yang belum ada di daftar pra-acara sekaligus check-in.</p>

            <form @submit.prevent="registerWalkIn" class="mt-4 space-y-3 text-xs">
                <div>
                    <label class="mb-1 block text-zinc-400">3 Digit NIM (Opsional jika diisi NIM lengkap)</label>
                    <input type="text" x-model="walkIn.nim_code" maxlength="15" placeholder="Contoh: 145" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-white">
                </div>
                <div>
                    <label class="mb-1 block text-zinc-400">NIM Lengkap (Opsional)</label>
                    <input type="text" x-model="walkIn.full_nim" maxlength="20" placeholder="434251145" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-white">
                </div>
                <div>
                    <label class="mb-1 block text-zinc-400">Nama Lengkap *</label>
                    <input type="text" x-model="walkIn.name" required placeholder="Nama Peserta" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-white">
                </div>
                <div>
                    <label class="mb-1 block text-zinc-400">Kelompok</label>
                    <input type="text" x-model="walkIn.group_name" list="group-options" placeholder="Nama Kelompok" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-white">
                    <datalist id="group-options">
                        @foreach($groups as $g)
                            <option value="{{ $g }}">
                        @endforeach
                    </datalist>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="mb-1 block text-zinc-400">Gender</label>
                        <select x-model="walkIn.gender" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-white">
                            <option value="">-</option>
                            <option value="L">L</option>
                            <option value="P">P</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-zinc-400">Poin Awal</label>
                        <input type="number" x-model="walkIn.poin_inisialisasi" min="0" placeholder="0" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-white">
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-zinc-400">Jabatan</label>
                    <select x-model="walkIn.jabatan" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-white">
                        <option value="BUKAN">Anggota</option>
                        <option value="KETUA">Ketua Kelompok</option>
                    </select>
                </div>

                <button type="submit" class="mt-2 w-full rounded-xl bg-gold-500 py-2.5 font-bold text-zinc-950 hover:bg-gold-400">
                    Daftarkan & Check-In
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function checkInDesk(cfg) {
    return {
        query: '',
        results: [],
        recent: cfg.recent,
        nextOrder: cfg.nextOrder,
        nextSpeedBonus: cfg.nextSpeedBonus,
        checkedInCount: cfg.checkedInCount,
        totalCount: cfg.totalCount,
        banner: '',
        errorMsg: '',
        walkIn: { nim_code: '', full_nim: '', name: '', group_name: '', gender: '', poin_inisialisasi: 0, jabatan: 'BUKAN' },

        async search() {
            if (this.query.trim().length < 1) { this.results = []; return; }
            const res = await fetch(`${cfg.searchUrl}?q=${encodeURIComponent(this.query)}`, { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const data = await res.json();
                this.results = (data.results || []).map(p => ({ ...p, jabatan: p.jabatan || 'BUKAN' }));
            }
        },
        async confirmCheckIn(p) {
            this.errorMsg = '';
            try {
                const res = await window.apiPost(`/admin/check-in/${p.nim_code}`, { jabatan: p.jabatan });
                this.banner = res.message;
                Object.assign(p, res.participant);
                this.recent.unshift(res.participant);
                this.recent = this.recent.slice(0, 10);
                this.checkedInCount++;
                this.nextOrder = res.participant.arrival_order + 1;
            } catch (e) { this.errorMsg = e.message; }
        },
        async registerWalkIn() {
            this.errorMsg = '';
            try {
                const res = await window.apiPost(cfg.registerUrl, this.walkIn);
                this.banner = res.message;
                this.recent.unshift(res.participant);
                this.recent = this.recent.slice(0, 10);
                this.checkedInCount++;
                this.totalCount++;
                this.nextOrder = res.participant.arrival_order + 1;
                this.walkIn = { nim_code: '', full_nim: '', name: '', group_name: '', gender: '', poin_inisialisasi: 0, jabatan: 'BUKAN' };
            } catch (e) { this.errorMsg = e.message; }
        }
    };
}
</script>
@endpush
