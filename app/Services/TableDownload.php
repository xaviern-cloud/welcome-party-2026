<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduhan tabel sederhana sebagai CSV (UTF-8 + BOM agar Excel membaca benar) atau XLSX.
 * Di XLSX semua teks (NIM, password) ditulis sebagai string eksplisit, jadi angka nol di depan
 * dan NIM 12 digit tidak rusak. Di CSV, Excel bisa mengubah "056" menjadi 56 dan NIM panjang
 * menjadi notasi ilmiah, jadi gunakan XLSX untuk ekspor.
 */
class TableDownload
{
    public static function make(string $format, string $basename, array $header, array $rows): StreamedResponse
    {
        return $format === 'csv'
            ? self::csv($basename, $header, $rows)
            : self::xlsx($basename, $header, $rows);
    }

    private static function csv(string $basename, array $header, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'csvCell'], array_values($row)), ',', '"', '');
            }

            fclose($out);
        }, $basename.'.csv', [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    private static function xlsx(string $basename, array $header, array $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data');

        foreach (array_values($header) as $c => $title) {
            $sheet->setCellValueExplicit([$c + 1, 1], (string) $title, DataType::TYPE_STRING);
        }

        foreach (array_values($rows) as $r => $row) {
            foreach (array_values($row) as $c => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $coordinate = [$c + 1, $r + 2];

                if (is_bool($value)) {
                    $sheet->setCellValueExplicit($coordinate, $value ? 'Ya' : 'Tidak', DataType::TYPE_STRING);
                } elseif (is_int($value) || is_float($value)) {
                    $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_NUMERIC);
                } else {
                    $sheet->setCellValueExplicit($coordinate, (string) $value, DataType::TYPE_STRING);
                }
            }
        }

        $lastColumn = Coordinate::stringFromColumnIndex(max(1, count($header)));
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->freezePane('A2');

        for ($c = 1; $c <= count($header); $c++) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $basename.'.xlsx', [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Cegah formula injection di Excel/Sheets untuk sel teks yang diawali = + - @. */
    public static function csvCell(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
