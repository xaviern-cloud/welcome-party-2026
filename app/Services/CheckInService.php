<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CheckInService
{
    public function __construct(private readonly PreEventScoreService $preEvent)
    {
    }

    /**
     * Check-in + penguncian permanen, seluruhnya atomik:
     *  1. kunci roster mutex (sekaligus penghitung urutan) -> urutan tidak mungkin ganda
     *  2. hitung ulang rata-rata/dasar/bonus kelompok dari data terbaru lalu kunci
     *  3. Bonus_Kecepatan_Hadir = MAX(0, (base_speed_quota + 1 - urutan) * speed_step_poin)
     *  4. Bonus_Jabatan         = ketua_bonus_poin jika KETUA, selain itu 0
     *  5. Total_Mata_Uang_Final = Dasar + Bonus_Kelompok + Bonus_Kecepatan + Bonus_Jabatan -> saldo awal
     *  6. catat mutasi "Saldo awal" di ledger
     *
     * @param string|null $jabatan 'KETUA' atau null/apa pun selain itu = bukan ketua (konfirmasi akhir di meja registrasi)
     * @param string      $adminCode kode panitia yang memproses (mis. 'ADM')
     */
    public function checkIn(string $nimCode, ?string $jabatan, string $adminCode): User
    {
        $nimCode = User::normalizeCode($nimCode);
        $jabatan = PreEventScoreService::normalizeJabatan($jabatan);

        return DB::transaction(function () use ($nimCode, $jabatan, $adminCode) {
            $counter = Setting::lockArrivalCounter();

            $locked = User::participants()->whereKey($nimCode)->lockForUpdate()->first();

            if (! $locked) {
                throw new BusinessRuleException("Peserta dengan kode NIM {$nimCode} tidak ditemukan.", 404);
            }

            if ($locked->is_checked_in) {
                throw new BusinessRuleException(
                    "{$locked->name} sudah check-in sebagai peserta urutan #{$locked->arrival_order}.",
                    409
                );
            }

            $formula = Setting::formula();

            // Hitung ulang kelompok dari data terbaru, lalu ambil instance target dari koleksi tersebut
            $members = $this->preEvent->membersOf($locked);
            $this->preEvent->recalculateMembers($members, $formula);

            /** @var User $target */
            $target = $members->first(fn (User $m) => $m->nim_code === $nimCode);

            $order = ((int) $counter->value) + 1;
            $counter->value = (string) $order;
            $counter->save();

            $speedBonus   = max(0, ($formula['base_speed_quota'] + 1 - $order) * $formula['speed_step_poin']);
            $jabatanBonus = $jabatan === 'KETUA' ? $formula['ketua_bonus_poin'] : 0;
            $total        = $target->mata_uang_dasar + $target->bonus_kelompok + $speedBonus + $jabatanBonus;

            $target->jabatan               = $jabatan;
            $target->bonus_kecepatan_hadir = $speedBonus;
            $target->bonus_jabatan         = $jabatanBonus;
            $target->total_mata_uang_final = $total;
            $target->balance               = $total;
            $target->arrival_order         = $order;
            $target->is_checked_in         = true;
            $target->checked_in_at         = now();
            $target->checked_in_by         = $adminCode;
            $target->save();

            Transaction::create([
                'type'               => Transaction::TYPE_INITIAL,
                'user_nim_code'      => $target->nim_code,
                'amount'             => $total,
                'user_balance_after' => $total,
                'description'        => "Saldo awal (check-in urutan #{$order})",
                'created_by'         => $adminCode,
            ]);

            return $target;
        }, 3);
    }

    /**
     * Peserta susulan yang belum terdaftar: daftarkan lalu langsung check-in.
     * $data minimal: nim_code (atau full_nim), name, group_name; poin_inisialisasi default 0.
     */
    public function registerAndCheckIn(array $data, ?string $jabatan, string $adminCode): User
    {
        $participant = $this->preEvent->createParticipant($data);

        return $this->checkIn($participant->nim_code, $jabatan, $adminCode);
    }
}
