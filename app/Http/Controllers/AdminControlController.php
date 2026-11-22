<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BigScreenService;
use App\Services\ParticipantSearch;
use App\Services\TableDownload;
use App\Services\TransactionPresenter;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminControlController extends Controller
{
    public function __construct(
        private readonly TransferService $transfers,
        private readonly BigScreenService $bigScreen,
    ) {
    }

    // ================================================================ Dashboard

    public function dashboard(): View
    {
        $participants = User::participants()->orderBy('nim_code')->get()
            ->map(fn (User $u) => [
                'nim_code'      => $u->nim_code,
                'name'          => $u->name,
                'group_name'    => $u->group_name,
                'is_checked_in' => $u->is_checked_in,
                'arrival_order' => $u->arrival_order,
                'balance'       => $u->balance,
                'has_session'   => $u->current_session_id !== null,
            ])->values();

        $recent = Transaction::query()
            ->with(['sender:nim_code,name', 'receiver:nim_code,name', 'user:nim_code,name'])
            ->latest('id')->limit(20)->get()
            ->map(fn (Transaction $t) => TransactionPresenter::forAdmin($t))->values();

        return view('admin.dashboard', [
            'stats'        => [
                'total'         => User::participants()->count(),
                'checked_in'    => User::participants()->checkedIn()->count(),
                'circulation'   => (int) User::participants()->checkedIn()->sum('balance'),
                'transfers'     => Transaction::query()->where('type', Transaction::TYPE_TRANSFER)->count(),
                'auctions'      => Transaction::query()->where('type', Transaction::TYPE_AUCTION)->count(),
                'auction_total' => (int) Transaction::query()->where('type', Transaction::TYPE_AUCTION)->sum('amount'),
            ],
            'frozen'       => Setting::isFrozen(),
            'bigScreen'    => $this->bigScreen->adminState(),
            'participants' => $participants,
            'recent'       => $recent,
            'formula'      => Setting::formula(),
            'event'        => config('event.name'),
        ]);
    }

    // ================================================================ Freeze, perangkat, pencarian

    /** Sakelar eksplisit ON/OFF (bukan toggle) agar klik ganda tidak membalik status. */
    public function freeze(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['frozen' => ['required', 'boolean']]);
        $frozen = $request->boolean('frozen');

        DB::transaction(function () use ($frozen) {
            // Menunggu transfer yang sedang berjalan (shared lock) selesai; setelah ini transfer baru ditolak
            Setting::query()->whereKey(Setting::TRANSACTIONS_FROZEN)->lockForUpdate()->first();
            Setting::putValue(Setting::TRANSACTIONS_FROZEN, $frozen);
        });

        return $this->done(
            $request,
            $frozen ? 'Transaksi DIBEKUKAN. Peserta tidak dapat melakukan transfer.' : 'Transaksi dibuka kembali.',
            ['frozen' => $frozen]
        );
    }

    public function resetDevice(Request $request, string $nimCode): JsonResponse|RedirectResponse
    {
        $code = User::normalizeCode($nimCode);

        if ($code === $request->user()->nim_code) {
            throw new BusinessRuleException('Tidak dapat mereset sesi akun Anda sendiri.');
        }

        $user = User::query()->whereKey($code)->first();

        if (! $user) {
            throw new BusinessRuleException('Akun tidak ditemukan.', 404);
        }

        $user->resetDeviceSession();

        return $this->done($request, "Sesi perangkat {$user->name} ({$user->nim_code}) direset. Peserta dapat login dari perangkat baru.");
    }

    /** Pencarian peserta untuk form lelang (admin boleh melihat saldo). */
    public function searchParticipants(Request $request): JsonResponse
    {
        $results = ParticipantSearch::find(
            (string) $request->query('q', ''),
            onlyCheckedIn: $request->boolean('checked_in'),
            limit: 8
        );

        return response()->json([
            'results' => $results->map(fn (User $u) => [
                'nim_code'      => $u->nim_code,
                'full_nim'      => $u->full_nim,
                'name'          => $u->name,
                'group_name'    => $u->group_name,
                'is_checked_in' => $u->is_checked_in,
                'balance'       => $u->balance,
            ])->values(),
        ]);
    }

    // ================================================================ Lelang

    public function auction(): View
    {
        $recent = Transaction::query()
            ->where('type', Transaction::TYPE_AUCTION)
            ->with('user:nim_code,name')
            ->latest('id')->limit(15)->get()
            ->map(fn (Transaction $t) => TransactionPresenter::forAdmin($t))->values();

        return view('admin.auction', [
            'recent' => $recent,
            'frozen' => Setting::isFrozen(),
        ]);
    }

    public function deductAuction(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'winner'          => ['required', 'string', 'max:10'],
            'price'           => ['required', 'integer', 'min:1', 'max:1000000000'],
            'item'            => ['required', 'string', 'max:200'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ], [
            'winner.required' => 'Pilih pemenang lelang (3-digit NIM).',
            'price.required'  => 'Harga deal wajib diisi.',
            'price.integer'   => 'Harga deal harus berupa bilangan bulat.',
            'item.required'   => 'Keterangan item lelang wajib diisi.',
        ]);

        $tx = $this->transfers->deductForAuction(
            $data['winner'],
            (int) $data['price'],
            $data['item'],
            $request->user()->nim_code,
            $data['idempotency_key'] ?? null
        );

        $tx->loadMissing('user:nim_code,name');

        $message = "Potongan lelang berhasil: {$tx->user->name} ({$tx->user_nim_code}) -"
            .$this->formatNumber($tx->amount).'. Sisa saldo: '.$this->formatNumber((int) $tx->user_balance_after).'.';

        return $this->done($request, $message, [
            'transaction' => TransactionPresenter::forAdmin($tx),
            'balance'     => $tx->user_balance_after,
        ], redirect()->route('admin.auction'));
    }

    // ================================================================ Audit & ekspor

    public function transactions(Request $request): View
    {
        $type = (string) $request->query('type', '');
        $q    = trim((string) $request->query('q', ''));

        $transactions = Transaction::query()
            ->with(['sender:nim_code,name', 'receiver:nim_code,name', 'user:nim_code,name'])
            ->when(array_key_exists($type, TransactionPresenter::LABELS), fn ($qq) => $qq->where('type', $type))
            ->when($q !== '', fn ($qq) => $qq->involving(User::normalizeCode($q)))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Transaction $t) => TransactionPresenter::forAdmin($t));

        return view('admin.transactions', [
            'transactions' => $transactions,
            'filters'      => ['type' => $type, 'q' => $q],
            'types'        => TransactionPresenter::LABELS,
        ]);
    }

    /** Ekspor rekap saldo + password peserta. Gunakan XLSX agar NIM dan password tidak dirusak Excel. */
    public function exportBalances(Request $request): StreamedResponse
    {
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';

        $header = [
            '3-Digit NIM', 'NIM Lengkap', 'Nama', 'Kelompok', 'Gender', 'Jabatan', 'Status Hadir', 'Urutan Hadir',
            'Poin Inisialisasi', 'Rata-Rata Kelompok', 'Mata Uang Dasar', 'Bonus Kelompok',
            'Bonus Kecepatan Hadir', 'Bonus Jabatan', 'Total Mata Uang Final', 'Saldo Saat Ini', 'Password',
        ];

        $rows = User::participants()->orderBy('nim_code')->get()->map(function (User $u) {
            try {
                $password = $u->password_plain;
            } catch (Throwable) {
                $password = '(tidak dapat didekripsi: APP_KEY berubah?)';
            }

            return [
                $u->nim_code, $u->full_nim, $u->name, $u->group_name, $u->gender, $u->jabatan,
                $u->is_checked_in ? 'Hadir' : 'Belum Hadir', $u->arrival_order,
                (float) $u->poin_inisialisasi, (float) $u->rata_rata_kelompok,
                $u->mata_uang_dasar, $u->bonus_kelompok, $u->bonus_kecepatan_hadir, $u->bonus_jabatan,
                $u->total_mata_uang_final, $u->balance, $password,
            ];
        })->all();

        return TableDownload::make($format, 'rekap-saldo-welcome-party-'.now()->format('Ymd-His'), $header, $rows);
    }

    // ================================================================ Kontrol Big Screen

    public function bigScreenState(): JsonResponse
    {
        return response()->json($this->bigScreen->adminState())->header('Cache-Control', 'no-store');
    }

    public function bigScreenActivate(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'size'         => ['nullable', 'integer', 'min:1', 'max:20'],
            'show_balance' => ['nullable', 'boolean'],
        ]);

        $state = $this->bigScreen->activate(
            isset($data['size']) ? (int) $data['size'] : null,
            $request->boolean('show_balance', true)
        );

        return $this->done(
            $request,
            "Mode leaderboard aktif (Top {$state['total']}). Tekan 'Reveal Peringkat Berikutnya' untuk memulai dari #{$state['total']}.",
            ['state' => $state]
        );
    }

    public function bigScreenReveal(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['expected_revealed' => ['nullable', 'integer', 'min:0']]);

        $result = $this->bigScreen->revealNext(
            isset($data['expected_revealed']) ? (int) $data['expected_revealed'] : null
        );

        $state   = $result['state'];
        $message = $result['changed']
            ? 'Peringkat #'.$state['entries'][$state['total'] - $state['revealed_count']]['rank'].' ditampilkan.'
            : 'Tampilan sudah berubah (klik ganda?). Tidak ada peringkat yang dilewati.';

        return $this->done($request, $message, ['changed' => $result['changed'], 'state' => $state]);
    }

    public function bigScreenHide(Request $request): JsonResponse|RedirectResponse
    {
        $state = $this->bigScreen->hide();

        return $this->done($request, 'Layar leaderboard ditutup.', ['state' => $state]);
    }
}
