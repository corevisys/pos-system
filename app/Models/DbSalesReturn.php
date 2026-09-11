<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbSalesReturn extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_salesreturn';

    protected $fillable = [
        'store_id',
        'count_id',
        'sales_id',
        'warehouse_id',
        'return_code',
        'reference_no',
        'return_date',
        'return_status',
        'customer_id',
        'other_charges_input',
        'other_charges_tax_id',
        'other_charges_amt',
        'discount_to_all_input',
        'discount_to_all_type',
        'tot_discount_to_all_amt',
        'subtotal',
        'round_off',
        'grand_total',
        'return_note',
        'payment_status',
        'paid_amount',
        'created_date',
        'created_time',
        'created_by',
        'system_ip',
        'system_name',
        'company_id',
        'pos',
        'status',
        'return_bit',
        'coupon_id',
        'coupon_amt',
    ];

    public function sale()
    {
        return $this->belongsTo(DbSale::class, 'sales_id');
    }

    public function items()
    {
        return $this->hasMany(DbSalesItemReturn::class, 'return_id');
    }

    public function payments()
    {
        return $this->hasMany(DbSalesPaymentReturn::class, 'return_id');
    }

    public function customer()
    {
        return $this->belongsTo(DbCustomer::class, 'customer_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(DbWarehouse::class, 'warehouse_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
