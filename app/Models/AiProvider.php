<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiProvider extends Model
{
    use SoftDeletes;

    protected $table = 'ai_providers';
    protected $primaryKey = 'id_provider';

    protected $fillable = [
        'slug',
        'default_model',
    ];

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class, 'id_provider', 'id_provider');
    }
}
