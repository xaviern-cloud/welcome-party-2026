<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransferService
{
    public function transfer(
        string $senderCode,
        string $receiverCode,
        int $amount,
        ?string $idempotencyKey = null,
        ?string $note = null,
        bool $bypassFreeze = false
    ): Transaction {
        $senderCode   = User::normalizeCode($senderCode);
        $receiverCode = User::normalizeCode($receiverCode);
        $key          = $this->cleanKey($idempotencyKey);
        $note         = $note !== null ? mb_substr(trim($note), 0, 255) : null;

        if ($amount <= 0) {
            throw new BusinessRuleException('Nominal transfer harus lebih dari 0.');
        }

        if ($senderCode === $receiverCode) {
            throw new BusinessRuleException('Tidak dapat melakukan transfer ke diri sendiri.');
        }

        return DB::transaction(function () use ($senderCode, $receiverCode, $amount, $key, $note, $bypassFreeze) {
            $users = User::query()
                ->whereIn('nim_code', [$senderCode, $receiverCode])
                ->orderBy('nim_code')
                ->lockForUpdate()
                ->get()
                ->keyBy('nim_code');

            /** @var User|null $sender */
            $sender = $users->get($senderCode);
            /** @var User|null $receiver */
            $receiver = $users->get($receiverCode);

            if ($key && ($existing = Transaction::query()->where('idempotency_key', $key)->first())) {
                if ($existing->sender_nim_code !== $senderCode) {
                    throw new BusinessRuleException('Kunci transaksi tidak valid.', 409);
                }

                return $existing;
            }

            $freeze = Setting::query()->whereKey(Setting::TRANSACTIONS_FROZEN)->sharedLock()->first();

            if (! $bypassFreeze && $freeze && $freeze->value === '1') {
                throw new BusinessRuleException('Transaksi sedang dibekukan selama sesi lelang.', 423);
            }

            if (! $sender || ! $sender->isParticipant() || ! $sender->is_checked_in) {
                throw new BusinessRuleException('Akun pengirim tidak aktif.', 403);
            }

            if (! $receiver || ! $receiver->isParticipant()) {
                throw new BusinessRuleException('Penerima tidak ditemukan.', 404);
            }

            if (! $receiver->is_checked_in) {
                throw new BusinessRuleException('Penerima belum berstatus Hadir.');
            }

            if ($sender->balance < $amount) {
                throw new BusinessRuleException('Saldo Anda tidak mencukupi.');
            }

            $sender->balance   -= $amount;
            $receiver->balance += $amount;
            $sender->save();
            $receiver->save();

            return Transaction::create([
                'type'                   => Transaction::TYPE_TRANSFER,
                'sender_nim_code'        => $sender->nim_code,
                'receiver_nim_code'      => $receiver->nim_code,
                'user_nim_code'          => $sender->nim_code,
                'amount'                 => $amount,
                'user_balance_after'     => $sender->balance,
                'receiver_balance_after' => $receiver->balance,
                'description'            => $note,
                'idempotency_key'        => $key,
            ]);
        }, 3);
    }

    public function deductForAuction(
        string $winnerCode,
        int $price,
        string $itemDescription,
        string $adminCode,
        ?string $idempotencyKey = null
    ): Transaction {
        $winnerCode      = User::normalizeCode($winnerCode);
        $itemDescription = mb_substr(trim($itemDescription), 0, 200);
        $key             = $this->cleanKey($idempotencyKey);

        if ($price <= 0) {
            throw new BusinessRuleException('Harga deal harus lebih dari 0.');
        }

        if ($itemDescription === '') {
            throw new BusinessRuleException('Keterangan item lelang wajib diisi.');
        }

        return DB::transaction(function () use ($winnerCode, $price, $itemDescription, $adminCode, $key) {
            $winner = User::participants()->whereKey($winnerCode)->lockForUpdate()->first();

            if (! $winner) {
                throw new BusinessRuleException("Peserta dengan kode NIM {$winnerCode} tidak ditemukan.", 404);
            }

            if ($key && ($existing = Transaction::query()->where('idempotency_key', $key)->first())) {
                return $existing;
            }

            if (! $winner->is_checked_in) {
                throw new BusinessRuleException("{$winner->name} belum berstatus Hadir.");
            }

            if ($winner->balance < $price) {
                $saldo = number_format($winner->balance, 0, ',', '.');
                throw new BusinessRuleException("Saldo {$winner->name} tidak mencukupi (saldo saat ini: {$saldo}).");
            }

            $winner->balance -= $price;
            $winner->save();

            return Transaction::create([
                'type'               => Transaction::TYPE_AUCTION,
                'user_nim_code'      => $winner->nim_code,
                'amount'             => $price,
                'user_balance_after' => $winner->balance,
                'description'        => "Lelang: {$itemDescription}",
                'created_by'         => $adminCode,
                'idempotency_key'    => $key,
            ]);
        }, 3);
    }

    private function cleanKey(?string $key): ?string
    {
        $key = $key !== null ? mb_substr(trim($key), 0, 64) : null;

        return $key === '' ? null : $key;
    }
}
