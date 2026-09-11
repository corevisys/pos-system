<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbStockTransfer extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_stocktransfer';

    protected $fillable = [
        'store_id',
        'to_store_id',
        'reference_no',
        'warehouse_from',
        'warehouse_to',
        'transfer_date',
        'note',
        'created_by',
        'created_date',
        'created_time',
        'system_ip',
        'system_name',
        'status',
        'delete_bit'
    ];

    public function items()
    {
        return $this->hasMany(DbStockTransferItems::class, 'stocktransfer_id');
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(DbWarehouse::class, 'warehouse_from');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(DbWarehouse::class, 'warehouse_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
