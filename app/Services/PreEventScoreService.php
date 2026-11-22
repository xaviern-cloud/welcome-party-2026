<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Manajemen data peserta & skor pra-acara, plus auto-kalkulasi
 * Mata_Uang_Dasar / Rata_Rata_Kelompok / Bonus_Kelompok (Estimasi Modal Awal).
 *
 * Semua mutasi memegang "roster mutex" (Setting::lockArrivalCounter) sehingga tidak bisa
 * berpapasan dengan check-in atau edit lain.
 */
class PreEventScoreService
{
    /** Kolom yang boleh diedit Admin selama peserta belum check-in. */
    private const EDITABLE = ['full_nim', 'name', 'gender', 'group_name', 'jabatan', 'poin_inisialisasi'];

    /** Alias header CSV/Excel (sudah di-slug: huruf kecil, non-alfanumerik jadi "_"). */
    private const HEADER_ALIASES = [
        'nim_code' => 'nim_code', '3_digit_nim' => 'nim_code', '3_digit_nim_belakang' => 'nim_code',
        'nim_3_digit' => 'nim_code', 'kode_nim' => 'nim_code', 'nim3' => 'nim_code',
        'full_nim' => 'full_nim', 'nim_lengkap' => 'full_nim', 'nim' => 'full_nim',
        'name' => 'name', 'nama' => 'name', 'nama_lengkap' => 'name',
        'group_name' => 'group_name', 'kelompok' => 'group_name', 'group' => 'group_name',
        'gender' => 'gender', 'jk' => 'gender', 'jenis_kelamin' => 'gender',
        'poin_inisialisasi' => 'poin_inisialisasi', 'poin' => 'poin_inisialisasi',
        'skor' => 'poin_inisialisasi', 'score' => 'poin_inisialisasi',
        'jabatan' => 'jabatan',
    ];

    // ================================================================ CRUD peserta

    /** Tambah satu peserta (form manual Admin / langkah awal peserta on-the-spot). */
    public function createParticipant(array $input): User
    {
        $data = $this->validated($this->normalizeInput($input) + ['poin_inisialisasi' => 0], true);

        return DB::transaction(function () use ($data) {
            Setting::lockArrivalCounter();

            if (User::query()->whereKey($data['nim_code'])->exists()) {
                throw new BusinessRuleException("Kode NIM {$data['nim_code']} sudah terdaftar.", 409);
            }

            if (! empty($data['full_nim']) && User::query()->where('full_nim', $data['full_nim'])->exists()) {
                throw new BusinessRuleException("NIM lengkap {$data['full_nim']} sudah terdaftar.", 409);
            }

            $plain = User::generatePassword();

            $user = new User();
            $user->forceFill([
                'nim_code'          => $data['nim_code'],
                'full_nim'          => $data['full_nim'] ?? null,
                'name'              => $data['name'],
                'role'              => User::ROLE_PARTICIPANT,
                'group_name'        => $data['group_name'] ?? null,
                'gender'            => $data['gender'] ?? null,
                'jabatan'           => $data['jabatan'] ?? null,
                'poin_inisialisasi' => $data['poin_inisialisasi'],
                'password'          => $plain,
                'password_plain'    => $plain,
            ])->save();

            $this->recalculateMembers($this->membersOf($user), Setting::formula());

            return $user->refresh();
        }, 3);
    }

    /** Edit peserta SELAMA belum check-in. Hanya key yang dikirim yang diubah. */
    public function updateParticipant(string $nimCode, array $input): User
    {
        $nimCode = User::normalizeCode($nimCode);
        $data    = $this->validated(Arr::except($this->normalizeInput($input), ['nim_code']), false);

        return DB::transaction(function () use ($nimCode, $data) {
            Setting::lockArrivalCounter();

            $user = User::participants()->whereKey($nimCode)->lockForUpdate()->first();

            if (! $user) {
                throw new BusinessRuleException('Peserta tidak ditemukan.', 404);
            }

            if ($user->is_checked_in) {
                throw new BusinessRuleException("Data {$user->name} terkunci permanen karena sudah check-in.", 409);
            }

            if (! empty($data['full_nim'])
                && User::query()->where('full_nim', $data['full_nim'])->whereKeyNot($nimCode)->exists()) {
                throw new BusinessRuleException("NIM lengkap {$data['full_nim']} sudah dipakai peserta lain.", 409);
            }

            $oldGroup = $user->group_name;

            $user->fill(Arr::only($data, self::EDITABLE))->save();

            // Hitung ulang kelompok lama (jika pindah) dan kelompok baru
            $formula = Setting::formula();
            $this->recalculateGroup($oldGroup, $formula);

            if ($user->group_name !== $oldGroup) {
                $this->recalculateGroup($user->group_name, $formula);
            }

            if ($user->group_name === null) {
                $this->recalculateMembers(collect([$user]), $formula);
            }

            return $user->refresh();
        }, 3);
    }

