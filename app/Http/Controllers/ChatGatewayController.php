<?php

namespace App\Http\Controllers;

use App\Services\ApiKeyManagerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatGatewayController extends Controller
{
    protected ApiKeyManagerService $keyManager;

    public function __construct(ApiKeyManagerService $keyManager)
    {
        $this->keyManager = $keyManager;
    }

    public function stream(Request $request): StreamedResponse
    {
        $request->validate([
            'query' => 'required|string',
            'knowledge' => 'nullable|array',
        ]);

        $query = $request->input('query');
        $knowledge = $request->input('knowledge', []);

        // 1. Ambil SEMUA API Key Gemini yang statusnya ready/cooldown
        $availableKeyModels = $this->keyManager->getAvailableKeys('gemini');

        if ($availableKeyModels->isEmpty()) {
            return response()->stream(function () {
                echo "data: " . json_encode(['text' => 'Maaf, layanan AI sedang tidak tersedia saat ini.']) . "\n\n";
                echo "data: [DONE]\n\n";
                if (ob_get_level() > 0)
                    ob_flush();
                flush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        // 2. Format Context Knowledge Base
        $contextText = "";
        if (!empty($knowledge)) {
            $contextText = "Gunakan referensi informasi berikut untuk menjawab:\n";
            foreach ($knowledge as $k) {
                $contextText .= "- " . ($k['title'] ?? '') . ": " . ($k['content'] ?? '') . "\n";
            }
        }

        $prompt = $contextText . "\nPertanyaan User: " . $query;

        // 3. Candidate Models dengan Gemini 3.6 Flash sebagai Prioritas Utama
        $candidateModels = [
            'gemini-3.6-flash',
            'gemini-2.0-flash',
            'gemini-1.5-flash',
            'gemini-1.5-flash-lite'
        ];

        return response()->stream(function () use ($availableKeyModels, $prompt, $candidateModels) {

            $success = false;
            $lastHttpCode = 0;
            $lastErrorMessage = '';

            // Outer Loop: Iterasi semua API Key yang tersedia
            foreach ($availableKeyModels as $apiKeyModel) {
                $rawKey = trim($this->keyManager->decryptKey($apiKeyModel));
                $keyInvalid = false;

                // Inner Loop: Iterasi fallback model untuk API Key saat ini
                foreach ($candidateModels as $model) {
                    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?alt=sse&key={$rawKey}";

                    try {
                        $response = Http::withoutVerifying()
                            ->connectTimeout(10)
                            ->timeout(45)
                            ->withHeaders([
                                'Content-Type' => 'application/json',
                            ])
                            ->post($url, [
                                'contents' => [
                                    [
                                        'parts' => [
                                            ['text' => $prompt]
                                        ]
                                    ]
                                ]
                            ]);

                        if ($response->successful()) {
                            $this->keyManager->recordSuccess($apiKeyModel);
                            echo $response->body();
                            $success = true;
                            break 2; // Berhasil! Keluar dari kedua loop (Key & Model)
                        } else {
                            $lastHttpCode = $response->status();
                            $lastErrorMessage = $response->body();

                            Log::warning("Gemini Model Error", [
                                'key_id' => $apiKeyModel->id_api,
                                'model' => $model,
                                'status' => $lastHttpCode,
                                'response' => $lastErrorMessage
                            ]);

                            // Jika error berupa Auth / Bad Request Key (400, 401, 403), tandai kunci rusak
                            if (in_array($lastHttpCode, [400, 401, 403], true)) {
                                $keyInvalid = true;
                                break; // Out dari loop model, lanjut ke API Key berikutnya
                            }
                        }
                    } catch (\Throwable $e) {
                        $lastHttpCode = 0;
                        $lastErrorMessage = $e->getMessage();
                        Log::error("Gemini Connection Error", [
                            'key_id' => $apiKeyModel->id_api,
                            'model' => $model,
                            'error' => $lastErrorMessage
                        ]);
                    }
                }

                // Terapkan penanganan kegagalan untuk API Key saat ini
                $this->keyManager->handleKeyFailure($apiKeyModel, $lastHttpCode, $lastErrorMessage);

                // Jika key teridentifikasi invalid/disabled, pindah ke iteration key berikutnya
                if ($keyInvalid) {
                    continue;
                }
            }

            // Jika seluruh API Key dan Model gagal diakses
            if (!$success) {
                $pesanError = ($lastHttpCode === 0)
                    ? "Gagal terhubung ke Google AI (Timeout/Network Error: {$lastErrorMessage})."
                    : "Server AI menolak permintaan (HTTP {$lastHttpCode}).";

                echo "data: " . json_encode(['text' => "\n[{$pesanError}]"]) . "\n\n";
            }

            echo "data: [DONE]\n\n";
            if (ob_get_level() > 0)
                ob_flush();
            flush();

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
