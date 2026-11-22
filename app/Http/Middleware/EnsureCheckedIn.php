<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ForcesLogout;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCheckedIn
{
    use ForcesLogout;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Panitia tidak perlu check-in; hanya peserta yang wajib berstatus Hadir.
        if ($user && $user->isParticipant() && ! $user->is_checked_in) {
            return $this->forceLogout(
                $request,
                'Silakan lakukan check-in di meja Penerima Tamu terlebih dahulu untuk mengaktifkan akun Anda.',
                'NOT_CHECKED_IN'
            );
        }

        return $next($request);
    }
}
