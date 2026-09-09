<?php

namespace App\Http\Controllers;

use App\Services\ApiKeyManagerService;
use App\Services\RoleplayService; // 1. Import RoleplayService
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatGatewayController extends Controller
{
    protected ApiKeyManagerService $keyManager;
    protected RoleplayService $roleplayService; // 2. Tambahkan properti

    // 3. Inject RoleplayService via Constructor
    public function __construct(ApiKeyManagerService $keyManager, RoleplayService $roleplayService)
    {
        $this->keyManager = $keyManager;
        $this->roleplayService = $roleplayService;
    }

    public function stream(Request $request): StreamedResponse
    {
        $request->validate([
            'query' => 'required|string',
            'knowledge' => 'nullable|array',
        ]);

        $query = $request->input('query');
        $knowledge = $request->input('knowledge', []);

        // 4. Ambil prompt roleplay aktif
        $systemRoleplay = $this->roleplayService->getRoleplay();

        // Ambil SEMUA API Key Gemini yang statusnya ready/cooldown
        $availableKeyModels = $this->keyManager->getAvailableKeys('gemini');

        if ($availableKeyModels->isEmpty()) {
            return response()->stream(function () {
                echo "data: " . json_encode(['candidates' => [['content' => ['parts' => [['text' => 'Maaf, layanan AI sedang tidak tersedia saat ini.']]]]]]) . "\n\n";
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

        // Format Context Knowledge Base
        $contextText = "";
        if (!empty($knowledge)) {
            $contextText = "Gunakan referensi informasi berikut untuk menjawab:\n";
            foreach ($knowledge as $k) {
                $contextText .= "- " . ($k['title'] ?? '') . ": " . ($k['content'] ?? '') . "\n";
            }
        }

        $prompt = $contextText . "\nPertanyaan User: " . $query;

        // Candidate Models dengan Gemini 3.6 Flash sebagai Prioritas Utama
        $candidateModels = [
            'gemini-3.6-flash',
            'gemini-2.0-flash',
            'gemini-1.5-flash',
            'gemini-1.5-flash-lite'
        ];

        return response()->stream(function () use ($availableKeyModels, $prompt, $candidateModels, $systemRoleplay) {

            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }
            @ini_set('zlib.output_compression', 'Off');
            @ini_set('implicit_flush', '1');
            ob_implicit_flush(true);

            $success = false;
            $lastHttpCode = 0;
            $lastErrorMessage = '';

            foreach ($availableKeyModels as $apiKeyModel) {
                $rawKey = trim($this->keyManager->decryptKey($apiKeyModel));
                $keyInvalid = false;

                foreach ($candidateModels as $model) {
                    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?alt=sse&key={$rawKey}";

                    // 5. Susun Payload dengan system_instruction
                    $payloadData = [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt]
                                ]
                            ]
                        ]
                    ];

                    // Sisipkan system_instruction jika roleplay tidak kosong
                    if (!empty($systemRoleplay)) {
                        $payloadData['system_instruction'] = [
                            'parts' => [
                                ['text' => $systemRoleplay]
                            ]
                        ];
                    }

                    $payload = json_encode($payloadData);

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                    ]);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

                    $httpCode = 200;
                    $streamBuffer = '';

                    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$streamBuffer, &$httpCode) {
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        if ($httpCode >= 200 && $httpCode < 300) {
                            echo $chunk;
                            if (ob_get_level() > 0)
                                ob_flush();
                            flush();
                        } else {
                            $streamBuffer .= $chunk;
                        }
                        return strlen($chunk);
                    });

                    curl_exec($ch);
                    $lastHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);

                    if ($lastHttpCode >= 200 && $lastHttpCode < 300) {
                        $this->keyManager->recordSuccess($apiKeyModel);
                        $success = true;
                        break 2;
                    } else {
                        $lastErrorMessage = $streamBuffer ?: $curlError;

                        Log::warning("Gemini Model Error", [
                            'key_id' => $apiKeyModel->id_api,
                            'model' => $model,
                            'status' => $lastHttpCode,
                            'response' => $lastErrorMessage
                        ]);

                        if (in_array($lastHttpCode, [400, 401, 403], true)) {
                            $keyInvalid = true;
                            break;
                        }
                    }
                }

                $this->keyManager->handleKeyFailure($apiKeyModel, $lastHttpCode, $lastErrorMessage);

                if ($keyInvalid) {
                    continue;
                }
            }

            if (!$success) {
                $pesanError = ($lastHttpCode === 0)
                    ? "Gagal terhubung ke Google AI (Timeout/Network Error: {$lastErrorMessage})."
                    : "Server AI menolak permintaan (HTTP {$lastHttpCode}).";

                echo "data: " . json_encode([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => "\n[{$pesanError}]"]
                                ]
                            ]
                        ]
                    ]
                ]) . "\n\n";
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
