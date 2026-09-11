<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbItemSerial extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_item_serials';

    protected $fillable = [
        'store_id',
        'purchase_id',
        'item_id',
        'serial_number',
        'status',
        'source',
        'sale_id',
        'adjustment_id',
        'stocktransfer_id',
        'warehouse_id',
        'created_by'
    ];

    public function item()
    {
        return $this->belongsTo(DbItem::class, 'item_id');
    }

    public function store()
    {
        return $this->belongsTo(DbStore::class, 'store_id');
    }

    public function sale()
    {
        return $this->belongsTo(DbSale::class, 'sale_id');
    }
}
