<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = [
        'customer_id', 'campaign_id', 'rule_id', 'phone', 'message', 
        'message_type', 'encoding', 'sms_parts', 'cost', 'rate_per_sms', 
        'provider', 'request_id', 'batch_id', 'provider_message_id', 
        'status', 'api_response', 'error_code', 'message_hash', 
        'ip_address', 'sent_by', 'sent_at', 'delivered_at', 
        'failed_at', 'retry_count'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'api_response' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(DbCustomer::class, 'customer_id');
    }

    public function campaign()
    {
        return $this->belongsTo(SmsCampaign::class, 'campaign_id');
    }
}