    // ================================================================ Import massal

    /**
     * Import massal dari baris yang sudah diparse (CSV/Excel di Tahap 3).
     * Tiap baris adalah array asosiatif dengan header bebas (lihat HEADER_ALIASES).
     * Peserta yang sudah check-in dilewati; baris lain tetap diproses (partial success).
     *
     * @param  iterable<array<string,mixed>> $rows  baris data (tanpa baris header)
     * @return array{created:int, updated:int, skipped:int, errors:string[]}
     */
    public function importRows(iterable $rows): array
    {
        $report = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        DB::transaction(function () use ($rows, &$report) {
            Setting::lockArrivalCounter();

            $formula       = Setting::formula();
            $existing      = User::participants()->get()->keyBy('nim_code');
            $fullNimOwner  = User::query()->whereNotNull('full_nim')->pluck('nim_code', 'full_nim')->all();
            $seen          = [];
            $line          = 1; // baris 1 = header, data mulai baris 2

        foreach ($rows as $raw) {
                $line++;
                $raw = (array) $raw;

                if (isset($raw['_line'])) {
                    $line = (int) $raw['_line']; // nomor baris asli di file sumber
                }

                if (! array_filter($raw, fn ($v) => trim((string) $v) !== '')) {
                    continue; // baris kosong
                }

                try {
                    $row  = $this->normalizeInput($raw);
                    $data = $this->validated($row + ['poin_inisialisasi' => 0], true);
                    $code = $data['nim_code'];
                    $full = $data['full_nim'] ?? null;

                    if (isset($seen[$code])) {
                        throw new BusinessRuleException("Kode NIM {$code} muncul lebih dari sekali di file (pertama di baris {$seen[$code]}).");
                    }
                    $seen[$code] = $line;

                    if ($full && isset($fullNimOwner[$full]) && $fullNimOwner[$full] !== $code) {
                        throw new BusinessRuleException("NIM lengkap {$full} sudah dipakai kode {$fullNimOwner[$full]}.");
                    }

                    $user = $existing->get($code);

                    if ($user) {
                        if ($user->is_checked_in) {
                            throw new BusinessRuleException("{$user->name} ({$code}) sudah check-in dan terkunci.");
                        }

                        $only = array_intersect(self::EDITABLE, array_keys($row));
                        $user->fill(Arr::only($data, $only))->save();
                        $report['updated']++;
                    } else {
                        $plain = User::generatePassword();
                        $user  = new User();
                        $user->forceFill([
                            'nim_code'          => $code,
                            'full_nim'          => $full,
                            'name'              => $data['name'],
                            'role'              => User::ROLE_PARTICIPANT,
                            'group_name'        => $data['group_name'] ?? null,
                            'gender'            => $data['gender'] ?? null,
                            'jabatan'           => $data['jabatan'] ?? null,
                            'poin_inisialisasi' => $data['poin_inisialisasi'],
                            'password'          => $plain,
                            'password_plain'    => $plain,
                        ])->save();

                        $existing->put($code, $user);
                        $report['created']++;
                    }

                    if ($full) {
                        $fullNimOwner[$full] = $code;
                    }
                } catch (ValidationException $e) {
                    $report['skipped']++;
                    $report['errors'][] = "Baris {$line}: ".collect($e->errors())->flatten()->implode(' ');
                } catch (BusinessRuleException $e) {
                    $report['skipped']++;
                    $report['errors'][] = "Baris {$line}: ".$e->getMessage();
                }
            }

            // Satu kali hitung ulang untuk semua kelompok setelah seluruh baris masuk
            $this->recalculateAll($formula);
        });

        return $report;
    }

    // ================================================================ Pengaturan rumus

