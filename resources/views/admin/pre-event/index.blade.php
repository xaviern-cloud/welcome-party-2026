@extends('layouts.app')

@section('title', 'Manajemen Pra-Acara — Welcome Party 2026')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">Data Pra-Acara, Rumus & Table Seating</h1>
            <p class="text-xs text-zinc-400">Kelola daftar peserta, poin inisialisasi, rumus perhitungan, dan pembagian meja acara.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.checkin.index') }}" class="rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-2.5 text-xs font-bold text-emerald-300 hover:bg-emerald-500/20">
                Ke Meja Check-In (Ubah Jadi Hadir) →
            </a>
            <a href="{{ route('admin.import.form') }}" class="rounded-xl bg-gold-500 px-4 py-2.5 text-xs font-bold text-zinc-950 hover:bg-gold-400">
                + Import CSV / Excel
            </a>
        </div>
    </div>

    {{-- Pengaturan Rumus --}}
    <form method="POST" action="{{ route('admin.pre-event.settings') }}" class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-5">
        @csrf
        @method('PUT')
        <div class="mb-3 text-sm font-bold text-gold-400">Parameter Rumus Mata Uang</div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label class="mb-1 block text-xs text-zinc-400">Pengali Poin</label>
                <input type="number" name="multiplier_poin" value="{{ $formula['multiplier_poin'] }}" required class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-sm text-white">
            </div>
            <div>
                <label class="mb-1 block text-xs text-zinc-400">Kuota Bonus Cepat</label>
                <input type="number" name="base_speed_quota" value="{{ $formula['base_speed_quota'] }}" required class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-sm text-white">
            </div>
            <div>
                <label class="mb-1 block text-xs text-zinc-400">Step Kecepatan</label>
                <input type="number" name="speed_step_poin" value="{{ $formula['speed_step_poin'] }}" required class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-sm text-white">
            </div>
            <div>
                <label class="mb-1 block text-xs text-zinc-400">Bonus Ketua</label>
                <input type="number" name="ketua_bonus_poin" value="{{ $formula['ketua_bonus_poin'] }}" required class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-sm text-white">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full rounded-xl border border-gold-500/50 bg-gold-500/15 py-2 text-xs font-bold text-gold-400 hover:bg-gold-500/25">
                    Simpan Rumus
                </button>
            </div>
        </div>
    </form>

    {{-- Table Seating Manager --}}
    <div
        x-data="tableSeatingManager({
            saveUrl: '{{ route('admin.pre-event.seating') }}',
            initialTables: @js($seating['tables'] ?? []),
            participants: @js($allParticipantsForSeating ?? [])
        })"
        class="rounded-2xl border border-gold-500/35 bg-zinc-900/90 p-5 shadow-xl"
    >
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-800 pb-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded bg-gold-500/20 px-2 py-0.5 text-[11px] font-extrabold uppercase text-gold-400">Table Seating</span>
                    <h2 class="text-base font-bold text-white">Pengaturan Meja Peserta</h2>
                </div>
                <p class="mt-0.5 text-xs text-zinc-400">Kelompokkan peserta ke dalam meja untuk sesi voting & pengumpulan saldo Juru Bicara Meja.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="autoAssignByGroup()" class="rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs font-semibold text-zinc-200 hover:border-gold-500/50">
                    ⚡ Auto-Bagi per Kelompok
                </button>
                <button type="button" @click="addTable()" class="rounded-xl border border-gold-500/40 bg-gold-500/10 px-3 py-2 text-xs font-bold text-gold-400 hover:bg-gold-500/20">
                    + Tambah Meja
                </button>
                <button type="button" @click="saveSeating()" :disabled="saving" class="rounded-xl bg-emerald-500 px-4 py-2 text-xs font-extrabold text-zinc-950 hover:bg-emerald-400 disabled:opacity-50">
                    <span x-text="saving ? 'Menyimpan...' : '💾 Simpan Table Seating'"></span>
                </button>
            </div>
        </div>

        <div x-show="toast" x-cloak class="mt-3 rounded-xl border border-emerald-500/40 bg-emerald-950/60 px-4 py-2 text-xs text-emerald-200" x-text="toast"></div>

        <div class="mt-4 grid gap-5 lg:grid-cols-12">
            {{-- Kolom Kiri: Peserta Belum Punya Meja --}}
            <div class="rounded-xl border border-zinc-800 bg-zinc-950/80 p-3.5 lg:col-span-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-zinc-300">Belum Masuk Meja (<span x-text="unassigned().length"></span>)</span>
                    <span class="text-[11px] text-zinc-500">Pilih meja tujuan →</span>
                </div>
                <input type="text" x-model="searchPool" placeholder="Cari nama / NIM / kelompok..." class="mt-2 w-full rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-1.5 text-xs text-white">

                <div class="mt-2.5 max-h-80 space-y-1.5 overflow-y-auto pr-1">
                    <template x-for="p in unassigned()" :key="p.nim_code">
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-zinc-800/80 bg-zinc-900/90 px-2.5 py-2 text-xs">
                            <div class="min-w-0">
                                <div class="truncate font-semibold text-white">
                                    <span class="font-mono text-gold-400" x-text="'#' + p.nim_code"></span>
                                    <span x-text="p.name"></span>
                                </div>
                                <div class="truncate text-[10px] text-zinc-400" x-text="p.group_name || 'Tanpa Kelompok'"></div>
                            </div>
                            <select @change="assignToTable($event.target.value, p.nim_code); $event.target.value=''" class="rounded-lg border border-zinc-700 bg-zinc-950 px-2 py-1 text-[11px] text-emerald-300">
                                <option value="">+ Meja</option>
                                <template x-for="t in tables" :key="t.id">
                                    <option :value="t.id" x-text="t.name"></option>
                                </template>
                            </select>
                        </div>
                    </template>
                    <template x-if="unassigned().length === 0">
                        <div class="py-6 text-center text-[11px] text-zinc-500">Semua peserta sudah terdaftar di meja.</div>
                    </template>
                </div>
            </div>

            {{-- Kolom Kanan: Kartu Meja --}}
            <div class="grid gap-3 sm:grid-cols-2 lg:col-span-8">
                <template x-if="tables.length === 0">
                    <div class="col-span-2 flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-700 py-10 text-center">
                        <div class="text-xs font-bold text-zinc-400">Belum ada meja dibuat</div>
                        <p class="mt-1 text-[11px] text-zinc-500">Klik "+ Tambah Meja" atau "Auto-Bagi per Kelompok" di kanan atas.</p>
                    </div>
                </template>

                <template x-for="(t, tIdx) in tables" :key="t.id">
                    <div class="flex flex-col justify-between rounded-xl border border-zinc-800 bg-zinc-950/90 p-3.5">
                        <div>
                            <div class="flex items-center justify-between gap-2 border-b border-zinc-800 pb-2">
                                <input type="text" x-model="t.name" class="w-40 rounded-lg border border-zinc-800 bg-zinc-900 px-2.5 py-1 text-xs font-bold text-gold-400">
                                <div class="flex items-center gap-2">
                                    <span class="rounded bg-zinc-800 px-2 py-0.5 font-mono text-[10px] text-zinc-300" x-text="t.members.length + ' org'"></span>
                                    <button type="button" @click="removeTable(tIdx)" class="text-[11px] text-red-400 hover:underline">Hapus</button>
                                </div>
                            </div>

                            <div class="mt-2.5 space-y-1.5">
                                <template x-for="nim in t.members" :key="nim">
                                    <div class="flex items-center justify-between rounded-lg bg-zinc-900/90 px-2.5 py-1.5 text-xs">
                                        <div class="truncate">
                                            <span class="font-mono text-gold-400" x-text="'#' + nim"></span>
                                            <span class="text-zinc-100" x-text="findName(nim)"></span>
                                        </div>
                                        <button type="button" @click="removeFromTable(tIdx, nim)" class="ml-2 text-zinc-500 hover:text-red-400" title="Keluarkan dari meja">✕</button>
                                    </div>
                                </template>
                                <template x-if="t.members.length === 0">
                                    <div class="py-3 text-center text-[11px] text-zinc-600">Kosong</div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Tambah Peserta Satuan --}}
    <div x-data="{ openAdd: false }" class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-5">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm font-bold text-white">Tambah Peserta Baru</div>
                <div class="text-xs text-zinc-400">Tambahkan peserta susulan ke daftar pra-acara tanpa upload file.</div>
            </div>
            <button type="button" @click="openAdd = !openAdd" class="rounded-xl border border-gold-500/40 bg-gold-500/10 px-3.5 py-2 text-xs font-semibold text-gold-400 hover:bg-gold-500/20">
                <span x-text="openAdd ? 'Tutup Form' : '+ Tambah Peserta'">+ Tambah Peserta</span>
            </button>
        </div>

        <form x-show="openAdd" x-cloak method="POST" action="{{ route('admin.pre-event.store') }}" class="mt-4 grid gap-3 border-t border-zinc-800 pt-4 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            <div>
                <label class="mb-1 block text-xs text-zinc-400">3 Digit NIM *</label>
                <input type="text" name="nim_code" maxlength="10" required placeholder="Contoh: 092" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-xs text-white">
            </div>
            <div>
                <label class="mb-1 block text-xs text-zinc-400">NIM Lengkap (Opsional)</label>
                <input type="text" name="full_nim" maxlength="20" placeholder="626115340092" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-xs text-white">
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs text-zinc-400">Nama Lengkap *</label>
                <input type="text" name="name" required placeholder="Nama Peserta" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-white">
            </div>
            <div>
                <label class="mb-1 block text-xs text-zinc-400">Kelompok</label>
                <input type="text" name="group_name" list="pre-groups" placeholder="Nama Kelompok" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-white">
                <datalist id="pre-groups">
                    @foreach($groups as $g)
                        <option value="{{ $g }}">
                    @endforeach
                </datalist>
            </div>
            <div>
                <label class="mb-1 block text-xs text-zinc-400">Gender</label>
                <select name="gender" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-white">
                    <option value="">-</option>
                    <option value="L">L</option>
                    <option value="P">P</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-zinc-400">Jabatan</label>
                <select name="jabatan" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-white">
                    <option value="BUKAN">Anggota</option>
                    <option value="KETUA">KETUA</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-zinc-400">Poin Inisialisasi</label>
                <input type="number" name="poin_inisialisasi" min="0" step="0.01" value="0" required class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-xs text-white">
            </div>
            <div class="flex justify-end sm:col-span-2 lg:col-span-4">
                <button type="submit" class="rounded-xl bg-emerald-500 px-5 py-2 text-xs font-bold text-zinc-950 hover:bg-emerald-400">
                    Simpan Peserta Baru
                </button>
            </div>
        </form>
    </div>

    {{-- Filter & Pencarian --}}
    <form method="GET" action="{{ route('admin.pre-event.index') }}" class="flex flex-wrap items-center gap-3">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Cari NIM atau nama..." class="rounded-xl border border-zinc-700 bg-zinc-950 px-3.5 py-2 text-xs text-white sm:w-64">
        <select name="group" class="rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-white">
            <option value="">Semua Kelompok</option>
            <option value="_none" {{ $filters['group'] === '_none' ? 'selected' : '' }}>Tanpa Kelompok</option>
            @foreach($groups as $g)
                <option value="{{ $g }}" {{ $filters['group'] === $g ? 'selected' : '' }}>{{ $g }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-white">
            <option value="all" {{ $filters['status'] === 'all' ? 'selected' : '' }}>Semua Status</option>
            <option value="belum" {{ $filters['status'] === 'belum' ? 'selected' : '' }}>Belum Hadir</option>
            <option value="hadir" {{ $filters['status'] === 'hadir' ? 'selected' : '' }}>Sudah Hadir</option>
        </select>
        <button type="submit" class="rounded-xl bg-zinc-800 px-4 py-2 text-xs font-semibold text-white hover:bg-zinc-700">Filter</button>
    </form>

    {{-- Tabel Peserta --}}
    <div class="overflow-x-auto rounded-2xl border border-zinc-800 bg-zinc-900/80">
        <table class="w-full text-left text-xs">
            <thead class="bg-zinc-950 text-zinc-400">
                <tr>
                    <th class="px-4 py-3">NIM</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Kelompok</th>
                    <th class="px-4 py-3">Jabatan</th>
                    <th class="px-4 py-3 text-right">Poin Init</th>
                    <th class="px-4 py-3 text-right">Est. Modal</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            @forelse($participants as $p)
            <tbody x-data="{ editing: false }" class="divide-y divide-zinc-800/70 border-b border-zinc-800/70">
                <tr class="hover:bg-zinc-900">
                    <td class="px-4 py-3 font-mono font-bold text-gold-400">{{ $p->nim_code }}</td>
                    <td class="px-4 py-3 font-medium text-white">{{ $p->name }}</td>
                    <td class="px-4 py-3 text-zinc-300">{{ $p->group_name ?? '-' }}</td>
                    <td class="px-4 py-3">
                        @if($p->jabatan === 'KETUA')
                            <span class="rounded bg-amber-500/20 px-2 py-0.5 font-semibold text-amber-300">KETUA</span>
                        @else
                            <span class="text-zinc-500">Anggota</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right font-mono">{{ $p->poin_inisialisasi }}</td>
                    <td class="px-4 py-3 text-right font-mono font-semibold text-emerald-400">
                        {{ number_format($p->is_checked_in ? $p->total_mata_uang_final : $p->estimated_balance, 0, ',', '.') }}
                    </td>
                    <td class="px-4 py-3">
                        @if($p->is_checked_in)
                            <span class="text-emerald-400">Hadir #{{ $p->arrival_order }}</span>
                        @else
                            <span class="text-zinc-500">Belum Hadir</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-3">
                            <button type="button" @click="editing = !editing" class="text-gold-400 hover:underline">
                                <span x-text="editing ? 'Tutup' : 'Edit'">Edit</span>
                            </button>
                            <form method="POST" action="{{ route('admin.pre-event.destroy', $p->nim_code) }}" onsubmit="return confirm('PERINGATAN: Hapus permanen akun {{ $p->name }} ({{ $p->nim_code }}){{ $p->is_checked_in ? ' yang SUDAH CHECK-IN' : '' }} beserta seluruh riwayat transaksinya?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-400 hover:underline">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <tr x-show="editing" x-cloak class="bg-zinc-950">
                    <td colspan="8" class="p-4">
                        <form method="POST" action="{{ route('admin.pre-event.update', $p->nim_code) }}" class="grid gap-3 sm:grid-cols-8">
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" value="{{ $p->name }}" required placeholder="Nama" {{ $p->is_checked_in ? 'readonly' : '' }} class="rounded-lg border border-zinc-700 bg-zinc-900 px-2.5 py-1.5 text-xs text-white sm:col-span-2">
                            <input type="text" name="group_name" value="{{ $p->group_name }}" placeholder="Kelompok" {{ $p->is_checked_in ? 'readonly' : '' }} class="rounded-lg border border-zinc-700 bg-zinc-900 px-2.5 py-1.5 text-xs text-white">
                            <select name="jabatan" {{ $p->is_checked_in ? 'disabled' : '' }} class="rounded-lg border border-zinc-700 bg-zinc-900 px-2.5 py-1.5 text-xs text-white">
                                <option value="BUKAN" {{ $p->jabatan !== 'KETUA' ? 'selected' : '' }}>Anggota</option>
                                <option value="KETUA" {{ $p->jabatan === 'KETUA' ? 'selected' : '' }}>KETUA</option>
                            </select>
                            <input type="number" name="poin_inisialisasi" value="{{ $p->poin_inisialisasi }}" min="0" step="0.01" class="rounded-lg border border-zinc-700 bg-zinc-900 px-2.5 py-1.5 font-mono text-xs text-white">
                            <input type="text" name="password" minlength="4" placeholder="Password baru (opsional)" class="rounded-lg border border-zinc-700 bg-zinc-900 px-2.5 py-1.5 font-mono text-xs text-amber-300 placeholder:text-zinc-500 sm:col-span-2">
                            <button type="submit" class="rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-bold text-zinc-950 hover:bg-emerald-400">Simpan</button>
                        </form>
                    </td>
                </tr>
            </tbody>
            @empty
            <tbody>
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-zinc-500">Belum ada data peserta. Silakan import CSV/Excel.</td>
                </tr>
            </tbody>
            @endforelse
        </table>
    </div>

    <div>{{ $participants->links() }}</div>
</div>
@endsection

@push('scripts')
<script>
function tableSeatingManager(cfg) {
    return {
        tables: cfg.initialTables || [],
        participants: cfg.participants || [],
        searchPool: '',
        saving: false,
        toast: '',
        findName(nim) {
            const p = this.participants.find(x => x.nim_code === nim);
            return p ? p.name : nim;
        },
        assignedSet() {
            const s = new Set();
            this.tables.forEach(t => (t.members || []).forEach(m => s.add(m)));
            return s;
        },
        unassigned() {
            const assigned = this.assignedSet();
            const q = this.searchPool.trim().toLowerCase();
            return this.participants.filter(p => {
                if (assigned.has(p.nim_code)) return false;
                if (!q) return true;
                return (p.name || '').toLowerCase().includes(q)
                    || (p.nim_code || '').toLowerCase().includes(q)
                    || (p.group_name || '').toLowerCase().includes(q);
            });
        },
        addTable() {
            const num = this.tables.length + 1;
            this.tables.push({
                id: 'TBL-' + Date.now() + '-' + num,
                name: 'Meja ' + num,
                members: []
            });
        },
        removeTable(idx) {
            this.tables.splice(idx, 1);
        },
        assignToTable(tableId, nim) {
            if (!tableId) return;
            this.tables.forEach(t => {
                t.members = (t.members || []).filter(m => m !== nim);
                if (t.id === tableId) t.members.push(nim);
            });
        },
        removeFromTable(tIdx, nim) {
            this.tables[tIdx].members = this.tables[tIdx].members.filter(m => m !== nim);
        },
        autoAssignByGroup() {
            if (!confirm('Bagi otomatis seluruh peserta ke meja berdasarkan nama Kelompok?')) return;
            const byGroup = {};
            this.participants.forEach(p => {
                const g = p.group_name || 'Umum';
                if (!byGroup[g]) byGroup[g] = [];
                byGroup[g].push(p.nim_code);
            });
            this.tables = Object.entries(byGroup).map(([grp, nims], i) => ({
                id: 'TBL-GRP-' + (i + 1),
                name: 'Meja ' + (i + 1) + ' (' + grp + ')',
                members: nims
            }));
        },
        async saveSeating() {
            this.saving = true;
            try {
                const res = await window.apiPost(cfg.saveUrl, { tables: this.tables });
                this.tables = res.seating.tables;
                this.toast = res.message;
                setTimeout(() => this.toast = '', 3500);
            } catch (e) {
                alert(e.message);
            } finally {
                this.saving = false;
            }
        }
    };
}
</script>
@endpush
