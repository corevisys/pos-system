<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbPurchasePaymentReturn extends Model
{
    protected $table = 'db_purchasepaymentsreturn';

    protected $fillable = [
        'purchase_id', 'return_id', 'payment_date', 'payment_type', 'payment',
        'payment_note', 'created_by', 'status', 'store_id', 'account_id'
    ];

    public function return()
    {
        return $this->belongsTo(DbPurchaseReturn::class, 'return_id');
    }

    public function account()
    {
        return $this->belongsTo(AcAccount::class, 'account_id');
    }
}
