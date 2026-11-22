<?php

use App\Http\Controllers\BigScreenController;
use Illuminate\Support\Facades\Route;

// Otomatis berprefix /api (lihat bootstrap/app.php). Grup "api" tidak memakai session/cookie,
// sehingga polling proyektor sangat ringan dan tidak membuat baris di tabel sessions.
Route::get('/big-screen/state', [BigScreenController::class, 'state'])
    ->middleware('throttle:600,1')
    ->name('api.bigscreen.state');
