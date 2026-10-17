<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class HolidayPack extends Model
{
    protected $table = 'holiday_packs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'verified_at' => 'date', 'published' => 'boolean'];
    }
}
