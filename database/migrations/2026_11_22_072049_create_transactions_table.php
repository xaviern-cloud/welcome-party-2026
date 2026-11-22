<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index(); // initial | transfer | auction

            // transfer : user_nim_code = pengirim, sender = pengirim, receiver = penerima
            // auction  : user_nim_code = pemenang lelang (sender/receiver null)
            // initial  : user_nim_code = peserta yang check-in (saldo awal)
            $table->char('sender_nim_code', 3)->nullable()->index();
            $table->char('receiver_nim_code', 3)->nullable()->index();
            $table->char('user_nim_code', 3)->nullable()->index();

            $table->bigInteger('amount');
            $table->bigInteger('user_balance_after')->nullable();     // saldo user_nim_code sesudah transaksi
            $table->bigInteger('receiver_balance_after')->nullable(); // khusus transfer

            $table->string('description', 255)->nullable();
            $table->char('created_by', 3)->nullable();               // admin yang memproses (lelang/check-in)
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();

            $table->foreign('sender_nim_code')->references('nim_code')->on('users')->restrictOnDelete();
            $table->foreign('receiver_nim_code')->references('nim_code')->on('users')->restrictOnDelete();
            $table->foreign('user_nim_code')->references('nim_code')->on('users')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_amount_non_negative CHECK (amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
