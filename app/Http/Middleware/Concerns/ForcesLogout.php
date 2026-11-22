<?php

namespace App\Http\Middleware\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

trait ForcesLogout
{
    /**
     * Logout sesi perangkat ini saja. Sengaja TIDAK menyentuh current_session_id di database,
     * karena kolom itu sekarang dimiliki perangkat baru yang sedang aktif.
     */
    protected function forceLogout(Request $request, string $message, string $code): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'code' => $code], 401);
        }

        return redirect()->route('login')->with('error', $message);
    }
}
