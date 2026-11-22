<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ParticipantSearch;
use App\Services\TransactionPresenter;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParticipantTransferController extends Controller
{
    public function __construct(private readonly TransferService $transfers)
    {
    }

    /** Dashboard pribadi: saldo, rincian modal awal, QR, riwayat. Peserta hanya melihat dirinya sendiri. */
    public function dashboard(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('participant.dashboard', [
            'user'         => $user,
            'breakdown'    => [
                'dasar'     => $user->mata_uang_dasar,
                'kelompok'  => $user->bonus_kelompok,
                'kecepatan' => $user->bonus_kecepatan_hadir,
                'ketua'     => $user->bonus_jabatan,
                'total'     => $user->total_mata_uang_final,
            ],
            'frozen'       => Setting::isFrozen(),
            'transferUrl'  => route('transfer.create', ['to' => $user->nim_code]), // isi QR Code
            'transactions' => $this->history($user->nim_code),
            'stateUrl'     => route('participant.state'),
        ]);
    }

    /** Dipolling dashboard peserta untuk saldo & riwayat real-time. */
    public function state(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'balance'      => $user->balance,
            'frozen'       => Setting::isFrozen(),
            'transactions' => $this->history($user->nim_code),
        ])->header('Cache-Control', 'no-store');
    }

    /** Halaman transfer. /transfer?to=056 (hasil scan QR) langsung memilih penerima. */
    public function create(Request $request): View
    {
        /** @var User $user */
        $user     = $request->user();
        $receiver = null;
        $toError  = null;
        $to       = trim((string) $request->query('to', ''));

        if ($to !== '') {
            $code = User::normalizeCode($to);

            if ($code === $user->nim_code) {
                $toError = 'Ini adalah QR Code Anda sendiri.';
            } else {
                $found = User::participants()->checkedIn()->whereKey($code)->first(['nim_code', 'name', 'group_name']);

                if ($found) {
                    $receiver = [
                        'nim_code'   => $found->nim_code,
                        'name'       => $found->name,
                        'group_name' => $found->group_name,
                    ];
                } else {
                    $toError = 'Penerima tidak ditemukan atau belum berstatus Hadir.';
                }
            }
        }

        return view('participant.transfer', [
            'user'      => $user,
            'balance'   => $user->balance,
            'frozen'    => Setting::isFrozen(),
            'receiver'  => $receiver,
            'toError'   => $toError,
            'lookupUrl' => route('transfer.lookup'),
            'storeUrl'  => route('transfer.store'),
        ]);
    }

    /** Pencarian penerima: ketik 3 digit NIM (mis. 056) atau nama. Tidak menampilkan saldo siapa pun. */
    public function lookup(Request $request): JsonResponse
    {
        $results = ParticipantSearch::find(
            (string) $request->query('q', ''),
            onlyCheckedIn: true,
            excludeCode: $request->user()->nim_code,
            allowFullNim: false,
            limit: 6
        );

        return response()->json([
            'results' => $results->map(fn (User $u) => [
                'nim_code'   => $u->nim_code,
                'name'       => $u->name,
                'group_name' => $u->group_name,
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'receiver'        => ['required', 'string', 'max:10'],
            'amount'          => ['required', 'integer', 'min:1', 'max:1000000000'],
            'note'            => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ], [
            'receiver.required' => 'Pilih penerima transfer.',
            'amount.required'   => 'Masukkan nominal transfer.',
            'amount.integer'    => 'Nominal harus berupa bilangan bulat.',
            'amount.min'        => 'Nominal harus lebih dari 0.',
        ]);

        $tx = $this->transfers->transfer(
            $request->user()->nim_code,
            $data['receiver'],
            (int) $data['amount'],
            $data['idempotency_key'] ?? null,
            $data['note'] ?? null
        );

        $tx->loadMissing('receiver:nim_code,name,group_name');

        $message = 'Transfer '.$this->formatNumber($tx->amount)." ke {$tx->receiver->name} ({$tx->receiver_nim_code}) berhasil.";

        return $this->done($request, $message, [
            'balance'     => $tx->user_balance_after,
            'transaction' => [
                'id'       => $tx->id,
                'amount'   => $tx->amount,
                'receiver' => [
                    'nim_code'   => $tx->receiver->nim_code,
                    'name'       => $tx->receiver->name,
                    'group_name' => $tx->receiver->group_name,
                ],
                'time'     => $tx->created_at?->format('d/m/Y H:i:s'),
            ],
        ], redirect()->route('participant.dashboard'));
    }

    /** @return array<int, array<string, mixed>> */
    private function history(string $nimCode): array
    {
        return Transaction::query()
            ->involving($nimCode)
            ->with(['sender:nim_code,name,group_name', 'receiver:nim_code,name,group_name'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (Transaction $t) => TransactionPresenter::forParticipant($t, $nimCode))
            ->values()
            ->all();
    }
}
