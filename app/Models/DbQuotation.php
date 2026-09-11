<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbQuotation extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_quotation';

    protected $fillable = [
        'store_id',
        'warehouse_id',
        'count_id',
        'quotation_code',
        'reference_no',
        'quotation_date',
        'expire_date',
        'quotation_status',
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
        'quotation_note',
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
        'sales_status',
    ];

    public function warehouse()
    {
        return $this->belongsTo(DbWarehouse::class, 'warehouse_id');
    }

    public function customer()
    {
        return $this->belongsTo(DbCustomer::class, 'customer_id');
    }

    public function items()
    {
        return $this->hasMany(DbQuotationItem::class, 'quotation_id');
    }

    public function sale()
    {
        return $this->hasOne(DbSale::class, 'quotation_id');
    }

    public function getEffectiveStatusAttribute(): string
    {
        if ($this->quotation_status === 'Converted') {
            return 'Converted';
        }

        if (!empty($this->expire_date) && $this->expire_date < date('Y-m-d')) {
            return 'Expired';
        }

        return $this->quotation_status ?: 'Quoted';
    }

    public function isExpired(): bool
    {
        return $this->quotation_status !== 'Converted' && !empty($this->expire_date) && $this->expire_date < date('Y-m-d');
    }

    public function isConverted(): bool
    {
        return $this->quotation_status === 'Converted';
    }
}