    /**
     * Simpan konstanta rumus lalu hitung ulang estimasi seluruh peserta yang BELUM check-in.
     * Peserta yang sudah check-in tidak terpengaruh (nilai mereka terkunci).
     *
     * @return array{multiplier_poin:int, base_speed_quota:int, speed_step_poin:int, ketua_bonus_poin:int}
     */
    public function updateFormulaSettings(array $input): array
    {
        $values = Validator::make($input, [
            'multiplier_poin'  => ['required', 'integer', 'min:1', 'max:1000000'],
            'base_speed_quota' => ['required', 'integer', 'min:1', 'max:100000'],
            'speed_step_poin'  => ['required', 'integer', 'min:0', 'max:1000000'],
            'ketua_bonus_poin' => ['required', 'integer', 'min:0', 'max:100000000'],
        ], $this->messages(), [
            'multiplier_poin'  => 'Multiplier poin',
            'base_speed_quota' => 'Base speed quota',
            'speed_step_poin'  => 'Speed step poin',
            'ketua_bonus_poin' => 'Bonus ketua',
        ])->validate();

        DB::transaction(function () use ($values) {
            Setting::lockArrivalCounter();

            foreach ($values as $key => $value) {
                Setting::putValue($key, (int) $value);
            }

            $this->recalculateAll(Setting::formula());
        }, 3);

        return Setting::formula();
    }

    // ================================================================ Kalkulasi

    /** @return Collection<int, User> anggota kelompok (peserta tanpa kelompok = kelompok berisi dirinya sendiri) */
    public function membersOf(User $user): Collection
    {
        if ($user->group_name === null) {
            return collect([$user]);
        }

        return $this->membersOfGroup($user->group_name);
    }

    public function recalculateGroup(?string $group, array $formula): void
    {
        if ($group === null) {
            return;
        }

        $this->recalculateMembers($this->membersOfGroup($group), $formula);
    }

    /** Hitung ulang SELURUH kelompok (dipakai setelah import & ubah rumus). */
    public function recalculateAll(array $formula): void
    {
        User::participants()->whereNotNull('group_name')->distinct()->pluck('group_name')
            ->each(fn (string $group) => $this->recalculateGroup($group, $formula));

        User::participants()->whereNull('group_name')->get()
            ->each(fn (User $solo) => $this->recalculateMembers(collect([$solo]), $formula));
    }

    /**
     * Rumus pra-check-in (aritmetika integer dalam "sen" poin, pembulatan setengah-naik seperti ROUND Excel):
     *   Rata_Rata_Kelompok = rata-rata Poin_Inisialisasi seluruh anggota terdaftar
     *   Mata_Uang_Dasar    = ROUND(Poin * multiplier_poin)
     *   Bonus_Kelompok     = ROUND(Rata_Rata_Kelompok * 0.5)
     * Hanya anggota yang BELUM check-in yang diperbarui; yang sudah check-in tetap terkunci.
     *
     * @param Collection<int, User> $members seluruh anggota satu kelompok (termasuk yang sudah check-in)
     */
    public function recalculateMembers(Collection $members, array $formula): void
    {
        $count = $members->count();

        if ($count === 0) {
            return;
        }

        $sumCents = (int) $members->sum(fn (User $m) => $this->cents($m->poin_inisialisasi));

        $avgCents = intdiv($sumCents * 2 + $count, 2 * $count);              // round(sum / count)
        $bonus    = intdiv($sumCents + 100 * $count, 200 * $count);          // round(sum / count / 100 * 0.5)
        $avgText  = number_format($avgCents / 100, 2, '.', '');

        foreach ($members as $member) {
            if ($member->is_checked_in) {
                continue;
            }

            $member->rata_rata_kelompok = $avgText;
            $member->mata_uang_dasar    = intdiv($this->cents($member->poin_inisialisasi) * $formula['multiplier_poin'] + 50, 100);
            $member->bonus_kelompok     = $bonus;

            if ($member->isDirty()) {
                $member->save();
            }
        }
    }

    /** Ringkasan per kelompok untuk dashboard Admin. group_name = null berarti "tanpa kelompok". */
    public function groupSummary(): Collection
    {
        return User::participants()
            ->selectRaw('group_name, COUNT(*) AS total, SUM(CASE WHEN is_checked_in THEN 1 ELSE 0 END) AS checked_in, MAX(rata_rata_kelompok) AS rata_rata_kelompok')
            ->groupBy('group_name')
            ->orderBy('group_name')
            ->get();
    }

    // ================================================================ Normalisasi & validasi

