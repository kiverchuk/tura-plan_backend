<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected $guarded = ['id'];

    protected $hidden = ['verified_payload'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime', 'verified_payload' => 'array'];
    }
}
