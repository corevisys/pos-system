<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbHold extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_hold';

    protected $fillable = [
        'store_id',
        'warehouse_id',
        'reference_id',
        'reference_no',
        'sales_date',
        'sales_status',
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
        'sales_note',
        'pos',
        'status',
        'discount_on_all',
        'discount_type',
        'coupon_id',
        'customer_coupon_id',
        'coupon_code',
        'coupon_type',
        'coupon_value',
        'coupon_amount',
    ];

    public function items()
    {
        return $this->hasMany(DbHoldItem::class, 'hold_id');
    }

    public function customer()
    {
        return $this->belongsTo(DbCustomer::class, 'customer_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(DbWarehouse::class, 'warehouse_id');
    }
}
