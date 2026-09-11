<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbSalesPaymentReturn extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_salespaymentsreturn';

    protected $fillable = [
        'count_id',
        'payment_code',
        'store_id',
        'sales_id',
        'return_id',
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
    ];

    public function sale()
    {
        return $this->belongsTo(DbSale::class, 'sales_id');
    }

    public function return()
    {
        return $this->belongsTo(DbSalesReturn::class, 'return_id');
    }

    public function account()
    {
        return $this->belongsTo(AcAccount::class, 'account_id');
    }

    public function customer()
    {
        return $this->belongsTo(DbCustomer::class, 'customer_id');
    }
}
