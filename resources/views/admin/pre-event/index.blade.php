@extends('layouts.app')

@section('title', 'Manajemen Pra-Acara — Welcome Party 2026')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">Data Pra-Acara & Rumus Poin</h1>
            <p class="text-xs text-zinc-400">Kelola daftar peserta, poin inisialisasi, dan parameter rumus perhitungan.</p>
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
                <input type="number" name="poin_inisialisasi" min="0" value="0" required class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-xs text-white">
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
            <tbody class="divide-y divide-zinc-800/70">
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
                        @if(!$p->is_checked_in)
                            <div class="inline-flex items-center gap-3">
                                <button type="button" @click="editing = !editing" class="text-gold-400 hover:underline">
                                    <span x-text="editing ? 'Tutup' : 'Edit'">Edit</span>
                                </button>
                                <form method="POST" action="{{ route('admin.pre-event.destroy', $p->nim_code) }}" onsubmit="return confirm('Hapus {{ $p->name }} ({{ $p->nim_code }}) dari daftar?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-400 hover:underline">Hapus</button>
                                </form>
                            </div>
                        @else
                            <span class="text-zinc-600">Terkunci</span>
                        @endif
                    </td>
                </tr>
                @if(!$p->is_checked_in)
                <tr x-show="editing" x-cloak class="bg-zinc-950">
                    <td colspan="8" class="p-4">
                        <form method="POST" action="{{ route('admin.pre-event.update', $p->nim_code) }}" class="grid gap-3 sm:grid-cols-6">
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" value="{{ $p->name }}" required class="rounded-lg border border-zinc-700 bg-zinc-900 px-2.5 py-1.5 text-xs text-white sm:col-span-2">
                            <input type="text" name="group_name" value="{{ $p->group_name }}" placeholder="Kelompok" class="rounded-lg border border-zinc-700 bg-zinc-900 px-2.5 py-1.5 text-xs text-white">
                            <select name="jabatan" class="rounded-lg border border-zinc-700 bg-zinc-900 px-2.5 py-1.5 text-xs text-white">
                                <option value="BUKAN" {{ $p->jabatan !== 'KETUA' ? 'selected' : '' }}>Anggota</option>
                                <option value="KETUA" {{ $p->jabatan === 'KETUA' ? 'selected' : '' }}>KETUA</option>
                            </select>
                            <input type="number" name="poin_inisialisasi" value="{{ $p->poin_inisialisasi }}" min="0" class="rounded-lg border border-zinc-700 bg-zinc-900 px-2.5 py-1.5 font-mono text-xs text-white">
                            <button type="submit" class="rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-bold text-zinc-950">Simpan</button>
                        </form>
                    </td>
                </tr>
                @endif
            </tbody>
            @empty
            <tbody>
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-zinc-500">Belum ada data peserta. Silakan import CSV/Excel.</td>
                </tr>
            </tbody>
            @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $participants->links() }}</div>
</div>
@endsection
