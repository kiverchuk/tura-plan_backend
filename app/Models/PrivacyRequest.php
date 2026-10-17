<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class PrivacyRequest extends Model
{
    protected $table = 'privacy_requests';

    protected $guarded = ['id'];

    protected $hidden = ['details'];

    protected function casts(): array
    {
        return ['details' => 'array', 'verified_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
