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

    // ================================================================ Lelang

    public function auction(): View
    {
        $recent = Transaction::query()
            ->where('type', Transaction::TYPE_AUCTION)
            ->with('user:nim_code,name')
            ->latest('id')->limit(15)->get()
            ->map(function (Transaction $t) {
                $base   = TransactionPresenter::forAdmin($t);
                $winner = $t->user ? "{$t->user->name} (#{$t->user_nim_code})" : "#{$t->user_nim_code}";
                $item   = $t->note ?? $t->description ?? ($base['title'] ?? 'Item Lelang');

                return array_merge($base, [
                    'item_label'  => $item,
                    'winner_text' => $winner,
                    'time_text'   => $t->created_at?->format('d/m/Y H:i:s') ?? ($base['time'] ?? ''),
                ]);
            })->values();

        return view('admin.auction', [
            'recent'  => $recent,
            'frozen'  => Setting::isFrozen(),
            'catalog' => $this->readCatalog(),
        ]);
    }

    public function deductAuction(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'winner'          => ['required', 'string', 'max:10'],
            'price'           => ['required', 'integer', 'min:1', 'max:1000000000'],
            'item'            => ['required', 'string', 'max:200'],
            'item_code'       => ['nullable', 'string', 'max:30'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ], [
            'winner.required' => 'Pilih pemenang lelang (3-digit NIM).',
            'price.required'  => 'Harga deal wajib diisi.',
            'price.integer'   => 'Harga deal harus berupa bilangan bulat.',
            'item.required'   => 'Keterangan item lelang wajib diisi.',
        ]);

        $itemCode  = strtoupper(trim((string) ($data['item_code'] ?? '')));
        $itemTitle = trim($data['item']);
        $fullLabel = ($itemCode !== '' && ! str_starts_with($itemTitle, "[{$itemCode}]"))
            ? "[{$itemCode}] {$itemTitle}"
            : $itemTitle;

        $tx = $this->transfers->deductForAuction(
            $data['winner'],
            (int) $data['price'],
            $fullLabel,
            $request->user()->nim_code,
            $data['idempotency_key'] ?? null
        );

        $tx->loadMissing('user:nim_code,name');

        // Tandai barang di katalog sebagai TERJUAL beserta nama pemenang & harga deal
        if ($itemCode !== '') {
            $catalog = $this->readCatalog();
            $updated = false;
            foreach ($catalog as &$c) {
                if (strtoupper((string) ($c['code'] ?? '')) === $itemCode) {
                    $c['is_sold']     = true;
                    $c['winner_name'] = $tx->user?->name ?? 'Peserta';
                    $c['winner_nim']  = $tx->user_nim_code;
                    $c['sold_price']  = (int) $tx->amount;
                    $updated          = true;
                    break;
                }
            }
            unset($c);

            if ($updated) {
                $this->writeCatalog($catalog);
            }
        }

        $winnerText = $tx->user ? "{$tx->user->name} (#{$tx->user_nim_code})" : "#{$tx->user_nim_code}";
        $txPayload  = array_merge(TransactionPresenter::forAdmin($tx), [
            'item_label'  => $fullLabel,
            'winner_text' => $winnerText,
            'time_text'   => $tx->created_at?->format('d/m/Y H:i:s') ?? now()->format('d/m/Y H:i:s'),
        ]);

        $message = "Potongan lelang berhasil ({$fullLabel}): {$winnerText} -"
            .$this->formatNumber($tx->amount).'. Sisa saldo: '.$this->formatNumber((int) $tx->user_balance_after).'.';

        return $this->done($request, $message, [
            'transaction' => $txPayload,
            'balance'     => $tx->user_balance_after,
            'sold_code'   => $itemCode,
            'winner_name' => $tx->user?->name ?? 'Peserta',
            'winner_nim'  => $tx->user_nim_code,
            'sold_price'  => (int) $tx->amount,
        ], redirect()->route('admin.auction'));
    }

    // ================================================================ Katalog Lelang (Buka / Sembunyikan)

    public function catalogStore(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:150'],
            'description'    => ['nullable', 'string', 'max:500'],
            'starting_price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'image_url'      => ['nullable', 'string', 'max:500'],
        ], [
            'name.required'           => 'Nama item katalog wajib diisi.',
            'starting_price.required' => 'Harga awal wajib diisi.',
        ]);

        $items   = $this->readCatalog();
        $nextNum = 1;
        foreach ($items as $existing) {
            if (preg_match('/(\d+)$/', (string) ($existing['code'] ?? ''), $m)) {
                $nextNum = max($nextNum, ((int) $m[1]) + 1);
            } else {
                $nextNum = max($nextNum, count($items) + 1);
            }
        }
        $code = 'BRG-'.str_pad((string) $nextNum, 2, '0', STR_PAD_LEFT);

        $items[] = [
            'id'             => (string) \Illuminate\Support\Str::uuid(),
            'code'           => $code,
            'name'           => trim($data['name']),
            'description'    => trim((string) ($data['description'] ?? '')),
            'starting_price' => (int) $data['starting_price'],
            'image_url'      => trim((string) ($data['image_url'] ?? '')),
            'is_revealed'    => false,
            'is_sold'        => false,
            'created_at'     => now()->toIso8601String(),
        ];

        $this->writeCatalog($items);

        return $this->done($request, "Item [{$code}] \"{$data['name']}\" ditambahkan ke katalog (status: Tersembunyi).", ['catalog' => $items]);
    }

    public function catalogToggle(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $items   = $this->readCatalog();
        $found   = false;
        $label   = '';
        $visible = false;

        foreach ($items as &$item) {
            if (($item['id'] ?? '') === $id) {
                $item['is_revealed'] = ! (bool) ($item['is_revealed'] ?? false);
                $visible             = $item['is_revealed'];
                $label               = $item['name'] ?? 'Item';
                $found               = true;
                break;
            }
        }
        unset($item);

        if (! $found) {
            throw new BusinessRuleException('Item katalog tidak ditemukan.', 404);
        }

        $this->writeCatalog($items);

        return $this->done(
            $request,
            $visible ? "Item \"{$label}\" DIBUKA untuk peserta." : "Item \"{$label}\" DISEMBUNYIKAN dari peserta.",
            ['catalog' => $items]
        );
    }

    public function catalogDestroy(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $items = array_values(array_filter(
            $this->readCatalog(),
            fn (array $item) => ($item['id'] ?? '') !== $id
        ));

        $this->writeCatalog($items);

        return $this->done($request, 'Item katalog dihapus.', ['catalog' => $items]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function getCatalogItems(bool $onlyRevealed = false): array
    {
        $raw   = Setting::getValue('auction_catalog_items', '[]');
        $items = json_decode((string) $raw, true);

        if (! is_array($items)) {
            return [];
        }

        if ($onlyRevealed) {
            return array_values(array_filter($items, fn (array $i) => (bool) ($i['is_revealed'] ?? false)));
        }

        return array_values($items);
    }

    /** @return array<int, array<string, mixed>> */
    private function readCatalog(): array
    {
        return self::getCatalogItems(false);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function writeCatalog(array $items): void
    {
        Setting::putValue('auction_catalog_items', json_encode(array_values($items), JSON_UNESCAPED_UNICODE));
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
