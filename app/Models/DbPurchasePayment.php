<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbPurchasePayment extends Model
{
    use HasFactory;

    protected $table = 'db_purchasepayments';

    protected $fillable = [
        'count_id',
        'payment_code',
        'store_id',
        'purchase_id',
        'payment_date',
        'payment_type',
        'payment',
        'payment_note',
        'system_ip',
        'system_name',
        'created_time',
        'created_date',
        'created_by',
        'status',
        'account_id',
        'supplier_id',
        'short_code',
    ];

    public function purchase()
    {
        return $this->belongsTo(DbPurchase::class, 'purchase_id');
    }

    public function supplier()
    {
        return $this->belongsTo(DbSupplier::class, 'supplier_id');
    }

    public function account()
    {
        return $this->belongsTo(AcAccount::class, 'account_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
