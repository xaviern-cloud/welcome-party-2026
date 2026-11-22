<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Mengubah file CSV/XLSX/XLS menjadi baris kanonik untuk PreEventScoreService::importRows().
 *
 * Format yang dikenali (otomatis, per sheet):
 *  1. FLAT  : tabel datar, header di satu baris (template standar & sheet data_peserta_hadir_lelang_unique).
 *             NIM diambil dari kolom "NIM"/"NIM Lengkap" (3 digit terakhir => nim_code) atau kolom "3-Digit NIM".
 *  2. BLOCK : blok per kelompok. Baris judul "Kelompok Laravel — 13 Member | P=4 L=9", lalu header
 *             "No | NIM Lengkap | 3-digit | Nama | Gender", lalu baris anggota.
 *
 * Kolom lain (Status_Kehadiran, Urutan_Hadir, Pengali, Mata_Uang_*, dst.) diabaikan: kehadiran dan
 * saldo hanya boleh ditetapkan lewat check-in di aplikasi. Kolom yang TIDAK ada di file tidak akan
 * muncul di baris hasil, sehingga tidak menimpa data peserta yang sudah ada.
 */
class ParticipantImportParser
{
    public const FORMAT_FLAT  = 'flat';
    public const FORMAT_BLOCK = 'block';

    private const SPREADSHEET_EXT = ['xlsx', 'xlsm', 'xls', 'ods'];
    private const MAX_ROWS        = 2000;
    private const MAX_COLS        = 40;
    private const MAX_WARNINGS    = 60;

    /** "Kelompok Laravel — 13 Member | P=4 L=9" => grup "Laravel" (pemisah: em dash, en dash, atau hyphen). */
    private const GROUP_TITLE = '/^kelompok\s+(.+?)\s*[—–\-]+\s*\d+\s*members?\b/iu';

    /** Header ter-slug (huruf kecil, non-alfanumerik jadi "_") => kolom kanonik. */
    private const COLUMN_ALIASES = [
        'nim_code' => 'nim_code', '3_digit_nim' => 'nim_code', '3_digit' => 'nim_code',
        '3_digit_nim_belakang' => 'nim_code', 'nim_3_digit' => 'nim_code', 'kode_nim' => 'nim_code', 'nim3' => 'nim_code',
        'full_nim' => 'full_nim', 'nim_lengkap' => 'full_nim', 'nim' => 'full_nim',
        'name' => 'name', 'nama' => 'name', 'nama_lengkap' => 'name', 'nama_peserta' => 'name',
        'group_name' => 'group_name', 'kelompok' => 'group_name', 'group' => 'group_name',
        'gender' => 'gender', 'jk' => 'gender', 'jenis_kelamin' => 'gender',
        'poin_inisialisasi' => 'poin_inisialisasi', 'poin' => 'poin_inisialisasi',
        'skor' => 'poin_inisialisasi', 'score' => 'poin_inisialisasi',
        'jabatan' => 'jabatan',
    ];

    /**
     * @return array{format:string, rows:array<int,array<string,mixed>>, warnings:string[], groups:string[], sheet:?string, sheets:string[]}
     *
     * @throws BusinessRuleException
     */
    public function parse(string $path, string $extension, ?string $sheetName = null): array
    {
        try {
            $ext = strtolower(trim($extension));

            if (in_array($ext, self::SPREADSHEET_EXT, true)) {
                return $this->parseWorkbook($path, $sheetName);
            }

            $result = $this->parseGrid($this->readCsv($path));

            if ($result === null) {
                throw $this->unrecognized();
            }

            return $result + ['sheet' => null, 'sheets' => []];
        } catch (BusinessRuleException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw new BusinessRuleException(
                'File tidak dapat dibaca. Pastikan file .xlsx/.xls/.csv valid dan tidak diproteksi password.'
            );
        }
    }

    // ================================================================ Workbook (XLSX/XLS/ODS)

