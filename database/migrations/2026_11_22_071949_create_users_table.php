<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // Primary Key = 3 digit terakhir NIM ('001', '056') atau kode panitia ('ADM', 'AD1')
            $table->char('nim_code', 3)->primary();
            $table->string('full_nim', 15)->nullable()->unique();
            $table->string('name', 120);
            $table->string('role', 12)->default('participant')->index(); // admin | participant

            $table->string('password');            // hash bcrypt (untuk login)
            $table->text('password_plain')->nullable(); // terenkripsi (untuk ekspor panitia)

            // Data pra-acara
            $table->string('group_name', 40)->nullable()->index();
            $table->char('gender', 1)->nullable();           // L | P
            $table->string('jabatan', 10)->nullable();       // KETUA | null
            $table->decimal('poin_inisialisasi', 8, 2)->default(0);

            // Hasil kalkulasi (pra-check-in = estimasi, setelah check-in = terkunci permanen)
            $table->decimal('rata_rata_kelompok', 10, 2)->default(0);
            $table->bigInteger('mata_uang_dasar')->default(0);
            $table->bigInteger('bonus_kelompok')->default(0);
            $table->bigInteger('bonus_kecepatan_hadir')->default(0);
            $table->bigInteger('bonus_jabatan')->default(0);
            $table->bigInteger('total_mata_uang_final')->nullable(); // null sampai check-in

            // Saldo aktif (berubah lewat transfer / lelang)
            $table->bigInteger('balance')->default(0);

            // Kehadiran
            $table->boolean('is_checked_in')->default(false)->index();
            $table->unsignedInteger('arrival_order')->nullable()->unique();
            $table->timestamp('checked_in_at')->nullable();
            $table->char('checked_in_by', 3)->nullable();

            // Single-device login
            $table->string('current_session_id', 255)->nullable()->index();

            $table->rememberToken();
            $table->timestamps();
        });

        // Pengaman terakhir: saldo tidak boleh negatif (MySQL 8.0.16+, MariaDB 10.2+, PostgreSQL)
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_balance_non_negative CHECK (balance >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
