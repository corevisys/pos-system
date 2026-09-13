<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\StoreScoped;

class SmsAutoRule extends Model
{
    use SoftDeletes;
    use StoreScoped;

    protected $fillable = [
        'store_id', 'rule_name', 'event_type', 'event_source', 'template_id',
        'trigger_time', 'days_offset', 'cooldown_days',
        'is_active', 'last_executed_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_executed_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(DbSmsTemplate::class, 'template_id');
    }
}
