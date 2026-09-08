<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmsCampaign extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'template_id', 'target_type', 'target_filters', 
        'total_recipients', 'total_sent', 'total_delivered', 
        'total_failed', 'estimated_cost', 'status', 
        'scheduled_at', 'started_at', 'completed_at', 'created_by'
    ];

    protected $casts = [
        'target_filters' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(DbSmsTemplate::class, 'template_id');
    }

    public function logs()
    {
        return $this->hasMany(SmsLog::class, 'campaign_id');
    }
}
