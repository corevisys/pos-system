<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbPurchaseItem extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_purchaseitems';

    protected $fillable = [
        'store_id',
        'purchase_id',
        'purchase_status',
        'item_id',
        'purchase_qty',
        'price_per_unit',
        'tax_type',
        'tax_id',
        'tax_amt',
        'discount_type',
        'discount_input',
        'discount_amt',
        'unit_total_cost',
        'total_cost',
        'profit_margin_per',
        'unit_sales_price',
        'status',
        'description',
    ];

    public function purchase()
    {
        return $this->belongsTo(DbPurchase::class, 'purchase_id');
    }

    public function item()
    {
        return $this->belongsTo(DbItem::class, 'item_id');
    }

    public function serials()
    {
        return $this->hasMany(DbItemSerial::class, 'purchase_id', 'purchase_id')
                    ->where('item_id', $this->item_id);
    }
}
