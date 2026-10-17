<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Device extends Model
{
    protected $table = 'devices';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
