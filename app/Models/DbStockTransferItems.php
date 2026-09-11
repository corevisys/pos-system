<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbStockTransferItems extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_stocktransferitems';

    protected $fillable = [
        'stocktransfer_id',
        'store_id',
        'to_store_id',
        'warehouse_from',
        'warehouse_to',
        'item_id',
        'transfer_qty',
        'status'
    ];

    public function transfer()
    {
        return $this->belongsTo(DbStockTransfer::class, 'stocktransfer_id');
    }

    public function item()
    {
        return $this->belongsTo(DbItem::class, 'item_id');
    }
}
