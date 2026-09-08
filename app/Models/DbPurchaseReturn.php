<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbPurchaseReturn extends Model
{
    protected $table = 'db_purchasereturn';

    protected $fillable = [
        'purchase_id', 'return_code', 'return_date', 'return_status',
        'reference_no', 'supplier_id', 'warehouse_id', 'other_charges_input',
        'other_charges_tax_id', 'other_charges_amt', 'discount_to_all_input',
        'discount_to_all_type', 'tot_discount_to_all_amt', 'subtotal',
        'round_off', 'grand_total', 'return_note', 'payment_status',
        'paid_amount', 'created_by', 'store_id'
    ];

    public function purchase()
    {
        return $this->belongsTo(DbPurchase::class, 'purchase_id');
    }

    public function supplier()
    {
        return $this->belongsTo(DbSupplier::class, 'supplier_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(DbWarehouse::class, 'warehouse_id');
    }

    public function items()
    {
        return $this->hasMany(DbPurchaseItemReturn::class, 'return_id');
    }
}
