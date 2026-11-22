<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Transaction extends Model
{
    public const TYPE_INITIAL  = 'initial';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_AUCTION  = 'auction';

    protected $fillable = [
        'type', 'sender_nim_code', 'receiver_nim_code', 'user_nim_code',
        'amount', 'user_balance_after', 'receiver_balance_after',
        'description', 'created_by', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount'                 => 'integer',
            'user_balance_after'     => 'integer',
            'receiver_balance_after' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Ledger bersifat append-only.
        static::updating(function () {
            throw new LogicException('Transaksi bersifat immutable dan tidak boleh diubah.');
        });

        static::deleting(function () {
            throw new LogicException('Transaksi bersifat immutable dan tidak boleh dihapus.');
        });
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_nim_code', 'nim_code');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_nim_code', 'nim_code');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_nim_code', 'nim_code');
    }

    /** Semua transaksi yang menyangkut satu peserta (masuk, keluar, lelang, saldo awal). */
    public function scopeInvolving(Builder $query, string $nimCode): Builder
    {
        return $query->where(function (Builder $q) use ($nimCode) {
            $q->where('sender_nim_code', $nimCode)
                ->orWhere('receiver_nim_code', $nimCode)
                ->orWhere('user_nim_code', $nimCode);
        });
    }
}
