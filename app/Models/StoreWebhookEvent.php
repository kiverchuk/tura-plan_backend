<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class StoreWebhookEvent extends Model
{
    protected $table = 'store_webhook_events';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'processed_at' => 'datetime'];
    }
}
