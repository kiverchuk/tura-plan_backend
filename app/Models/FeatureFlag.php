<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class FeatureFlag extends Model
{
    protected $table = 'feature_flags';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'config' => 'array'];
    }
}
