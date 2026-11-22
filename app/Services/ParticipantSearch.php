<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Pencarian peserta berbasis 3-digit NIM atau nama.
 * Dipakai oleh Meja Penerima Tamu, form lelang, dan pencarian penerima transfer.
 */
class ParticipantSearch
{
    /**
     * @return Collection<int, User>
     */
    public static function find(
        string $q,
        bool $onlyCheckedIn = false,
        ?string $excludeCode = null,
        bool $allowFullNim = true,
        int $limit = 8
    ): Collection {
        $q = trim($q);

        if ($q === '') {
            return collect();
        }

        $numeric = ctype_digit($q);

        // Nama minimal 2 huruf; angka minimal 1 digit
        if (! $numeric && mb_strlen($q) < 2) {
            return collect();
        }

        // Angka lebih dari 3 digit hanya valid sebagai pencarian NIM lengkap
        if ($numeric && strlen($q) > 3 && ! $allowFullNim) {
            return collect();
        }

        $query = User::participants();

        if ($onlyCheckedIn) {
            $query->where('is_checked_in', true);
        }

        if ($excludeCode !== null) {
            $query->where('nim_code', '!=', $excludeCode);
        }

        if ($numeric) {
            $query->where(function ($w) use ($q, $allowFullNim) {
                if (strlen($q) <= 3) {
                    $w->orWhere('nim_code', str_pad($q, 3, '0', STR_PAD_LEFT));
                }

                if ($allowFullNim && strlen($q) >= 3) {
                    $w->orWhere('full_nim', 'like', '%'.$q);
                }
            });
        } else {
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.self::escapeLike(mb_strtolower($q)).'%']);
        }

        return $query->orderBy('name')->limit($limit)->get();
    }

    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
