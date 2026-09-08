<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbPurchaseItemReturn extends Model
{
    protected $table = 'db_purchaseitemsreturn';

    protected $fillable = [
        'return_id', 'purchase_id', 'item_id', 'return_qty',
        'price_per_unit', 'tax_id', 'tax_amt', 'tax_type',
        'discount_input', 'discount_type', 'discount_amt',
        'unit_total_cost', 'total_cost', 'status', 'store_id'
    ];

    public function return()
    {
        return $this->belongsTo(DbPurchaseReturn::class, 'return_id');
    }

    public function item()
    {
        return $this->belongsTo(DbItem::class, 'item_id');
    }

    public function purchase()
    {
        return $this->belongsTo(DbPurchase::class, 'purchase_id');
    }
}
