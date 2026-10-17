<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class AdminAuditLog extends Model
{
    protected $table = 'admin_audit_logs';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }
}
