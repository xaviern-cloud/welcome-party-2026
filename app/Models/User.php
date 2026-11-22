<?php

namespace App\Models;

use App\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    public const ROLE_ADMIN       = 'admin';
    public const ROLE_PARTICIPANT = 'participant';

    /** Karakter password: uppercase + angka, tanpa karakter yang mudah tertukar (0 O 1 I). */
    private const PASSWORD_CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Kolom yang terkunci permanen begitu peserta check-in. */
    public const LOCKED_AFTER_CHECK_IN = [
        'poin_inisialisasi', 'group_name', 'jabatan',
        'rata_rata_kelompok', 'mata_uang_dasar', 'bonus_kelompok',
        'bonus_kecepatan_hadir', 'bonus_jabatan', 'total_mata_uang_final',
        'is_checked_in', 'arrival_order', 'checked_in_at', 'checked_in_by',
    ];

    protected $primaryKey = 'nim_code';
    public $incrementing  = false;
    protected $keyType    = 'string';

    /** role, password, saldo, dsb. sengaja TIDAK fillable; di-set eksplisit oleh service/seeder. */
    protected $fillable = [
        'nim_code', 'full_nim', 'name', 'group_name', 'gender', 'jabatan', 'poin_inisialisasi',
    ];

    protected $hidden = [
        'password', 'password_plain', 'remember_token', 'current_session_id',
    ];

    protected function casts(): array
    {
        return [
            'password'              => 'hashed',
            'password_plain'        => 'encrypted',
            'poin_inisialisasi'     => 'decimal:2',
            'rata_rata_kelompok'    => 'decimal:2',
            'mata_uang_dasar'       => 'integer',
            'bonus_kelompok'        => 'integer',
            'bonus_kecepatan_hadir' => 'integer',
            'bonus_jabatan'         => 'integer',
            'total_mata_uang_final' => 'integer',
            'balance'               => 'integer',
            'is_checked_in'         => 'boolean',
            'arrival_order'         => 'integer',
            'checked_in_at'         => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Aturan Kunci Permanen: setelah check-in, kolom skor/jabatan/kehadiran tidak boleh berubah.
        static::updating(function (User $user) {
            if (! $user->getOriginal('is_checked_in')) {
                return;
            }

            $violations = array_intersect(array_keys($user->getDirty()), self::LOCKED_AFTER_CHECK_IN);

            if ($violations !== []) {
                throw new BusinessRuleException(
                    "Data {$user->name} terkunci permanen karena sudah check-in.",
                    409
                );
            }
        });
    }

    // ---------------------------------------------------------------- Relasi

    public function sentTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'sender_nim_code', 'nim_code');
    }

    public function receivedTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'receiver_nim_code', 'nim_code');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_nim_code', 'nim_code');
    }

    // ---------------------------------------------------------------- Scopes

    public function scopeParticipants(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_PARTICIPANT);
    }

    public function scopeCheckedIn(Builder $query): Builder
    {
        return $query->where('is_checked_in', true);
    }

    // ---------------------------------------------------------------- Helpers

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isParticipant(): bool
    {
        return $this->role === self::ROLE_PARTICIPANT;
    }

    /** Estimasi modal awal pra-check-in (belum termasuk bonus kecepatan & jabatan). */
    protected function estimatedBalance(): Attribute
    {
        return Attribute::get(fn () => (int) $this->mata_uang_dasar + (int) $this->bonus_kelompok);
    }

    /** '56' => '056', ' adm ' => 'ADM'. */
    public static function normalizeCode(string $code): string
    {
        $code = strtoupper(trim($code));

        if ($code !== '' && ctype_digit($code) && strlen($code) <= 3) {
            return str_pad($code, 3, '0', STR_PAD_LEFT);
        }

        return $code;
    }

    /** '626115340056' => '056'. */
    public static function deriveNimCode(?string $fullNim): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $fullNim);

        return $digits === '' ? null : substr(str_pad($digits, 3, '0', STR_PAD_LEFT), -3);
    }

    public static function generatePassword(int $length = 6): string
    {
        $max = strlen(self::PASSWORD_CHARSET) - 1;
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= self::PASSWORD_CHARSET[random_int(0, $max)];
        }

        return $out;
    }

    /** Peserta: case-insensitive (dinormalisasi uppercase). Admin: persis seperti diketik. */
    public function verifyPassword(string $input): bool
    {
        $candidate = $this->isParticipant() ? strtoupper(trim($input)) : $input;

        return Hash::check($candidate, $this->password);
    }

    /**
     * Tombol "Reset Device Session" (Admin): hapus semua sesi user ini dan kosongkan
     * current_session_id, sehingga peserta bisa login dari HP/browser baru.
     */
    public function resetDeviceSession(): void
    {
        DB::transaction(function () {
            DB::table('sessions')->where('user_id', $this->nim_code)->delete();
            static::query()->whereKey($this->nim_code)->update(['current_session_id' => null]);
        });

        $this->forceFill(['current_session_id' => null])->syncOriginalAttribute('current_session_id');
    }
}
