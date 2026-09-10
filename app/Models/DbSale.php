<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbSale extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_sales';

    protected $fillable = [
        'store_id',
        'warehouse_id',
        'init_code',
        'count_id',
        'sales_code',
        'reference_no',
        'sales_date',
        'due_date',
        'sales_status',
        'customer_id',
        'other_charges_input',
        'other_charges_tax_id',
        'other_charges_amt',
        'discount_to_all_input',
        'discount_to_all_type',
        'tot_discount_to_all_amt',
        'subtotal',
        'round_off',
        'grand_total',
        'sales_note',
        'payment_status',
        'paid_amount',
        'created_date',
        'created_time',
        'created_by',
        'system_ip',
        'system_name',
        'company_id',
        'pos',
        'status',
        'return_bit',
        'customer_previous_due',
        'customer_total_due',
        'quotation_id',
        'coupon_id',
        'coupon_amt',
        'invoice_terms',
    ];

    public function items()
    {
        return $this->hasMany(DbSaleItem::class, 'sales_id');
    }

    public function payments()
    {
        return $this->hasMany(DbSalePayment::class, 'sales_id');
    }

    public function customer()
    {
        return $this->belongsTo(DbCustomer::class, 'customer_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(DbWarehouse::class, 'warehouse_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function serials()
    {
        return $this->hasMany(DbItemSerial::class, 'sale_id');
    }

    public function emi()
    {
        return $this->hasOne(DbEmiSale::class, 'sale_id');
    }

    public function returns()
    {
        return $this->hasMany(DbSalesReturn::class, 'sales_id')->orderBy('id', 'desc');
    }

    public function returnItems()
    {
        return $this->hasMany(DbSalesItemReturn::class, 'sales_id');
    }

    public function returnPayments()
    {
        return $this->hasMany(DbSalesPaymentReturn::class, 'sales_id');
    }

    public function store()
    {
        return $this->belongsTo(DbStore::class, 'store_id');
    }

    public function coupon()
    {
        return $this->belongsTo(DbCoupon::class, 'coupon_id');
    }

    public function quotation()
    {
        return $this->belongsTo(DbQuotation::class, 'quotation_id');
    }
}
