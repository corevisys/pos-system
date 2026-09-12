<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsDailyStat extends Model
{
    protected $fillable = ['store_id', 'date', 'total_sent', 'total_delivered', 'total_failed', 'total_cost'];

    protected $casts = [
        'date' => 'date',
    ];
}
