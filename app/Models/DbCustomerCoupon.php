<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbCustomerCoupon extends Model
{
    use HasFactory;

    protected $table = 'db_customer_coupons';

    protected $fillable = [
        'store_id',
        'code',
        'name',
        'description',
        'value',
        'type',
        'expire_date',
        'status',
        'created_by',
        'created_date',
        'created_time',
        'system_name',
        'system_ip',
        'customer_id',
        'coupon_id',
    ];

    public function customer()
    {
        return $this->belongsTo(DbCustomer::class, 'customer_id');
    }

    public function coupon()
    {
        return $this->belongsTo(DbCoupon::class, 'coupon_id');
    }
}