    public static function normalizeGroup(?string $group): ?string
    {
        $group = trim((string) $group);

        return $group === '' ? null : mb_convert_case(mb_strtolower($group), MB_CASE_TITLE);
    }

    public static function normalizeJabatan(?string $jabatan): ?string
    {
        return str_starts_with(strtoupper(trim((string) $jabatan)), 'KETUA') ? 'KETUA' : null;
    }

    /**
     * Terima header bebas ("3-Digit NIM", "Poin_Inisialisasi", dst.), keluarkan key kanonik.
     * Hanya key yang ADA di input yang dihasilkan (agar aman untuk update parsial).
     */
    public function normalizeInput(array $row): array
    {
        $out = [];

        foreach ($row as $key => $value) {
            $slug  = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower((string) $key)), '_');
            $field = self::HEADER_ALIASES[$slug] ?? null;

            if ($field !== null) {
                $out[$field] = is_string($value) ? trim($value) : $value;
            }
        }

        if (isset($out['full_nim'])) {
            $digits          = preg_replace('/\D/', '', (string) $out['full_nim']);
            $out['full_nim'] = $digits === '' ? null : $digits;
        }

        if (isset($out['nim_code'])) {
            $digits = preg_replace('/\D/', '', (string) $out['nim_code']);

            if (strlen($digits) > 3) {            // user mengisi NIM lengkap di kolom 3-digit
                $out['full_nim'] ??= $digits;
                $digits = substr($digits, -3);
            }

            $out['nim_code'] = $digits === '' ? null : str_pad($digits, 3, '0', STR_PAD_LEFT);
        }

        if (empty($out['nim_code']) && ! empty($out['full_nim'])) {
            $out['nim_code'] = User::deriveNimCode($out['full_nim']);
        }

        if (array_key_exists('name', $out)) {
            $out['name'] = trim((string) preg_replace('/\s+/', ' ', (string) $out['name']));
        }

        if (array_key_exists('group_name', $out)) {
            $out['group_name'] = self::normalizeGroup($out['group_name']);
        }

        if (array_key_exists('jabatan', $out)) {
            $out['jabatan'] = self::normalizeJabatan($out['jabatan']);
        }

        if (array_key_exists('gender', $out)) {
            $g = strtoupper(substr(trim((string) $out['gender']), 0, 1));
            $g = ['M' => 'L', 'F' => 'P'][$g] ?? $g;
            $out['gender'] = in_array($g, ['L', 'P'], true) ? $g : null;
        }

        if (array_key_exists('poin_inisialisasi', $out)) {
            $poin = str_replace(',', '.', trim((string) $out['poin_inisialisasi']));
            $out['poin_inisialisasi'] = $poin === '' ? 0 : $poin;
        }

        return $out;
    }

    private function membersOfGroup(string $group): Collection
    {
        return User::participants()->where('group_name', $group)->orderBy('nim_code')->get();
    }

    private function cents(mixed $poin): int
    {
        return (int) round(((float) $poin) * 100);
    }

    private function validated(array $data, bool $creating): array
    {
        $presence = $creating ? 'required' : 'sometimes';

        return Validator::make($data, [
            'nim_code'          => [$presence, 'regex:/^\d{3}$/'],
            'full_nim'          => ['nullable', 'regex:/^\d{6,15}$/'],
            'name'              => [$presence, 'string', 'max:120'],
            'group_name'        => ['nullable', 'string', 'max:40'],
            'gender'            => ['nullable', 'in:L,P'],
            'jabatan'           => ['nullable', 'in:KETUA'],
            'poin_inisialisasi' => [$presence, 'numeric', 'min:0', 'max:1000000'],
        ], $this->messages(), [
            'nim_code'          => 'Kode NIM (3 digit)',
            'full_nim'          => 'NIM lengkap',
            'name'              => 'Nama',
            'group_name'        => 'Kelompok',
            'gender'            => 'Gender',
            'jabatan'           => 'Jabatan',
            'poin_inisialisasi' => 'Poin inisialisasi',
        ])->validate();
    }

    private function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'regex'    => 'Format :attribute tidak valid.',
            'numeric'  => ':attribute harus berupa angka.',
            'integer'  => ':attribute harus berupa bilangan bulat.',
            'min'      => ':attribute minimal :min.',
            'max'      => ':attribute maksimal :max.',
            'in'       => ':attribute tidak valid.',
        ];
    }
}
