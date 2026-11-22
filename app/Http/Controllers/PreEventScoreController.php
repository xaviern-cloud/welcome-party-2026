<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\Setting;
use App\Models\User;
use App\Services\ParticipantImportParser;
use App\Services\ParticipantSearch;
use App\Services\PreEventScoreService;
use App\Services\TableDownload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PreEventScoreController extends Controller
{
    private const CREATE_FIELDS = ['nim_code', 'full_nim', 'name', 'group_name', 'gender', 'jabatan', 'poin_inisialisasi'];
    private const UPDATE_FIELDS = ['full_nim', 'name', 'group_name', 'gender', 'jabatan', 'poin_inisialisasi'];

    private const FORMULA_FIELDS = ['multiplier_poin', 'base_speed_quota', 'speed_step_poin', 'ketua_bonus_poin'];

    private const FORMAT_LABELS = [
        ParticipantImportParser::FORMAT_FLAT  => 'Tabel datar (header di satu baris)',
        ParticipantImportParser::FORMAT_BLOCK => 'Blok per kelompok ("Kelompok X — N Member")',
    ];

    private const PREVIEW_TTL_MINUTES = 60;

    public function __construct(private readonly PreEventScoreService $scores)
    {
    }

    // ================================================================ Daftar peserta & skor

    public function index(Request $request): View
    {
        $q      = trim((string) $request->query('q', ''));
        $group  = $request->query('group');
        $status = (string) $request->query('status', 'all');

        $participants = User::participants()
            ->when($group === '_none', fn ($qq) => $qq->whereNull('group_name'))
            ->when($group && $group !== '_none', fn ($qq) => $qq->where('group_name', $group))
            ->when($status === 'belum', fn ($qq) => $qq->where('is_checked_in', false))
            ->when($status === 'hadir', fn ($qq) => $qq->where('is_checked_in', true))
            ->when($q !== '', function ($qq) use ($q) {
                $like = '%'.ParticipantSearch::escapeLike(mb_strtolower($q)).'%';

                $qq->where(function ($w) use ($like) {
                    $w->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhere('nim_code', 'like', $like)
                        ->orWhere('full_nim', 'like', $like);
                });
            })
            ->orderBy('group_name')
            ->orderBy('nim_code')
            ->paginate(100)
            ->withQueryString();

        return view('admin.pre-event.index', [
            'participants' => $participants,
            'groupSummary' => $this->scores->groupSummary(),
            'formula'      => Setting::formula(),
            'groups'       => User::participants()->whereNotNull('group_name')->distinct()->orderBy('group_name')->pluck('group_name'),
            'stats'        => [
                'total'      => User::participants()->count(),
                'checked_in' => User::participants()->checkedIn()->count(),
            ],
            'filters'      => ['q' => $q, 'group' => $group, 'status' => $status],
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $user = $this->scores->createParticipant($request->only(self::CREATE_FIELDS));

        return $this->done(
            $request,
            "Peserta {$user->name} ({$user->nim_code}) ditambahkan. Password: {$user->password_plain}",
            ['participant' => $this->payload($user)]
        );
    }

    public function update(Request $request, string $nimCode): JsonResponse|RedirectResponse
    {
        $request->validate([
            'poin_inisialisasi' => ['nullable', 'numeric', 'min:0'],
            'password'          => ['nullable', 'string', 'min:4', 'max:64'],
        ], [
            'password.min' => 'Password baru minimal 4 karakter.',
        ]);

        $code   = User::normalizeCode($nimCode);
        $target = User::participants()->whereKey($code)->firstOrFail();
        $notes  = [];

        if (! $target->is_checked_in) {
            $user = $this->scores->updateParticipant($nimCode, $request->only(self::UPDATE_FIELDS));
        } else {
            $user = $target;

            if ($request->filled('poin_inisialisasi')) {
                $newPoin = round((float) $request->input('poin_inisialisasi'), 2);
                $oldPoin = round((float) $user->poin_inisialisasi, 2);

                if ($newPoin !== $oldPoin) {
                    $formula  = Setting::formula();
                    $newDasar = (int) round($newPoin * (float) $formula['multiplier_poin']);
                    $oldTotal = (int) ($user->total_mata_uang_final ?? 0);
                    $newTotal = $newDasar
                        + (int) $user->bonus_kelompok
                        + (int) $user->bonus_kecepatan_hadir
                        + (int) $user->bonus_jabatan;

                    $diff       = $newTotal - $oldTotal;
                    $newBalance = (int) $user->balance + $diff;

                    if ($newBalance < 0) {
                        throw new BusinessRuleException('Pengurangan poin melebihi sisa saldo aktif peserta saat ini.');
                    }

                    $user->forceFill([
                        'poin_inisialisasi'     => $newPoin,
                        'mata_uang_dasar'       => $newDasar,
                        'total_mata_uang_final' => $newTotal,
                        'balance'               => $newBalance,
                    ])->save();

                    $notes[] = 'Poin & saldo diperbarui (' . ($diff >= 0 ? '+' : '') . $this->formatNumber($diff) . ' coins).';
                }
            }
        }

        $newPassword = strtoupper(trim((string) $request->input('password', '')));

        if ($newPassword !== '') {
            $user->forceFill([
                'password'       => $newPassword,
                'password_plain' => $newPassword,
            ])->save();

            $user->resetDeviceSession();

            $notes[] = "Password baru: {$newPassword}";
        }

        $extra = $notes === [] ? '' : ' ' . implode(' ', $notes);

        return $this->done(
            $request,
            "Data {$user->name} ({$user->nim_code}) diperbarui.{$extra}",
            ['participant' => $this->payload($user)]
        );
    }

    public function updateSettings(Request $request): JsonResponse|RedirectResponse
    {
        $formula = $this->scores->updateFormulaSettings($request->only(self::FORMULA_FIELDS));

        $pending = User::participants()->where('is_checked_in', false)->count();
        $locked  = User::participants()->checkedIn()->count();

        $message = "Pengaturan rumus disimpan. Estimasi {$pending} peserta (belum check-in) dihitung ulang.";

        if ($locked > 0) {
            $message .= " {$locked} peserta yang sudah check-in tidak terpengaruh (nilai terkunci).";
        }

        return $this->done($request, $message, ['formula' => $formula]);
    }

    public function destroy(Request $request, string $nimCode): JsonResponse|RedirectResponse
    {
        $code = User::normalizeCode($nimCode);
        $user = User::participants()->whereKey($code)->firstOrFail();
        $name = $user->name;

        DB::transaction(function () use ($user, $code) {
            DB::table('sessions')->where('user_id', $code)->delete();
            DB::table('transactions')
                ->where('sender_nim_code', $code)
                ->orWhere('receiver_nim_code', $code)
                ->orWhere('user_nim_code', $code)
                ->delete();
            $user->delete();
        });

        return $this->done($request, "Akun peserta {$name} ({$code}) beserta riwayat transaksinya berhasil dihapus.");
    }

    // ================================================================ Import

    public function importForm(): View
    {
        return view('admin.import.index');
    }

    /** Langkah 1: upload, parse, dry-run (di-rollback), simpan pratinjau, lalu redirect ke halaman pratinjau. */
    public function importUpload(Request $request, ParticipantImportParser $parser): RedirectResponse
    {
        $request->validate([
            'file'  => ['required', 'file', 'max:5120', 'extensions:csv,txt,xlsx,xls'],
            'sheet' => ['nullable', 'string', 'max:100'],
        ], [
            'file.required'   => 'Pilih file CSV atau Excel terlebih dahulu.',
            'file.max'        => 'Ukuran file maksimal 5 MB.',
            'file.extensions' => 'File harus berformat .csv, .xlsx, atau .xls.',
        ]);

        $file   = $request->file('file');
        $parsed = $parser->parse((string) $file->getRealPath(), $file->getClientOriginalExtension(), $request->input('sheet'));

        DB::beginTransaction();

        try {
            $dry = $this->scores->importRows($parsed['rows']);
        } finally {
            DB::rollBack();
        }

        $dry['errors'] = array_slice($dry['errors'], 0, 200);

        $token = Str::random(40);

        Cache::put($this->cacheKey($token), [
            'admin' => $request->user()->nim_code,
            'rows'  => $parsed['rows'],
            'meta'  => [
                'format'       => $parsed['format'],
                'format_label' => self::FORMAT_LABELS[$parsed['format']] ?? $parsed['format'],
                'sheet'        => $parsed['sheet'],
                'sheets'       => $parsed['sheets'],
                'warnings'     => $parsed['warnings'],
                'groups'       => $parsed['groups'],
                'row_count'    => count($parsed['rows']),
                'file_name'    => $file->getClientOriginalName(),
            ],
            'dry'   => $dry,
        ], now()->addMinutes(self::PREVIEW_TTL_MINUTES));

        return redirect()->route('admin.import.preview', $token);
    }

    /** Langkah 2: halaman pratinjau (aman di-refresh karena dibaca dari cache). */
    public function importPreview(Request $request, string $token): View|RedirectResponse
    {
        $payload = $this->previewPayload($request, $token);

        if ($payload === null) {
            return redirect()->route('admin.import.form')
                ->with('error', 'Pratinjau tidak ditemukan atau sudah kedaluwarsa. Silakan upload ulang file.');
        }

        return view('admin.import.preview', [
            'token' => $token,
            'meta'  => $payload['meta'],
            'dry'   => $payload['dry'],
            'rows'  => array_slice($payload['rows'], 0, 300),
        ]);
    }

    /** Langkah 3: konfirmasi. Import sungguhan memakai baris yang sama dengan pratinjau. */
    public function importCommit(Request $request): RedirectResponse
    {
        $request->validate(['token' => ['required', 'string', 'size:40']]);

        $token   = (string) $request->input('token');
        $payload = $this->previewPayload($request, $token);

        if ($payload === null) {
            throw new BusinessRuleException('Pratinjau import sudah kedaluwarsa. Silakan upload ulang file.', 410);
        }

        Cache::forget($this->cacheKey($token));

        $report = $this->scores->importRows($payload['rows']);

        $message = "Import selesai: {$report['created']} peserta baru, {$report['updated']} diperbarui, {$report['skipped']} dilewati.";

        return redirect()->route('admin.pre-event.index')
            ->with('success', $message)
            ->with('import_report', $report);
    }

    public function importTemplate(Request $request): StreamedResponse
    {
        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'csv';

        $header = ['3-Digit NIM', 'NIM Lengkap', 'Nama', 'Kelompok', 'Gender', 'Poin_Inisialisasi', 'Jabatan'];
        $rows   = [
            ['056', '626115340056', 'Contoh Nama Satu', 'Laravel', 'L', 80, 'KETUA'],
            ['057', '626115340057', 'Contoh Nama Dua', 'Laravel', 'P', 90, ''],
        ];

        return TableDownload::make($format, 'template-import-peserta', $header, $rows);
    }

    // ================================================================ Internal

    private function previewPayload(Request $request, string $token): ?array
    {
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            return null;
        }

        $payload = Cache::get($this->cacheKey($token));

        if (! is_array($payload) || ($payload['admin'] ?? null) !== $request->user()->nim_code) {
            return null;
        }

        return $payload;
    }

    private function cacheKey(string $token): string
    {
        return 'import-preview:'.$token;
    }

    private function payload(User $u): array
    {
        return [
            'nim_code'           => $u->nim_code,
            'full_nim'           => $u->full_nim,
            'name'               => $u->name,
            'group_name'         => $u->group_name,
            'gender'             => $u->gender,
            'jabatan'            => $u->jabatan,
            'poin_inisialisasi'  => $u->poin_inisialisasi,
            'rata_rata_kelompok' => $u->rata_rata_kelompok,
            'mata_uang_dasar'    => $u->mata_uang_dasar,
            'bonus_kelompok'     => $u->bonus_kelompok,
            'estimated_balance'  => $u->estimated_balance,
            'is_checked_in'      => $u->is_checked_in,
        ];
    }
}
