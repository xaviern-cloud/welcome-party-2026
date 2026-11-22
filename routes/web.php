<?php

use App\Http\Controllers\AdminControlController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BigScreenController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\ParticipantTransferController;
use App\Http\Controllers\PreEventScoreController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------- Publik
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

// Layar proyektor: publik & tanpa session
Route::get('/big-screen', [BigScreenController::class, 'show'])->name('bigscreen');
Route::get('/big-screen/state', [BigScreenController::class, 'state'])->name('bigscreen.public-state');

// ---------------------------------------------------------------- Terautentikasi
Route::middleware('auth')->group(function () {
    Route::get('/', [AuthController::class, 'home'])->name('home');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// ---------------------------------------------------------------- Peserta (wajib sudah check-in)
Route::middleware(['auth', 'checked.in', 'role:participant'])->group(function () {
    Route::get('/dashboard', [ParticipantTransferController::class, 'dashboard'])->name('participant.dashboard');
    Route::get('/me/state', [ParticipantTransferController::class, 'state'])->middleware('throttle:120,1')->name('participant.state');

    Route::get('/transfer', [ParticipantTransferController::class, 'create'])->name('transfer.create');
    Route::get('/transfer/lookup', [ParticipantTransferController::class, 'lookup'])->middleware('throttle:120,1')->name('transfer.lookup');
    Route::post('/transfer', [ParticipantTransferController::class, 'store'])->middleware('throttle:30,1')->name('transfer.store');
    Route::get('/catalog', [ParticipantTransferController::class, 'catalog'])->name('participant.catalog');
});

// ---------------------------------------------------------------- Panitia (admin)
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard & kontrol
    Route::get('/', [AdminControlController::class, 'dashboard'])->name('dashboard');
    Route::post('/freeze', [AdminControlController::class, 'freeze'])->name('freeze');
    Route::post('/participants/{nimCode}/reset-device', [AdminControlController::class, 'resetDevice'])
        ->where('nimCode', '[0-9A-Za-z]{3}')->name('participants.reset-device');
    Route::get('/participants/search', [AdminControlController::class, 'searchParticipants'])->name('participants.search');
    Route::get('/transactions', [AdminControlController::class, 'transactions'])->name('transactions');
    Route::get('/export/balances', [AdminControlController::class, 'exportBalances'])->name('export.balances');

    // Lelang (potong saldo pemenang)
    Route::get('/auction', [AdminControlController::class, 'auction'])->name('auction');
    Route::post('/auction', [AdminControlController::class, 'deductAuction'])->name('auction.deduct');
    Route::post('/auction/catalog', [AdminControlController::class, 'catalogStore'])->name('auction.catalog.store');
    Route::post('/auction/catalog/{id}/toggle', [AdminControlController::class, 'catalogToggle'])->name('auction.catalog.toggle');
    Route::delete('/auction/catalog/{id}', [AdminControlController::class, 'catalogDestroy'])->name('auction.catalog.destroy');

    // Kontrol Big Screen / Leaderboard Reveal
    Route::get('/bigscreen/state', [AdminControlController::class, 'bigScreenState'])->name('bigscreen.state');
    Route::post('/bigscreen/activate', [AdminControlController::class, 'bigScreenActivate'])->name('bigscreen.activate');
    Route::post('/bigscreen/reveal', [AdminControlController::class, 'bigScreenReveal'])->name('bigscreen.reveal');
    Route::post('/bigscreen/hide', [AdminControlController::class, 'bigScreenHide'])->name('bigscreen.hide');

    // Pra-acara: peserta, skor, rumus
    Route::get('/pre-event', [PreEventScoreController::class, 'index'])->name('pre-event.index');
    Route::post('/pre-event/participants', [PreEventScoreController::class, 'store'])->name('pre-event.store');
    Route::put('/pre-event/participants/{nimCode}', [PreEventScoreController::class, 'update'])
        ->where('nimCode', '[0-9A-Za-z]{3}')->name('pre-event.update');
    Route::delete('/pre-event/participants/{nimCode}', [PreEventScoreController::class, 'destroy'])
        ->where('nimCode', '[0-9A-Za-z]{3}')->name('pre-event.destroy');
    Route::put('/pre-event/settings', [PreEventScoreController::class, 'updateSettings'])->name('pre-event.settings');

    // Import CSV/Excel (upload, pratinjau, konfirmasi)
    Route::get('/import', [PreEventScoreController::class, 'importForm'])->name('import.form');
    Route::post('/import', [PreEventScoreController::class, 'importUpload'])->name('import.upload');
    Route::get('/import/template', [PreEventScoreController::class, 'importTemplate'])->name('import.template');
    Route::get('/import/preview/{token}', [PreEventScoreController::class, 'importPreview'])
        ->where('token', '[A-Za-z0-9]{40}')->name('import.preview');
    Route::post('/import/commit', [PreEventScoreController::class, 'importCommit'])->name('import.commit');

    // Meja Penerima Tamu
    Route::get('/check-in', [CheckInController::class, 'index'])->name('checkin.index');
    Route::get('/check-in/search', [CheckInController::class, 'search'])->name('checkin.search');
    Route::post('/check-in/register', [CheckInController::class, 'registerAndCheckIn'])->name('checkin.register');
    Route::post('/check-in/{nimCode}', [CheckInController::class, 'checkIn'])
        ->where('nimCode', '[0-9A-Za-z]{3}')->name('checkin.confirm');
});
