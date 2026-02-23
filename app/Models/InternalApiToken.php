<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalApiToken extends Model
{
    protected $table = 'internal_api_tokens';

    protected $guarded = ['id'];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];
}
