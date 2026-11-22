<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, protected int $status = 422)
    {
        parent::__construct($message);
    }

    /** Pelanggaran aturan bisnis bukan bug, jadi tidak perlu masuk log error. */
    public function report(): bool
    {
        return false;
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], $this->status);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
