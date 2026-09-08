<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmsAutoRule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'rule_name', 'event_type', 'event_source', 'template_id', 
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
