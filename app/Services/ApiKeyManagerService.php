<?php

namespace App\Services;

use App\Models\ApiKey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApiKeyManagerService
{
    protected bool $debug = true;

    /**
     * Get 1 ready API Key based on provider slug
     */
    public function getAvailableKey(?string $providerSlug = null): ?ApiKey
    {
        $keys = $this->getAvailableKeys($providerSlug);
        return $keys->first();
    }

    /**
     * Get all available keys ordered by priority & status
     */
    public function getAvailableKeys(?string $providerSlug = null): Collection
    {
        try {
            $this->resetExpiredCooldowns();

            $query = ApiKey::query()
                ->whereIn('status', ['ready', 'cooldown'])
                ->whereHas('provider', function ($query) use ($providerSlug) {
                    $query->whereNull('deleted_at');
                    if ($providerSlug !== null) {
                        $query->where('slug', $providerSlug);
                    }
                })
                ->with('provider')
                ->orderByRaw("CASE WHEN status = 'ready' THEN 1 ELSE 2 END")
                ->orderBy('priority', 'asc')
                ->orderBy('request_count', 'asc')
                ->orderBy('id_api', 'asc');

            return $query->get();
        } catch (Throwable $e) {
            Log::error('🔥 getAvailableKeys() EXCEPTION', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    /**
     * Reset cooldown keys that are expired back to ready status
     */
    public function resetExpiredCooldowns(): void
    {
        $now = Carbon::now();

        try {
            ApiKey::query()
                ->where('status', 'cooldown')
                ->whereNotNull('cooldown_until')
                ->where('cooldown_until', '<=', $now)
                ->update([
                    'status' => 'ready',
                    'cooldown_until' => null,
                    'error_count' => 0,
                ]);
        } catch (Throwable $e) {
            Log::error('🔥 resetExpiredCooldowns() EXCEPTION', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Decrypt encrypted_key column (Smart Auto-Detect Plaintext vs Encrypted)
     */
    public function decryptKey(ApiKey $key): ?string
    {
        try {
            $raw = trim((string) $key->encrypted_key);

            if (empty($raw)) {
                return null;
            }

            // Safe Check: Jika key terlanjur dimasukkan sebagai Plaintext Google Gemini ('AIza...')
            if (str_starts_with($raw, 'AIza')) {
                return $raw;
            }

            // Dekripsi menggunakan Crypt Facade Laravel
            $decrypted = trim(Crypt::decryptString($raw));
            return $decrypted;
        } catch (Throwable $e) {
            // Fallback: Jika gagal dekripsi tetapi string berisi data raw
            Log::warning('⚠️ decryptKey() FAILED, fallback to raw string', [
                'api_key_id' => $key->id_api,
                'message' => $e->getMessage()
            ]);

            return trim((string) $key->encrypted_key);
        }
    }

    /**
     * Record successful API request
     */
    public function recordSuccess(ApiKey $key): void
    {
        $key->increment('request_count');
        $key->update([
            'last_used_at' => Carbon::now(),
            'error_count' => 0,
            'last_error_at' => null,
            'cooldown_until' => null,
            'status' => 'ready',
        ]);
    }

    /**
     * Handle key failure with automatic cooldown/disable policy
     */
    public function handleKeyFailure(ApiKey $key, int $httpCode, ?string $errorMessage = null): void
    {
        DB::transaction(function () use ($key, $httpCode, $errorMessage) {
            $key->refresh();
            $newErrorCount = ((int) $key->error_count) + 1;
            $now = Carbon::now();

            // Auth Error / Bad Request Key (400, 401, 403) -> Langsung Disable
            if (in_array($httpCode, [400, 401, 403], true)) {
                $key->update([
                    'status' => 'disabled',
                    'error_count' => $newErrorCount,
                    'cooldown_until' => null,
                    'last_error_at' => $now,
                ]);

                Log::error("🚫 API Key Disabled (Invalid / Auth Error)", [
                    'id_api' => $key->id_api,
                    'http_code' => $httpCode,
                    'error' => $errorMessage
                ]);
                return;
            }

            // Rate Limit (429) -> Cooldown 45 Detik / Disable jika >= 5x Error
            if ($httpCode === 429) {
                $shouldDisable = $newErrorCount >= 5;
                $key->update([
                    'status' => $shouldDisable ? 'disabled' : 'cooldown',
                    'error_count' => $newErrorCount,
                    'cooldown_until' => $shouldDisable ? null : $now->copy()->addSeconds(45),
                    'last_error_at' => $now,
                ]);
                return;
            }

            // Server / Network Error (5xx / HTTP 0) -> Cooldown 30 Detik / Disable jika >= 5x Error
            if ($httpCode === 0 || $httpCode >= 500) {
                $shouldDisable = $newErrorCount >= 5;
                $key->update([
                    'status' => $shouldDisable ? 'disabled' : 'cooldown',
                    'error_count' => $newErrorCount,
                    'cooldown_until' => $shouldDisable ? null : $now->copy()->addSeconds(30),
                    'last_error_at' => $now,
                ]);
                return;
            }

            // Client Error Lainnya
            $key->update([
                'error_count' => $newErrorCount,
                'last_error_at' => $now,
            ]);
        });
    }
}
