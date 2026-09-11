<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbCoupon extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_coupons';

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
    ];

    public function customerCoupons()
    {
        return $this->hasMany(DbCustomerCoupon::class, 'coupon_id');
    }
}
