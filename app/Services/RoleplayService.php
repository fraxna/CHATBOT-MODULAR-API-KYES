<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class RoleplayService
{
    protected string $filePath = 'roleplay.json';
    protected string $cacheKey = 'active_roleplay_prompt';
    protected string $mtimeCacheKey = 'roleplay_json_mtime';

    protected function getAbsolutePath(): string
    {
        return Storage::disk('local')->path($this->filePath);
    }

    protected function ensureFileExists(): void
    {
        if (!Storage::disk('local')->exists($this->filePath)) {
            $defaultData = [
                'roleplay' => 'Kamu adalah asisten AI resmi SMKN 6 Malang yang ramah, profesional, dan membantu siswa serta wali murid.'
            ];

            Storage::disk('local')->put($this->filePath, json_encode($defaultData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    public function getRoleplay(): string
    {
        $this->ensureFileExists();
        $fullPath = $this->getAbsolutePath();

        $currentMtime = file_exists($fullPath) ? filemtime($fullPath) : time();
        $cachedMtime = Cache::get($this->mtimeCacheKey);

        if ($cachedMtime !== $currentMtime) {
            $this->refreshCache($fullPath, $currentMtime);
        }

        return Cache::get($this->cacheKey, '');
    }

    public function saveRoleplay(string $prompt): void
    {
        $data = ['roleplay' => $prompt];
        Storage::disk('local')->put($this->filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $fullPath = $this->getAbsolutePath();
        $mtime = file_exists($fullPath) ? filemtime($fullPath) : time();

        Cache::put($this->cacheKey, $prompt);
        Cache::put($this->mtimeCacheKey, $mtime);
    }

    protected function refreshCache(string $fullPath, int $mtime): void
    {
        if (!file_exists($fullPath))
            return;

        $content = json_decode(file_get_contents($fullPath), true);
        $prompt = $content['roleplay'] ?? '';

        Cache::put($this->cacheKey, $prompt);
        Cache::put($this->mtimeCacheKey, $mtime);
    }
}
