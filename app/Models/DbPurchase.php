<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbPurchase extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_purchase';

    protected $fillable = [
        'store_id',
        'warehouse_id',
        'count_id',
        'purchase_code',
        'reference_no',
        'purchase_date',
        'purchase_status',
        'supplier_id',
        'other_charges_input',
        'other_charges_tax_id',
        'other_charges_amt',
        'discount_to_all_input',
        'discount_to_all_type',
        'tot_discount_to_all_amt',
        'subtotal',
        'round_off',
        'grand_total',
        'purchase_note',
        'payment_status',
        'paid_amount',
        'created_date',
        'created_time',
        'created_by',
        'system_ip',
        'system_name',
        'company_id',
        'status',
        'return_bit',
    ];

    public function warehouse()
    {
        return $this->belongsTo(DbWarehouse::class, 'warehouse_id');
    }

    public function supplier()
    {
        return $this->belongsTo(DbSupplier::class, 'supplier_id');
    }

    public function items()
    {
        return $this->hasMany(DbPurchaseItem::class, 'purchase_id');
    }

    public function payments()
    {
        return $this->hasMany(DbPurchasePayment::class, 'purchase_id');
    }

    public function returns()
    {
        return $this->hasMany(DbPurchaseReturn::class, 'purchase_id');
    }

    public function warehouseStore()
    {
        return $this->belongsTo(DbWarehouse::class, 'warehouse_id');
    }
}
