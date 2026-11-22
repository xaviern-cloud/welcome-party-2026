<?php

namespace App\Services;

use App\Models\Transaction;

class TransactionPresenter
{
    public const LABELS = [
        Transaction::TYPE_INITIAL  => 'Saldo Awal',
        Transaction::TYPE_TRANSFER => 'Transfer',
        Transaction::TYPE_AUCTION  => 'Potongan Lelang',
    ];

    /**
     * Satu baris mutasi dari sudut pandang peserta $me.
     * Eager-load yang disarankan: sender:nim_code,name,group_name dan receiver:nim_code,name,group_name.
     */
    public static function forParticipant(Transaction $t, string $me): array
    {
        $direction    = 'in';
        $label        = self::LABELS[$t->type] ?? $t->type;
        $counterparty = null;
        $balanceAfter = $t->user_balance_after;

        if ($t->type === Transaction::TYPE_TRANSFER) {
            $outgoing = $t->sender_nim_code === $me;
            $other    = $outgoing ? $t->receiver : $t->sender;
            $otherCode = $outgoing ? $t->receiver_nim_code : $t->sender_nim_code;

            $direction    = $outgoing ? 'out' : 'in';
            $label        = ($outgoing ? 'Transfer ke ' : 'Transfer dari ').($other?->name ?? $otherCode);
            $balanceAfter = $outgoing ? $t->user_balance_after : $t->receiver_balance_after;
            $counterparty = [
                'nim_code'   => $otherCode,
                'name'       => $other?->name,
                'group_name' => $other?->group_name,
            ];
        } elseif ($t->type === Transaction::TYPE_AUCTION) {
            $direction = 'out';
        }

        return [
            'id'            => $t->id,
            'type'          => $t->type,
            'direction'     => $direction,
            'label'         => $label,
            'counterparty'  => $counterparty,
            'amount'        => $t->amount,
            'signed_amount' => $direction === 'out' ? -$t->amount : $t->amount,
            'balance_after' => $balanceAfter,
            'description'   => $t->description,
            'created_at'    => $t->created_at?->toIso8601String(),
            'time'          => $t->created_at?->format('d/m/Y H:i:s'),
        ];
    }

    /** Satu baris mutasi untuk tampilan Admin (audit). */
    public static function forAdmin(Transaction $t): array
    {
        return [
            'id'          => $t->id,
            'type'        => $t->type,
            'type_label'  => self::LABELS[$t->type] ?? $t->type,
            'sender'      => $t->sender_nim_code ? ['nim_code' => $t->sender_nim_code, 'name' => $t->sender?->name] : null,
            'receiver'    => $t->receiver_nim_code ? ['nim_code' => $t->receiver_nim_code, 'name' => $t->receiver?->name] : null,
            'user'        => $t->user_nim_code ? ['nim_code' => $t->user_nim_code, 'name' => $t->user?->name] : null,
            'amount'      => $t->amount,
            'description' => $t->description,
            'created_by'  => $t->created_by,
            'time'        => $t->created_at?->format('d/m/Y H:i:s'),
        ];
    }
}
