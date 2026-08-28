<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\ChatGatewayController;
use App\Http\Middleware\AntiAbuseMiddleware;
use Illuminate\Support\Facades\Http;
use App\Services\ApiKeyManagerService;

// Endpoints Knowledge Base
Route::get('/knowledge/version', [KnowledgeController::class, 'version'])->name('knowledge.version');
Route::get('/knowledge/download', [KnowledgeController::class, 'download'])->name('knowledge.download');

// Endpoint Streaming AI Gateway dengan Proteksi Security
Route::middleware([AntiAbuseMiddleware::class])->group(function () {
    Route::post('/chat/stream', [ChatGatewayController::class, 'stream'])->name('chat.stream');
});

// Route Debugging Gemini API
Route::get('/debug-gemini', function (ApiKeyManagerService $keyManager) {
    $apiKeyModel = $keyManager->getAvailableKey('gemini');

    if (!$apiKeyModel) {
        return response()->json(['error' => 'Tidak ada API Key berstatus ready di DB']);
    }

    $rawKey = trim($keyManager->decryptKey($apiKeyModel));
    $isGoogleFormat = str_starts_with($rawKey, 'AIza');

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=" . $rawKey;

    try {
        $response = Http::withoutVerifying()
            ->connectTimeout(10)
            ->timeout(30)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => 'ping']
                        ]
                    ]
                ]
            ]);

        return response()->json([
            'api_key_id' => $apiKeyModel->id_api,
            'starts_with_AIza' => $isGoogleFormat,
            'raw_key_length' => strlen($rawKey),
            'http_status' => $response->status(),
            'response_body' => $response->json() ?? $response->body(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'api_key_id' => $apiKeyModel->id_api,
            'starts_with_AIza' => $isGoogleFormat,
            'curl_error' => $e->getMessage(),
        ]);
    }
});
