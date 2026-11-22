@extends('layouts.app')

@section('title', 'Masuk — Welcome Party 2026')

@section('content')
<div class="flex min-h-[82vh] items-center justify-center">
    <div class="w-full max-w-md rounded-2xl border border-zinc-800 bg-zinc-900/80 p-7 shadow-2xl backdrop-blur-md">
        <div class="mb-6 text-center">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl border border-gold-500/40 bg-gold-500/10 font-display text-2xl font-bold text-gold-400">
                W
            </div>
            <h1 class="font-display text-2xl font-bold tracking-wide text-white">WELCOME PARTY 2026</h1>
            <p class="mt-1 text-xs uppercase tracking-widest text-gold-400">{{ config('event.tagline', 'The Penthouse of Freedom') }}</p>
        </div>

        <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
            @csrf

            <div>
                <label for="nim_code" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-zinc-400">
                    3 Digit NIM Terakhir / Kode Panitia
                </label>
                <input
                    id="nim_code"
                    name="nim_code"
                    type="text"
                    value="{{ old('nim_code') }}"
                    placeholder="Contoh: 092"
                    maxlength="10"
                    required
                    autofocus
                    class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 font-mono text-lg tracking-widest text-white placeholder-zinc-600 focus:border-gold-500 focus:outline-none"
                >
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-zinc-400">
                    Password
                </label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="••••••••"
                    required
                    class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-white placeholder-zinc-600 focus:border-gold-500 focus:outline-none"
                >
            </div>

            <button
                type="submit"
                class="mt-2 w-full rounded-xl bg-gradient-to-r from-gold-600 via-gold-500 to-amber-400 py-3 text-sm font-bold text-zinc-950 shadow-lg shadow-gold-500/20 transition hover:brightness-110"
            >
                Masuk ke Sistem
            </button>
        </form>

        <div class="mt-6 rounded-xl border border-zinc-800 bg-zinc-950/70 p-3.5 text-center text-xs text-zinc-400">
            Akun peserta otomatis aktif setelah melakukan <strong class="text-zinc-200">Check-In</strong> di meja Penerima Tamu.
        </div>
    </div>
</div>
@endsection
