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
use Illuminate\Database\Eloquent\Builder;
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
            ->whereIn('type', [Transaction::TYPE_AUCTION, Transaction::TYPE_AUCTION_CASH])
            ->with('user:nim_code,name')
            ->latest('id')->limit(20)->get()
            ->map(function (Transaction $t) {
                $base   = TransactionPresenter::forAdmin($t);
                $winner = $t->user ? "{$t->user->name} (#{$t->user_nim_code})" : "#{$t->user_nim_code}";
                $item   = $t->description ?? ($base['title'] ?? 'Item Lelang');

                return array_merge($base, [
                    'item_label'  => $item,
                    'winner_text' => $winner,
                    'is_rupiah'   => $t->type === Transaction::TYPE_AUCTION_CASH,
                    'time_text'   => $t->created_at?->format('d/m/Y H:i:s') ?? ($base['time'] ?? ''),
                ]);
            })->values();

        return view('admin.auction', [
            'recent'      => $recent,
            'frozen'      => Setting::isFrozen(),
            'catalog'     => $this->readCatalog(),
            'liveAuction' => self::getLiveAuctionState(),
        ]);
    }

    public function deductAuction(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'winner'          => ['required', 'string', 'max:10'],
            'price'           => ['required', 'integer', 'min:1', 'max:1000000000'],
            'currency'        => ['nullable', 'in:coins,cash'],
            'item'            => ['required', 'string', 'max:200'],
            'item_code'       => ['nullable', 'string', 'max:30'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ], [
            'winner.required' => 'Pilih pemenang lelang (3-digit NIM).',
            'price.required'  => 'Harga deal wajib diisi.',
            'price.integer'   => 'Harga deal harus berupa bilangan bulat.',
            'item.required'   => 'Keterangan item lelang wajib diisi.',
        ]);

        $currency  = $data['currency'] ?? 'coins';
        $itemCode  = strtoupper(trim((string) ($data['item_code'] ?? '')));
        $itemTitle = trim($data['item']);
        $fullLabel = ($itemCode !== '' && ! str_starts_with($itemTitle, "[{$itemCode}]"))
            ? "[{$itemCode}] {$itemTitle}"
            : $itemTitle;

        $winnerCode = User::normalizeCode($data['winner']);
        $winnerUser = User::query()->whereKey($winnerCode)->first();
        if (! $winnerUser) {
            throw new BusinessRuleException("Akun dengan kode #{$winnerCode} tidak ditemukan.");
        }

        if ($currency === 'cash') {
            $tx = DB::transaction(function () use ($winnerUser, $data, $fullLabel, $request) {
                return Transaction::create([
                    'type'               => Transaction::TYPE_AUCTION_CASH,
                    'user_nim_code'      => $winnerUser->nim_code,
                    'amount'             => (int) $data['price'],
                    'user_balance_after' => (int) $winnerUser->balance,
                    'description'        => $fullLabel.' [Uang Asli Rp '.number_format((int) $data['price'], 0, ',', '.').']',
                    'created_by'         => $request->user()->nim_code,
                    'idempotency_key'    => $data['idempotency_key'] ?? null,
                ]);
            });
        } else {
            $tx = $this->transfers->deductForAuction(
                $winnerCode,
                (int) $data['price'],
                $fullLabel,
                $request->user()->nim_code,
                $data['idempotency_key'] ?? null
            );
        }

        $tx->loadMissing('user:nim_code,name');

        // Tandai barang di katalog sebagai TERJUAL
        if ($itemCode !== '') {
            $catalog = $this->readCatalog();
            $updated = false;
            foreach ($catalog as &$c) {
                if (strtoupper((string) ($c['code'] ?? '')) === $itemCode) {
                    $c['is_sold']     = true;
                    $c['currency']    = $currency;
                    $c['winner_name'] = $tx->user?->name ?? $winnerUser->name;
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

        // Jika item yang baru terjual sedang tampil di Live Big Screen Lelang, tandai statusnya DEAL / TERJUAL
        $live = self::getLiveAuctionState();
        if (($live['active'] ?? false) && strtoupper((string) ($live['code'] ?? '')) === $itemCode && $itemCode !== '') {
            $live['is_sold']     = true;
            $live['current_bid'] = (int) $tx->amount;
            $live['bidder_name'] = $tx->user?->name ?? $winnerUser->name;
            $live['bidder_nim']  = $tx->user_nim_code;
            Setting::putValue('live_auction_state', json_encode($live, JSON_UNESCAPED_UNICODE));
        }

        $winnerText = "{$winnerUser->name} (#{$tx->user_nim_code})";
        $isRupiah   = $currency === 'cash';
        $txPayload  = array_merge(TransactionPresenter::forAdmin($tx), [
            'item_label'  => $fullLabel,
            'winner_text' => $winnerText,
            'is_rupiah'   => $isRupiah,
            'time_text'   => $tx->created_at?->format('d/m/Y H:i:s') ?? now()->format('d/m/Y H:i:s'),
        ]);

        $message = $isRupiah
            ? "Lelang Uang Asli berhasil ({$fullLabel}): {$winnerText} menang senilai Rp ".$this->formatNumber($tx->amount).' (Saldo koin tidak dipotong).'
            : "Potongan lelang berhasil ({$fullLabel}): {$winnerText} -".$this->formatNumber($tx->amount).' Coins. Sisa saldo: '.$this->formatNumber((int) $tx->user_balance_after).'.';

        return $this->done($request, $message, [
            'transaction' => $txPayload,
            'balance'     => $tx->user_balance_after,
            'sold_code'   => $itemCode,
            'currency'    => $currency,
            'winner_name' => $winnerUser->name,
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
            'currency'       => ['nullable', 'in:coins,cash'],
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
            'currency'       => $data['currency'] ?? 'coins',
            'image_url'      => trim((string) ($data['image_url'] ?? '')),
            'is_revealed'    => false,
            'is_sold'        => false,
            'created_at'     => now()->toIso8601String(),
        ];

        $this->writeCatalog($items);

        return $this->done($request, "Item [{$code}] \"{$data['name']}\" ditambahkan ke katalog.", ['catalog' => $items]);
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
        $from = trim((string) $request->query('from', ''));
        $to   = trim((string) $request->query('to', ''));

        $transactions = $this->filteredTransactionsQuery($type, $q, $from, $to)
            ->with(['sender:nim_code,name', 'receiver:nim_code,name', 'user:nim_code,name'])
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Transaction $t) => TransactionPresenter::forAdmin($t));

        return view('admin.transactions', [
            'transactions' => $transactions,
            'filters'      => ['type' => $type, 'q' => $q, 'from' => $from, 'to' => $to],
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

    public function exportTransactions(Request $request): StreamedResponse
    {
        $type = (string) $request->query('type', '');
        $q    = trim((string) $request->query('q', ''));
        $from = trim((string) $request->query('from', ''));
        $to   = trim((string) $request->query('to', ''));

        $rows = $this->filteredTransactionsQuery($type, $q, $from, $to)
            ->with(['sender:nim_code,name', 'receiver:nim_code,name', 'user:nim_code,name'])
            ->latest('id')
            ->get()
            ->map(function (Transaction $t) {
                $p = TransactionPresenter::forAdmin($t);

                return [
                    $t->id,
                    $t->created_at?->format('Y-m-d H:i:s') ?? '-',
                    $p['label'] ?? $t->type,
                    trim(($p['title'] ?? '').' '.($p['subtitle'] ?? '')),
                    $t->sender_nim_code,
                    $t->receiver_nim_code,
                    $t->user_nim_code,
                    (int) $t->amount,
                ];
            })->all();

        $header = ['ID', 'Waktu (WIB)', 'Jenis', 'Keterangan', 'NIM Pengirim', 'NIM Penerima', 'NIM Terkait', 'Nominal'];

        return TableDownload::make('xlsx', 'audit-transaksi-'.now()->format('Ymd-His'), $header, $rows);
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

    // ================================================================ Reset Statistik Dashboard

    public function resetStats(Request $request): JsonResponse|RedirectResponse
    {
        DB::transaction(function () {
            DB::table('transactions')->delete();
            DB::table('sessions')->whereIn(
                'user_id',
                User::participants()->pluck('nim_code')
            )->delete();

            User::participants()->update([
                'is_checked_in'         => false,
                'arrival_order'         => null,
                'checked_in_at'         => null,
                'checked_in_by'         => null,
                'mata_uang_dasar'       => 0,
                'bonus_kelompok'        => 0,
                'bonus_kecepatan_hadir' => 0,
                'bonus_jabatan'         => 0,
                'total_mata_uang_final' => 0,
                'balance'               => 0,
                'current_session_id'    => null,
            ]);

            Setting::putValue(Setting::ARRIVAL_COUNTER, 0);
            Setting::putValue(BigScreenService::KEY_MODE, BigScreenService::MODE_HIDDEN);
            Setting::putValue(BigScreenService::KEY_REVEALED, 0);
        });

        return $this->done(
            $request,
            'Total Uang Beredar, Transfer P2P, Transaksi Lelang, dan status kehadiran berhasil direset ke 0.'
        );
    }

    public function resetTransactions(Request $request): JsonResponse|RedirectResponse
    {
        $type = (string) $request->input('type', '');
        $q    = trim((string) $request->input('q', ''));
        $from = trim((string) $request->input('from', ''));
        $to   = trim((string) $request->input('to', ''));

        $query   = $this->filteredTransactionsQuery($type, $q, $from, $to);
        $deleted = (clone $query)->count();

        if ($deleted === 0) {
            throw new BusinessRuleException('Tidak ada data transaksi pada rentang/filter tersebut untuk direset.');
        }

        DB::transaction(fn () => $query->delete());

        return $this->done(
            $request,
            "{$deleted} log transaksi audit berhasil dihapus setelah arsip XLS diunduh.",
            [],
            redirect()->route('admin.transactions', ['type' => $type, 'q' => $q, 'from' => $from, 'to' => $to])
        );
    }

    public function exportAuctionHistory(): StreamedResponse
    {
        $rows = Transaction::query()
            ->where('type', Transaction::TYPE_AUCTION)
            ->with('user:nim_code,name,group_name')
            ->latest('id')
            ->get()
            ->map(fn (Transaction $t) => [
                $t->id,
                $t->created_at?->format('Y-m-d H:i:s') ?? '-',
                $t->description ?? '-',
                $t->user_nim_code,
                $t->user?->name ?? '-',
                $t->user?->group_name ?? '-',
                (int) $t->amount,
                (int) $t->user_balance_after,
            ])->all();

        $header = ['ID', 'Waktu (WIB)', 'ID & Nama Barang', 'NIM Pemenang', 'Nama Pemenang', 'Kelompok', 'Harga Deal', 'Sisa Saldo'];

        return TableDownload::make('xlsx', 'riwayat-lelang-'.now()->format('Ymd-His'), $header, $rows);
    }

    public function resetAuctionHistory(Request $request): JsonResponse|RedirectResponse
    {
        $deleted = Transaction::query()->where('type', Transaction::TYPE_AUCTION)->count();

        DB::transaction(function () {
            DB::table('transactions')->where('type', Transaction::TYPE_AUCTION)->delete();

            // Kembalikan status TERJUAL di katalog menjadi tersedia kembali
            $catalog = $this->readCatalog();
            foreach ($catalog as &$c) {
                $c['is_sold'] = false;
                unset($c['winner_name'], $c['winner_nim'], $c['sold_price']);
            }
            unset($c);
            $this->writeCatalog($catalog);
        });

        return $this->done(
            $request,
            "Riwayat lelang ({$deleted} transaksi) berhasil direset & status barang di katalog dikembalikan.",
            [],
            redirect()->route('admin.auction')
        );
    }

    private function filteredTransactionsQuery(string $type, string $q, string $from, string $to): Builder
    {
        return Transaction::query()
            ->when(array_key_exists($type, TransactionPresenter::LABELS), fn ($qq) => $qq->where('type', $type))
            ->when($q !== '', fn ($qq) => $qq->involving(User::normalizeCode($q)))
            ->when($from !== '', fn ($qq) => $qq->where('created_at', '>=', \Illuminate\Support\Carbon::parse($from)))
            ->when($to !== '', fn ($qq) => $qq->where('created_at', '<=', \Illuminate\Support\Carbon::parse($to)));
    }

    // ================================================================ Live Bidding Panggung & Big Screen Lelang

    public static function getLiveAuctionState(): array
    {
        $raw   = Setting::getValue('live_auction_state', '{}');
        $state = json_decode((string) $raw, true);

        return is_array($state) ? $state : ['active' => false];
    }

    public function updateLiveAuction(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'active'      => ['required', 'boolean'],
            'code'        => ['nullable', 'string', 'max:30'],
            'name'        => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'image_url'   => ['nullable', 'string', 'max:500'],
            'currency'    => ['nullable', 'in:coins,cash'],
            'current_bid' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'bidder_nim'  => ['nullable', 'string', 'max:15'],
            'bidder_name' => ['nullable', 'string', 'max:120'],
            'is_sold'     => ['nullable', 'boolean'],
        ]);

        if (! $request->boolean('active')) {
            $state = ['active' => false];
            Setting::putValue('live_auction_state', json_encode($state));

            return $this->done($request, 'Layar Big Screen Lelang ditutup (standby).', ['live' => $state]);
        }

        $bidderNim  = strtoupper(trim((string) ($data['bidder_nim'] ?? '')));
        $bidderName = trim((string) ($data['bidder_name'] ?? ''));

        if ($bidderNim !== '' && $bidderName === '') {
            $u = User::query()->whereKey(User::normalizeCode($bidderNim))->first();
            if ($u) {
                $bidderName = $u->name;
            }
        }

        $state = [
            'active'      => true,
            'code'        => strtoupper(trim((string) ($data['code'] ?? 'LIVE'))),
            'name'        => trim((string) ($data['name'] ?? 'Item Lelang')),
            'description' => trim((string) ($data['description'] ?? '')),
            'image_url'   => trim((string) ($data['image_url'] ?? '')),
            'currency'    => $data['currency'] ?? 'coins',
            'current_bid' => (int) ($data['current_bid'] ?? 0),
            'bidder_nim'  => $bidderNim,
            'bidder_name' => $bidderName,
            'is_sold'     => $request->boolean('is_sold', false),
            'updated_at'  => now()->toIso8601String(),
        ];

        Setting::putValue('live_auction_state', json_encode($state, JSON_UNESCAPED_UNICODE));

        return $this->done($request, 'Big Screen Lelang & HP Peserta diperbarui secara real-time.', ['live' => $state]);
    }

    public function bigScreenAuctionShow(): View
    {
        return view('bigscreen-auction', [
            'initialState' => self::getLiveAuctionState(),
            'stateUrl'     => route('bigscreen.auction.state'),
        ]);
    }

    public function bigScreenAuctionState(): JsonResponse
    {
        return response()
            ->json(self::getLiveAuctionState())
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    // ================================================================ Kustomisasi Pembayaran Uang Asli (Rekening & QR)

    public static function getPaymentConfig(): array
    {
        $raw = Setting::getValue('auction_payment_config', '{}');
        $cfg = json_decode((string) $raw, true);

        return array_merge([
            'qris_image_url' => '',
            'qris_label'     => 'QRIS Panitia Welcome Party 2026',
            'accounts'       => [],
            'whatsapp'       => '',
            'instructions'   => 'Silakan transfer sesuai nominal deal lelang, lalu kirim bukti pembayaran ke panitia.',
        ], is_array($cfg) ? $cfg : []);
    }

    public function paymentSettings(): View
    {
        return view('admin.payment-settings', [
            'config' => self::getPaymentConfig(),
        ]);
    }

    public function savePaymentSettings(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'qris_image_url' => ['nullable', 'string', 'max:500'],
            'qris_label'     => ['nullable', 'string', 'max:120'],
            'whatsapp'       => ['nullable', 'string', 'max:30'],
            'instructions'   => ['nullable', 'string', 'max:600'],
            'accounts'       => ['nullable', 'array'],
            'accounts.*.provider' => ['nullable', 'string', 'max:60'],
            'accounts.*.number'   => ['nullable', 'string', 'max:60'],
            'accounts.*.holder'   => ['nullable', 'string', 'max:100'],
        ]);

        $cleanAccounts = [];
        foreach (($data['accounts'] ?? []) as $acc) {
            $prov = trim((string) ($acc['provider'] ?? ''));
            $num  = trim((string) ($acc['number'] ?? ''));
            $hold = trim((string) ($acc['holder'] ?? ''));
            if ($prov !== '' || $num !== '') {
                $cleanAccounts[] = [
                    'provider' => $prov,
                    'number'   => $num,
                    'holder'   => $hold,
                ];
            }
        }

        $config = [
            'qris_image_url' => trim((string) ($data['qris_image_url'] ?? '')),
            'qris_label'     => trim((string) ($data['qris_label'] ?? 'QRIS Panitia')),
            'accounts'       => $cleanAccounts,
            'whatsapp'       => preg_replace('/[^0-9]/', '', (string) ($data['whatsapp'] ?? '')),
            'instructions'   => trim((string) ($data['instructions'] ?? '')),
        ];

        Setting::putValue('auction_payment_config', json_encode($config, JSON_UNESCAPED_UNICODE));

        return $this->done($request, 'Pengaturan Rekening, QRIS & Halaman Pembayaran Pemenang berhasil disimpan.');
    }
}
