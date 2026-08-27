<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AntiAbuseMiddleware
{
    protected int $maxRequestsPerMinute = 20;
    protected int $blockDurationMinutes = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $blockKey = "blocked_ip:" . $ip;
        $rateKey = "rate_limit_ip:" . $ip;

        if (Cache::has($blockKey)) {
            return response()->json([
                'error' => 'Akses ditolak. IP Anda diblokir sementara karena aktivitas mencurigakan.'
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $requests = Cache::get($rateKey, 0) + 1;
        Cache::put($rateKey, $requests, 60);

        if ($requests > $this->maxRequestsPerMinute) {
            Cache::put($blockKey, true, $this->blockDurationMinutes * 60);
            return response()->json([
                'error' => 'Terlalu banyak permintaan. Silakan tunggu beberapa saat.'
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        return $next($request);
    }
}
