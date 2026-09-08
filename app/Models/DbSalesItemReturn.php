<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbSalesItemReturn extends Model
{
    use HasFactory;

    protected $table = 'db_salesitemsreturn';

    protected $fillable = [
        'store_id',
        'sales_id',
        'return_id',
        'return_status',
        'item_id',
        'return_qty',
        'price_per_unit',
        'tax_type',
        'tax_id',
        'tax_amt',
        'discount_input',
        'discount_amt',
        'discount_type',
        'unit_total_cost',
        'total_cost',
        'status',
        'description',
        'purchase_price',
        'returned_serials',
    ];

    public function sale()
    {
        return $this->belongsTo(DbSale::class, 'sales_id');
    }

    public function return()
    {
        return $this->belongsTo(DbSalesReturn::class, 'return_id');
    }

    public function item()
    {
        return $this->belongsTo(DbItem::class, 'item_id');
    }
}
