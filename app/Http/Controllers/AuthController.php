<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS  = 5;
    private const DECAY_SECONDS = 60;

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nim_code' => ['required', 'string', 'max:10'],
            'password' => ['required', 'string', 'max:100'],
        ], [
            'nim_code.required' => 'Masukkan 3 digit NIM Anda.',
            'password.required' => 'Masukkan password Anda.',
        ]);

        $code        = User::normalizeCode($data['nim_code']);   // '56' => '056', 'adm' => 'ADM'
        $throttleKey = 'login|'.$code.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return $this->loginFailed($request, "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.");
        }

        $user = User::query()->whereKey($code)->first();

        if (! $user || ! $user->verifyPassword($data['password'])) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            return $this->loginFailed($request, 'Kode NIM atau password salah.');
        }

        // Check-In Gate: dicek SETELAH password benar, agar status kehadiran tidak bisa diintip tanpa password
        if ($user->isParticipant() && ! $user->is_checked_in) {
            return $this->loginFailed(
                $request,
                'Silakan lakukan check-in di meja Penerima Tamu terlebih dahulu untuk mengaktifkan akun Anda.'
            );
        }

        RateLimiter::clear($throttleKey);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        // Single-Device: perangkat lama otomatis tertolak oleh middleware pada request berikutnya.
        // Baris sesi lama sengaja TIDAK dihapus agar perangkat lama menerima pesan "login di perangkat lain".
        User::query()->whereKey($user->nim_code)->update([
            'current_session_id' => $request->session()->getId(),
        ]);

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user      = $request->user();
        $sessionId = $request->session()->getId();

        if ($user) {
            User::query()
                ->whereKey($user->nim_code)
                ->where('current_session_id', $sessionId)
                ->update(['current_session_id' => null]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** "/" mengarahkan ke dashboard sesuai role. */
    public function home(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->isAdmin() ? 'admin.dashboard' : 'participant.dashboard');
    }

    private function loginFailed(Request $request, string $message): RedirectResponse
    {
        return back()->withInput($request->only('nim_code'))->with('error', $message);
    }
}
