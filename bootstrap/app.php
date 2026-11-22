<?php

use App\Http\Middleware\EnsureCheckedIn;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SingleDeviceSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Di belakang Nginx / Vercel / Railway: percayai X-Forwarded-* agar HTTPS & cookie secure benar
        $middleware->trustProxies(at: '*');

        // Dijalankan di SEMUA request web (setelah StartSession), memeriksa 1 akun = 1 perangkat
        $middleware->web(append: [
            SingleDeviceSession::class,
        ]);

        $middleware->alias([
            'checked.in' => EnsureCheckedIn::class,
            'role'       => EnsureRole::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

if (isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL'])) {
    $app->useStoragePath('/tmp/storage');
}

return $app;
