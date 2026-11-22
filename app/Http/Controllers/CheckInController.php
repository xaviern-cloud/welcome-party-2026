<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\ParticipantSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class CheckInController extends Controller
{
    public function __construct(private readonly CheckInService $checkIn)
    {
    }

    /** Meja Penerima Tamu. */
    public function index(): View
    {
        $formula   = Setting::formula();
        $nextOrder = (int) Setting::getValue(Setting::ARRIVAL_COUNTER, '0') + 1;

        return view('admin.checkin.index', [
            'stats'          => [
                'total'      => User::participants()->count(),
                'checked_in' => User::participants()->checkedIn()->count(),
            ],
            'nextOrder'      => $nextOrder,
            'nextSpeedBonus' => max(0, ($formula['base_speed_quota'] + 1 - $nextOrder) * $formula['speed_step_poin']),
            'ketuaBonus'     => $formula['ketua_bonus_poin'],
            'recent'         => User::participants()->checkedIn()->orderByDesc('arrival_order')->limit(10)->get()
                ->map(fn (User $u) => $this->payload($u))->values(),
            'groups'         => User::participants()->whereNotNull('group_name')->distinct()->orderBy('group_name')->pluck('group_name'),
        ]);
    }

    /** Pencarian instan: 3 digit NIM, NIM lengkap, atau nama. */
    public function search(Request $request): JsonResponse
    {
        $results = ParticipantSearch::find((string) $request->query('q', ''), limit: 10);

        return response()->json([
            'results' => $results->map(fn (User $u) => $this->payload($u))->values(),
        ]);
    }

    /** Konfirmasi Hadir (Check-In) + penguncian permanen. */
    public function checkIn(Request $request, string $nimCode): JsonResponse|RedirectResponse
    {
        $request->validate(['jabatan' => ['nullable', 'in:KETUA,BUKAN']]);

        $code = User::normalizeCode($nimCode);

        $jabatan = $request->has('jabatan')
            ? $request->input('jabatan')
            : User::participants()->whereKey($code)->value('jabatan');

        $user = $this->checkIn->checkIn($code, $jabatan, $request->user()->nim_code);

        return $this->done($request, $this->successMessage($user), ['participant' => $this->payload($user)]);
    }

    /** Peserta susulan: daftarkan lalu langsung check-in. */
    public function registerAndCheckIn(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'nim_code'          => ['nullable', 'string', 'max:15'],
            'full_nim'          => ['nullable', 'string', 'max:20'],
            'name'              => ['required', 'string', 'max:120'],
            'group_name'        => ['nullable', 'string', 'max:40'],
            'gender'            => ['nullable', 'in:L,P'],
            'poin_inisialisasi' => ['nullable', 'numeric', 'min:0'],
            'jabatan'           => ['nullable', 'in:KETUA,BUKAN'],
        ], [
            'name.required' => 'Nama peserta wajib diisi.',
        ]);

        $user = $this->checkIn->registerAndCheckIn($data, $data['jabatan'] ?? null, $request->user()->nim_code);

        return $this->done(
            $request,
            $this->successMessage($user),
            ['participant' => $this->payload($user)]
        );
    }

    private function plainPassword(User $user): string
    {
        try {
            $plain = (string) $user->password_plain;
        } catch (Throwable) {
            $plain = '';
        }

        if ($plain === '') {
            $plain = User::generatePassword(6);
            $user->forceFill([
                'password'       => $plain,
                'password_plain' => $plain,
            ])->save();
        }

        return $plain;
    }

    private function successMessage(User $user): string
    {
        $password = $this->plainPassword($user);

        return "Check-in berhasil: {$user->name} (NIM: {$user->nim_code}), urutan #{$user->arrival_order}. "
            ."PASSWORD LOGIN: {$password}";
    }

    private function payload(User $u): array
    {
        return [
            'nim_code'              => $u->nim_code,
            'full_nim'              => $u->full_nim,
            'name'                  => $u->name,
            'group_name'            => $u->group_name,
            'gender'                => $u->gender,
            'jabatan'               => $u->jabatan,
            'is_checked_in'         => $u->is_checked_in,
            'arrival_order'         => $u->arrival_order,
            'poin_inisialisasi'     => $u->poin_inisialisasi,
            'mata_uang_dasar'       => $u->mata_uang_dasar,
            'bonus_kelompok'        => $u->bonus_kelompok,
            'bonus_kecepatan_hadir' => $u->bonus_kecepatan_hadir,
            'bonus_jabatan'         => $u->bonus_jabatan,
            'estimated_balance'     => $u->estimated_balance,
            'total_mata_uang_final' => $u->total_mata_uang_final,
            'balance'               => $u->balance,
            'password_plain'        => $u->is_checked_in ? $this->plainPassword($u) : null,
        ];
    }
}
