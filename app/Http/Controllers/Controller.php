<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Respons sukses seragam: JSON untuk fetch() Alpine.js, redirect + flash 'success' untuk form biasa.
     */
    protected function done(
        Request $request,
        string $message,
        array $json = [],
        ?RedirectResponse $redirect = null
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message] + $json);
        }

        return ($redirect ?? back())->with('success', $message);
    }

    protected function formatNumber(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}
