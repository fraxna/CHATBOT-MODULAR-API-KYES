<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Exception;

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

    public function getDecryptedKeyAttribute(): string
    {
        try {
            return Crypt::decryptString($this->attributes['encrypted_key'] ?? '');
        } catch (Exception $e) {
            return '';
        }
    }

    public function setEncryptedKeyAttribute(?string $value): void
    {
        if (!empty($value)) {
            $this->attributes['encrypted_key'] = Crypt::encryptString($value);
        }
    }
}
