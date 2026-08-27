<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class ApiKey extends Model
{
    use SoftDeletes;

    protected $table = 'api_keys';
    protected $primaryKey = 'id_api';

    protected $fillable = [
        'id_provider',
        'name',
        'encrypted_key',
        'status',
        'priority',
        'rate_limit',
        'cooldown_until',
        'error_count',
        'request_count',
        'last_used_at',
        'last_error_at',
    ];

    protected $casts = [
        'cooldown_until' => 'datetime',
        'last_used_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'id_provider', 'id_provider');
    }

    // Accessor: Mengambil teks plaintext
    public function getDecryptedKeyAttribute(): string
    {
        $raw = $this->attributes['encrypted_key'] ?? '';
        if (empty($raw)) {
            return '';
        }

        try {
            return Crypt::decryptString($raw);
        } catch (DecryptException $e) {
            // Fallback jika data di database masih plaintext
            return $raw;
        }
    }

    // Mutator: Otomatis mengenkripsi plaintext & mencegah enkripsi ganda
    public function setEncryptedKeyAttribute(?string $value): void
    {
        if (!empty($value)) {
            $cleanValue = trim($value);
            try {
                // Cek apakah string sudah terenkripsi valid
                Crypt::decryptString($cleanValue);
                // Jika tidak melempar exception, berarti sudah terenkripsi
                $this->attributes['encrypted_key'] = $cleanValue;
            } catch (DecryptException $e) {
                // Jika bukan string terenkripsi, lakukan enkripsi baru
                $this->attributes['encrypted_key'] = Crypt::encryptString($cleanValue);
            }
        }
    }
}
