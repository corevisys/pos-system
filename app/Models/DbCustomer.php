<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DbCustomer extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_customers';

    protected $fillable = [
        'store_id',
        'count_id',
        'customer_code',
        'customer_name',
        'customer_type',
        'mobile',
        'phone',
        'email',
        'gstin',
        'tax_number',
        'vatin',
        'opening_balance',
        'sales_due',
        'sales_return_due',
        'country_id',
        'state_id',
        'city',
        'postcode',
        'address',
        'ship_country_id',
        'ship_state_id',
        'ship_city',
        'ship_postcode',
        'ship_address',
        'system_ip',
        'system_name',
        'created_date',
        'created_time',
        'created_by',
        'company_id',
        'status',
        'location_link',
        'attachment_1',
        'price_level_type',
        'price_level',
        'delete_bit',
        'tot_advance',
        'credit_limit',
        'shippingaddress_id',
        // Extended Fields
        'customer_id_card',
        'father_name',
        'mother_name',
        'dob',
        'mobile_primary',
        'mobile_secondary',
        'present_address',
        'permanent_address',
        'occupation',
        'monthly_income',
        'workplace_name',
        'workplace_address',
        'photo',
        'nid_front',
        'nid_back',
        'job_id_card',
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

    /**
     * Get all guardians for the customer.
     */
    public function guardians()
    {
        return $this->hasMany(CustomerGuardian::class, 'customer_id');
    }

    /**
     * Get all guarantors for the customer.
     */
    public function guarantors()
    {
        return $this->hasMany(CustomerGuarantor::class, 'customer_id');
    }

    /**
     * Sales placed by this customer (db_sales.customer_id).
     */
    public function sales()
    {
        return $this->hasMany(DbSale::class, 'customer_id');
    }

    /**
     * EMI agreements tied to this customer (db_emi_sales.customer_id).
     */
    public function emiSales()
    {
        return $this->hasMany(DbEmiSale::class, 'customer_id');
    }

    /**
     * Payments received from this customer (db_salespayments.customer_id).
     */
    public function payments()
    {
        return $this->hasMany(DbSalePayment::class, 'customer_id');
    }

    /**
     * Compatibility helper: Get the primary (first) guardian.
     */
    public function getGuardianAttribute()
    {
        return $this->guardians->first();
    }

    /**
     * Compatibility helper: Get the primary (first) guarantor.
     */
    public function getGuarantorAttribute()
    {
        return $this->guarantors->first();
    }
}
