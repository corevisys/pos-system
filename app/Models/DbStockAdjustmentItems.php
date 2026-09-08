<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbStockAdjustmentItems extends Model
{
    use HasFactory;

    protected $table = 'db_stockadjustmentitems';

    protected $fillable = [
        'store_id',
        'warehouse_id',
        'adjustment_id',
        'item_id',
        'adjustment_qty',
        'status',
        'description',
    ];

    public function adjustment()
    {
        return $this->belongsTo(DbStockAdjustment::class, 'adjustment_id');
    }

    public function item()
    {
        return $this->belongsTo(DbItem::class, 'item_id');
    }
}
