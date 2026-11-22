<?php

namespace App\Http\Controllers;

use App\Services\BigScreenService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class BigScreenController extends Controller
{
    public function __construct(private readonly BigScreenService $bigScreen)
    {
    }

    /** Halaman proyektor (publik, tanpa login agar tidak menendang sesi admin). */
    public function show(): View
    {
        return view('bigscreen', [
            'event'    => config('event.name'),
            'tagline'  => config('event.tagline'),
            'stateUrl' => route('bigscreen.public-state'),
            'pollMs'   => 2000,
        ]);
    }

    /** Dipolling setiap 2 detik. Hanya berisi peringkat yang SUDAH di-reveal. */
    public function state(): JsonResponse
    {
        return response()
            ->json($this->bigScreen->publicState() + [
                'event'   => config('event.name'),
                'tagline' => config('event.tagline'),
            ])
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}