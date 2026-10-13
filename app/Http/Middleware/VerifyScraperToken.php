<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyScraperToken
{
    public function handle(Request $request, Closure $next)
    {
        // Ambil token dari header Authorization: Bearer <token>
        $token = $request->bearerToken();

        // Ambil token valid dari konfigurasi agar tetap tersedia saat config di-cache.
        $validToken = config('services.scraper.token');

        if (!$token || $token !== $validToken) {
            return response()->json([
                'message' => 'Akses ditolak! Token scraper tidak valid.'
            ], 401);
        }

        return $next($request);
    }
}