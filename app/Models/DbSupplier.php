<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DbSupplier extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_suppliers';

    protected $fillable = [
        'store_id',
        'count_id',
        'supplier_code',
        'supplier_name',
        'mobile',
        'phone',
        'email',
        'gstin',
        'tax_number',
        'vatin',
        'opening_balance',
        'purchase_due',
        'purchase_return_due',
        'country_id',
        'state_id',
        'city',
        'postcode',
        'address',
        'system_ip',
        'system_name',
        'created_date',
        'created_time',
        'created_by',
        'company_id',
        'status',
        'delete_bit', // Added for consistency with Customer
        'location_link', // Standard contact field
        'attachment_1', // Standard contact field
    ];

    public function store()
    {
        return $this->belongsTo(DbStore::class, 'store_id');
    }

    public function country()
    {
        return $this->belongsTo(DbCountry::class, 'country_id');
    }

    public function state()
    {
        return $this->belongsTo(DbState::class, 'state_id');
    }

    public function purchases()
    {
        return $this->hasMany(DbPurchase::class, 'supplier_id');
    }

    public function purchasePayments()
    {
        return $this->hasMany(DbPurchasePayment::class, 'supplier_id');
    }

    public function purchaseReturns()
    {
        return $this->hasMany(DbPurchaseReturn::class, 'supplier_id');
    }

    public function transactions()
    {
        return $this->hasMany(AcTransaction::class, 'supplier_id');
    }
}
