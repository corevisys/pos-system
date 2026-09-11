<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbHoldItem extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_holditems';

    protected $fillable = [
        'store_id',
        'hold_id',
        'item_id',
        'description',
        'sales_qty',
        'price_per_unit',
        'tax_percent',
        'tax_amt',
        'is_serialized',
        'tax_type',
        'tax_id',
        'discount_type',
        'discount_input',
        'discount_amt',
        'unit_total_cost',
        'total_cost',
    ];

    public function hold()
    {
        return $this->belongsTo(DbHold::class, 'hold_id');
    }

    public function item()
    {
        return $this->belongsTo(DbItem::class, 'item_id');
    }
}