    private function parseWorkbook(string $path, ?string $sheetName): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $workbook = $reader->load($path);

        try {
            $names = $workbook->getSheetNames();

            if ($sheetName !== null && trim($sheetName) !== '') {
                $sheet = $workbook->getSheetByName(trim($sheetName));

                if ($sheet === null) {
                    throw new BusinessRuleException(
                        'Sheet "'.trim($sheetName).'" tidak ditemukan. Sheet yang ada: '.implode(', ', $names).'.'
                    );
                }

                $candidates = [$sheet];
            } else {
                $candidates = $this->orderSheets($workbook);
            }

            foreach ($candidates as $sheet) {
                $result = $this->parseGrid($this->sheetToGrid($sheet));

                if ($result !== null) {
                    return $result + ['sheet' => $sheet->getTitle(), 'sheets' => $names];
                }
            }

            throw new BusinessRuleException(
                $this->unrecognized()->getMessage().' Sheet yang diperiksa: '.implode(', ', $names).'.'
            );
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    /** Sheet bernama dikenal diperiksa lebih dulu; sisanya mengikuti urutan di file. */
    private function orderSheets(Spreadsheet $workbook): array
    {
        $items = [];

        foreach ($workbook->getAllSheets() as $index => $sheet) {
            $slug = $this->slug($sheet->getTitle());

            $priority = match (true) {
                str_contains($slug, 'data_peserta_hadir_lelang') => 0,
                str_contains($slug, 'inisialisasi')              => 1,
                str_contains($slug, 'peserta'), str_contains($slug, 'member') => 2,
                default                                          => 3,
            };

            $items[] = [$priority, $index, $sheet];
        }

        usort($items, fn (array $a, array $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_map(fn (array $item) => $item[2], $items);
    }

    /** @return array<int, array<int, string>> baris 0-based; nomor baris Excel = indeks + 1 */
    private function sheetToGrid(Worksheet $sheet): array
    {
        $lastRow = min((int) $sheet->getHighestDataRow(), self::MAX_ROWS);
        $lastCol = min(Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), self::MAX_COLS);

        if ($lastRow < 1 || $lastCol < 1) {
            return [];
        }

        $range = 'A1:'.Coordinate::stringFromColumnIndex($lastCol).$lastRow;

        // Formula tidak dihitung massal; hanya sel berformula yang di-resolve satu per satu di bawah
        $raw = $sheet->rangeToArray($range, null, false, false, false);

        foreach ($raw as $r => $row) {
            foreach ($row as $c => $value) {
                if (is_string($value) && $value !== '' && $value[0] === '=') {
                    $raw[$r][$c] = $this->resolveFormula($sheet, $c + 1, $r + 1);
                }
            }
        }

        return array_map(fn (array $row) => array_map(fn ($v) => $this->str($v), $row), $raw);
    }

    private function resolveFormula(Worksheet $sheet, int $col, int $row): mixed
    {
        try {
            return $sheet->getCell([$col, $row])->getCalculatedValue();
        } catch (Throwable) {
            try {
                return $sheet->getCell([$col, $row])->getOldCalculatedValue();
            } catch (Throwable) {
                return null;
            }
        }
    }

    // ================================================================ CSV

    /** @return array<int, array<int, string>> */
    private function readCsv(string $path): array
    {
        $content = file_get_contents($path);

        if ($content === false || trim($content) === '') {
            throw new BusinessRuleException('File kosong.');
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        } elseif (str_starts_with($content, "\xFF\xFE")) {
            $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($content, "\xFE\xFF")) {
            $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE');
        } elseif (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = '';

        foreach (preg_split('/\R/u', $content) ?: [] as $line) {
            $trim = trim($line);

            if ($trim !== '' && ! preg_match('/^sep=.$/i', $trim)) {
                $firstLine = $line;
                break;
            }
        }

        // Excel regional Indonesia menyimpan CSV dengan ";" sehingga pemisah dideteksi otomatis
        $counts = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
        arsort($counts);
        $delimiter = array_key_first($counts);

        if ($counts[$delimiter] === 0) {
            $delimiter = ',';
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $grid = [];

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            // Baris kosong tetap dihitung agar nomor baris pada pesan error sesuai file asli
            $grid[] = $row === [null] ? [] : array_map(fn ($v) => $this->str($v), $row);

            if (count($grid) >= self::MAX_ROWS) {
                break;
            }
        }

        fclose($handle);

        if (isset($grid[0][0]) && preg_match('/^sep=.$/i', $grid[0][0])) {
            $grid[0] = [];
        }

        return $grid;
    }

    // ================================================================ Deteksi & ekstraksi

    private function parseGrid(array $grid): ?array
    {
        $result = $this->parseBlocks($grid) ?? $this->parseFlat($grid);

        if ($result === null || $result['rows'] === []) {
            return null;
        }

        $result['warnings'] = $this->capWarnings($result['warnings']);
        $result['groups']   = array_values(array_unique(array_filter(array_map(
            fn (array $row) => PreEventScoreService::normalizeGroup($row['group_name'] ?? null),
            $result['rows']
        ))));

        return $result;
    }

    /** Format blok per kelompok. Mengembalikan null jika tidak ada satu pun judul "Kelompok X — N Member". */
    private function parseBlocks(array $grid): ?array
    {
        $rows     = [];
        $warnings = [];
        $group    = null;
        $map      = null;
        $titles   = 0;

        foreach ($grid as $i => $cells) {
            $line  = $i + 1;
            $first = $this->firstFilled($cells);

            if ($first !== null && preg_match(self::GROUP_TITLE, $first, $m)) {
                if ($group !== null && $map === null) {
                    $warnings[] = "Kelompok \"{$group}\": header tabel (NIM / Nama) tidak ditemukan, kelompok dilewati.";
                }

                $group = trim($m[1]);
                $map   = null;
                $titles++;
                continue;
            }

            if ($group === null) {
                continue;
            }

            $header = $this->detectHeader($cells);

            if ($header !== null) {
                if ($map !== null) {
                    // Blok baru tanpa judul yang dikenali: jangan salah-tempel anggota ke kelompok sebelumnya
                    $warnings[] = "Baris {$line}: header tabel muncul lagi tanpa judul 'Kelompok ...' yang dikenali; baris berikutnya dilewati sampai judul kelompok berikutnya.";
                    $group = null;
                    $map   = null;
                } else {
                    $map = $header;
                }
                continue;
            }

            if ($map === null) {
                continue;
            }

            $record = $this->extract($cells, $map);

            if ($this->isBlank($record)) {
                continue;
            }

            $record['group_name'] = $group;
            $accepted = $this->accept($record, $line, $warnings);

            if ($accepted !== null) {
                $rows[] = $accepted;
            }
        }

        if ($titles === 0) {
            return null;
        }

        if ($group !== null && $map === null) {
            $warnings[] = "Kelompok \"{$group}\": header tabel (NIM / Nama) tidak ditemukan, kelompok dilewati.";
        }

        return ['format' => self::FORMAT_BLOCK, 'rows' => $rows, 'warnings' => $warnings];
    }

    /** Tabel datar: header dicari di 25 baris pertama. */
    private function parseFlat(array $grid): ?array
    {
        $headerIndex = null;
        $map         = null;

        foreach (array_slice($grid, 0, 25, true) as $i => $cells) {
            $detected = $this->detectHeader($cells);

            if ($detected !== null) {
                $headerIndex = $i;
                $map         = $detected;
                break;
            }
        }

        if ($map === null) {
            return null;
        }

        $rows     = [];
        $warnings = [];

        foreach (array_slice($grid, $headerIndex + 1, null, true) as $i => $cells) {
            $record = $this->extract($cells, $map);

            if ($this->isBlank($record)) {
                continue;
            }

            $accepted = $this->accept($record, $i + 1, $warnings);

            if ($accepted !== null) {
                $rows[] = $accepted;
            }
        }

        return ['format' => self::FORMAT_FLAT, 'rows' => $rows, 'warnings' => $warnings];
    }

    /** Baris dianggap header jika punya kolom nama DAN (3-digit atau NIM lengkap). @return array<string,int>|null */
    private function detectHeader(array $cells): ?array
    {
        $map = [];

        foreach ($cells as $col => $value) {
            if ($value === '') {
                continue;
            }

            $field = self::COLUMN_ALIASES[$this->slug($value)] ?? null;

            if ($field !== null && ! isset($map[$field])) {
                $map[$field] = $col;
            }
        }

        return isset($map['name']) && (isset($map['nim_code']) || isset($map['full_nim'])) ? $map : null;
    }

    /** Hanya kolom yang ada di header yang diambil. */
    private function extract(array $cells, array $map): array
    {
        $record = [];

        foreach ($map as $field => $col) {
            $record[$field] = $this->str($cells[$col] ?? null);
        }

        return $record;
    }

    /** Validasi ringan tingkat baris + penanda nomor baris asli. null = baris dilewati (dengan peringatan). */
    private function accept(array $record, int $line, array &$warnings): ?array
    {
        $nimEmpty = ($record['nim_code'] ?? '') === '' && ($record['full_nim'] ?? '') === '';

        if (($record['name'] ?? '') === '' || $nimEmpty) {
            $warnings[] = "Baris {$line}: nama atau NIM kosong, baris dilewati.";

            return null;
        }

        $explicit = preg_replace('/\D/', '', $record['nim_code'] ?? '');
        $derived  = User::deriveNimCode($record['full_nim'] ?? null);

        if ($explicit !== '' && $derived !== null && str_pad(substr($explicit, -3), 3, '0', STR_PAD_LEFT) !== $derived) {
            $warnings[] = "Baris {$line}: kode 3-digit ({$explicit}) tidak sama dengan 3 digit terakhir NIM lengkap ({$derived}). Kode 3-digit dari kolom yang dipakai.";
        }

        $record['_line'] = $line;

        return $record;
    }

    // ================================================================ Utilitas

    private function firstFilled(array $cells): ?string
    {
        foreach ($cells as $value) {
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function isBlank(array $record): bool
    {
        foreach ($record as $value) {
            if ($value !== '') {
                return false;
            }
        }

        return true;
    }

    private function slug(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($value)), '_');
    }

    /** Nilai sel apa pun menjadi string bersih (angka Excel 626115340056.0 menjadi "626115340056"). */
    private function str(mixed $value): string
    {
        if ($value === null || is_array($value)) {
            return '';
        }

        if (is_object($value) && ! method_exists($value, '__toString')) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (floor($value) === $value && abs($value) < 1e15) {
                return sprintf('%.0f', $value);
            }

            return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
        }

        $text = str_replace("\u{00A0}", ' ', (string) $value);
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return preg_match('/^#(N\/A|VALUE!|REF!|DIV\/0!|NAME\?|NUM!|NULL!)$/', $text) ? '' : $text;
    }

    private function capWarnings(array $warnings): array
    {
        if (count($warnings) <= self::MAX_WARNINGS) {
            return $warnings;
        }

        $extra    = count($warnings) - self::MAX_WARNINGS;
        $warnings = array_slice($warnings, 0, self::MAX_WARNINGS);
        $warnings[] = "... dan {$extra} peringatan lainnya.";

        return $warnings;
    }

    private function unrecognized(): BusinessRuleException
    {
        return new BusinessRuleException(
            'Struktur file tidak dikenali. Format yang didukung: (1) tabel datar dengan header minimal "NIM" dan "Nama"; '
            .'(2) blok per kelompok dengan judul "Kelompok X — N Member" lalu header "No | NIM Lengkap | 3-digit | Nama | Gender".'
        );
    }
}
