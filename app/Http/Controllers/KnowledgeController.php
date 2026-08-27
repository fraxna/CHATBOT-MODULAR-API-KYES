<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class KnowledgeController extends Controller
{
    protected string $fileName = 'knowledge.json';

    public function version()
    {
        if (!Storage::disk('public')->exists($this->fileName)) {
            return response()->json(['version' => '0'], 404);
        }

        $fullPath = Storage::disk('public')->path($this->fileName);
        $version = filemtime($fullPath);

        return response()->json(['version' => (string) $version]);
    }

    public function download()
    {
        if (!Storage::disk('public')->exists($this->fileName)) {
            return response()->json(['message' => 'File knowledge.json tidak ditemukan.'], 404);
        }

        $fullPath = Storage::disk('public')->path($this->fileName);

        return response()->file($fullPath, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
