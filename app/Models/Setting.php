<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

class Setting extends Model
{
    public const ARRIVAL_COUNTER      = 'arrival_counter';
    public const TRANSACTIONS_FROZEN  = 'transactions_frozen';

    /** Konstanta rumus (bisa diubah Admin sebelum acara). */
    public const FORMULA_DEFAULTS = [
        'multiplier_poin'  => 41,
        'base_speed_quota' => 64,
        'speed_step_poin'  => 10,
        'ketua_bonus_poin' => 1000,
    ];

    /** State operasional (dipakai Tahap 3: freeze & big screen). */
    public const STATE_DEFAULTS = [
        'arrival_counter'          => '0',
        'transactions_frozen'      => '0',
        'bigscreen_mode'           => 'hidden', // hidden | leaderboard
        'bigscreen_revealed_count' => '0',
        'leaderboard_size'         => '10',
    ];

    protected $primaryKey = 'key';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        return static::query()->whereKey($key)->value('value') ?? $default;
    }

    public static function putValue(string $key, string|int|bool $value): void
    {
        $value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** @return array{multiplier_poin:int, base_speed_quota:int, speed_step_poin:int, ketua_bonus_poin:int} */
    public static function formula(): array
    {
        $rows   = static::query()->whereIn('key', array_keys(self::FORMULA_DEFAULTS))->pluck('value', 'key');
        $result = [];

        foreach (self::FORMULA_DEFAULTS as $key => $default) {
            $result[$key] = (int) ($rows[$key] ?? $default);
        }

        return $result;
    }

    public static function isFrozen(): bool
    {
        return static::getValue(self::TRANSACTIONS_FROZEN, '0') === '1';
    }

    /**
     * Kunci baris penghitung kedatangan (sekaligus "roster mutex").
     * WAJIB dipanggil di dalam DB::transaction.
     */
    public static function lockArrivalCounter(): self
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('lockArrivalCounter() harus dipanggil di dalam DB::transaction().');
        }

        $row = static::query()->whereKey(self::ARRIVAL_COUNTER)->lockForUpdate()->first();

        if ($row) {
            return $row;
        }

        // Fallback jika seeder belum jalan. Dilakukan hanya saat baris belum ada.
        DB::table('settings')->insertOrIgnore([
            'key'        => self::ARRIVAL_COUNTER,
            'value'      => '0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return static::query()->whereKey(self::ARRIVAL_COUNTER)->lockForUpdate()->firstOrFail();
    }
}
