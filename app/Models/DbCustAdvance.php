<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbCustAdvance extends Model
{
    use HasFactory;

    protected $table = 'db_custadvance';

    protected $fillable = [
        'store_id',
        'count_id',
        'payment_code',
        'payment_date',
        'customer_id',
        'amount',
        'payment_type',
        'account_id',
        'note',
        'created_by',
        'created_date',
        'created_time',
        'system_ip',
        'system_name',
        'status',
    ];

    public function customer()
    {
        return $this->belongsTo(DbCustomer::class, 'customer_id');
    }
}
