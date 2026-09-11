<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbQuotationItem extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_quotationitems';

    protected $fillable = [
        'store_id',
        'quotation_id',
        'quotation_status',
        'item_id',
        'description',
        'quotation_qty',
        'price_per_unit',
        'tax_type',
        'tax_id',
        'tax_amt',
        'discount_type',
        'discount_input',
        'discount_amt',
        'unit_total_cost',
        'total_cost',
        'status',
        'seller_points',
    ];

    public function quotation()
    {
        return $this->belongsTo(DbQuotation::class, 'quotation_id');
    }

    public function item()
    {
        return $this->belongsTo(DbItem::class, 'item_id');
    }
}
