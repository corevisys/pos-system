<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbStockAdjustment extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_stockadjustment';

    protected $fillable = [
        'store_id',
        'warehouse_id',
        'reference_no',
        'adjustment_date',
        'adjustment_note',
        'created_date',
        'created_time',
        'created_by',
        'system_ip',
        'system_name',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(DbStockAdjustmentItems::class, 'adjustment_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(DbWarehouse::class, 'warehouse_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
