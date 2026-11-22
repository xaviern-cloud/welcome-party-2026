<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Leaderboard Reveal untuk layar proyektor.
 *
 * Saat diaktifkan, Top N (berdasarkan saldo saat itu) disimpan sebagai SNAPSHOT di settings.
 * Reveal hanya membuka snapshot itu satu per satu dari peringkat terbawah (#N) ke #1, sehingga
 * transfer yang masuk di tengah reveal tidak mengacak peringkat. Endpoint publik TIDAK PERNAH
 * mengirim peringkat yang belum di-reveal.
 */
class BigScreenService
{
    public const KEY_MODE     = 'bigscreen_mode';
    public const KEY_REVEALED = 'bigscreen_revealed_count';
    public const KEY_SNAPSHOT = 'bigscreen_snapshot';

    public const MODE_HIDDEN      = 'hidden';
    public const MODE_LEADERBOARD = 'leaderboard';

    // ================================================================ Tampilan

    /** State untuk /api/big-screen/state (publik, hanya yang sudah di-reveal). */
    public function publicState(): array
    {
        $s      = $this->read();
        $active = $this->isActive($s);

        if (! $active) {
            return [
                'mode'           => self::MODE_HIDDEN,
                'snapshot_id'    => null,
                'total'          => 0,
                'revealed_count' => 0,
                'latest_rank'    => null,
                'entries'        => [],
            ];
        }

        $all      = $s['snapshot']['entries'];            // urut peringkat 1..N
        $total    = count($all);
        $revealed = min($s['revealed'], $total);
        $showBal  = (bool) ($s['snapshot']['show_balance'] ?? true);

        // Urutan reveal: N, N-1, ..., sehingga yang terbuka adalah K entri terakhir dari daftar
        $visible = $revealed > 0 ? array_slice($all, $total - $revealed) : [];

        return [
            'mode'           => self::MODE_LEADERBOARD,
            'snapshot_id'    => $s['snapshot']['id'],
            'total'          => $total,
            'revealed_count' => $revealed,
            'latest_rank'    => $revealed > 0 ? $total - $revealed + 1 : null,
            'entries'        => array_map(fn (array $e) => [
                'rank'    => $e['rank'],
                'name'    => $e['name'],
                'group'   => $e['group'],
                'balance' => $showBal ? $e['balance'] : null,
            ], $visible),
        ];
    }

    /** State untuk panel kontrol Admin (termasuk peringkat yang belum di-reveal). */
    public function adminState(): array
    {
        $s      = $this->read();
        $active = $this->isActive($s);
        $total  = $active ? count($s['snapshot']['entries']) : 0;
        $rev    = $active ? min($s['revealed'], $total) : 0;

        $entries = [];

        if ($active) {
            foreach ($s['snapshot']['entries'] as $e) {
                $entries[] = $e + ['revealed' => $e['rank'] > $total - $rev];
            }
        }

        return [
            'mode'           => $active ? self::MODE_LEADERBOARD : self::MODE_HIDDEN,
            'active'         => $active,
            'snapshot_id'    => $active ? $s['snapshot']['id'] : null,
            'taken_at'       => $active ? ($s['snapshot']['taken_at'] ?? null) : null,
            'show_balance'   => $active ? (bool) ($s['snapshot']['show_balance'] ?? true) : true,
            'total'          => $total,
            'revealed_count' => $rev,
            'next_rank'      => $active && $rev < $total ? $total - $rev : null,
            'finished'       => $active && $total > 0 && $rev >= $total,
            'default_size'   => $s['default_size'],
            'entries'        => $entries,
        ];
    }

    // ================================================================ Kontrol

    /** Ambil snapshot Top N baru dan buka mode leaderboard dengan 0 peringkat terbuka. */
    public function activate(?int $size = null, bool $showBalance = true): array
    {
        return DB::transaction(function () use ($size, $showBalance) {
            $this->lockState();

            $size = $size ?? max(1, (int) Setting::getValue('leaderboard_size', '10'));

            $top = User::participants()->checkedIn()
                ->orderByDesc('balance')
                ->orderBy('arrival_order')
                ->limit($size)
                ->get(['nim_code', 'name', 'group_name', 'balance']);

            if ($top->isEmpty()) {
                throw new BusinessRuleException('Belum ada peserta yang check-in, leaderboard belum bisa diaktifkan.', 409);
            }

            $entries = $top->values()->map(fn (User $u, int $i) => [
                'rank'     => $i + 1,
                'nim_code' => $u->nim_code,
                'name'     => $u->name,
                'group'    => $u->group_name,
                'balance'  => (int) $u->balance,
            ])->all();

            $snapshot = [
                'id'           => (string) Str::uuid(),
                'taken_at'     => now()->toIso8601String(),
                'show_balance' => $showBalance,
                'entries'      => $entries,
            ];

            Setting::putValue(self::KEY_SNAPSHOT, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            Setting::putValue(self::KEY_REVEALED, 0);
            Setting::putValue(self::KEY_MODE, self::MODE_LEADERBOARD);

            return $this->adminState();
        }, 3);
    }

    /**
     * Buka satu peringkat berikutnya (#N lalu turun ke #1).
     * Jika $expectedRevealed diberikan dan tidak sama dengan kondisi sebenarnya (klik ganda / panel basi),
     * tidak ada yang berubah dan 'changed' bernilai false.
     *
     * @return array{changed:bool, state:array}
     */
    public function revealNext(?int $expectedRevealed = null): array
    {
        return DB::transaction(function () use ($expectedRevealed) {
            $this->lockState();
            $s = $this->read();

            if (! $this->isActive($s)) {
                throw new BusinessRuleException('Mode leaderboard belum diaktifkan.', 409);
            }

            $total = count($s['snapshot']['entries']);

            if ($expectedRevealed !== null && $expectedRevealed !== $s['revealed']) {
                return ['changed' => false, 'state' => $this->adminState()];
            }

            if ($s['revealed'] >= $total) {
                throw new BusinessRuleException('Semua peringkat sudah ditampilkan.', 409);
            }

            Setting::putValue(self::KEY_REVEALED, $s['revealed'] + 1);

            return ['changed' => true, 'state' => $this->adminState()];
        }, 3);
    }

    /** Tutup layar leaderboard (proyektor kembali ke layar tunggu). */
    public function hide(): array
    {
        return DB::transaction(function () {
            $this->lockState();
            Setting::putValue(self::KEY_MODE, self::MODE_HIDDEN);

            return $this->adminState();
        }, 3);
    }

    // ================================================================ Internal

    /** Mutex semua operasi big screen: baris settings 'bigscreen_mode'. Wajib di dalam transaksi. */
    private function lockState(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key'        => self::KEY_MODE,
            'value'      => self::MODE_HIDDEN,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Setting::query()->whereKey(self::KEY_MODE)->lockForUpdate()->first();
    }

    /** @return array{mode:string, revealed:int, snapshot:?array, default_size:int} */
    private function read(): array
    {
        $rows = Setting::query()
            ->whereIn('key', [self::KEY_MODE, self::KEY_REVEALED, self::KEY_SNAPSHOT, 'leaderboard_size'])
            ->pluck('value', 'key');

        $snapshot = null;

        if (! empty($rows[self::KEY_SNAPSHOT])) {
            $decoded = json_decode($rows[self::KEY_SNAPSHOT], true);

            if (is_array($decoded) && isset($decoded['id'], $decoded['entries']) && is_array($decoded['entries'])) {
                $snapshot = $decoded;
            }
        }

        return [
            'mode'         => $rows[self::KEY_MODE] ?? self::MODE_HIDDEN,
            'revealed'     => (int) ($rows[self::KEY_REVEALED] ?? 0),
            'snapshot'     => $snapshot,
            'default_size' => max(1, (int) ($rows['leaderboard_size'] ?? 10)),
        ];
    }

    private function isActive(array $s): bool
    {
        return $s['mode'] === self::MODE_LEADERBOARD && $s['snapshot'] !== null;
    }
}
