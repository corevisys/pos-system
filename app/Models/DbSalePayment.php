<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbSalePayment extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_salespayments';

    protected $fillable = [
        'count_id',
        'payment_code',
        'store_id',
        'sales_id',
        'emi_schedule_id',
        'payment_date',
        'payment_type',
        'payment',
        'payment_note',
        'change_return',
        'system_ip',
        'system_name',
        'created_time',
        'created_date',
        'created_by',
        'status',
        'account_id',
        'customer_id',
        'short_code',
        'advance_adjusted',
        'cheque_number',
        'cheque_period',
        'cheque_status',
    ];

    public function sale()
    {
        return $this->belongsTo(DbSale::class, 'sales_id');
    }

    public function account()
    {
        return $this->belongsTo(AcAccount::class, 'account_id');
    }

    public function customer()
    {
        return $this->belongsTo(DbCustomer::class, 'customer_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function emiSchedule()
    {
        return $this->belongsTo(DbEmiSchedule::class, 'emi_schedule_id');
    }
}
