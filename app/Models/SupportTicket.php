<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SupportTicket extends Model
{
    protected $table = 'support_tickets';

    protected $guarded = ['id'];
}
