<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ForcesLogout;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SingleDeviceSession
{
    use ForcesLogout;

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        if ($user instanceof User && ! $user->isAdmin() && $user->current_session_id !== $request->session()->getId()) {
            return $this->forceLogout($request, 'Akun Anda telah login di perangkat lain.', 'SESSION_REPLACED');
        }

        return $next($request);
    }
}
