<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbItem extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_items';

    protected $fillable = [
        'store_id',
        'count_id',
        'item_code',
        'item_name',
        'is_serialized',
        'category_id',
        'sku',
        'hsn',
        'unit_id',
        'alert_qty',
        'brand_id',
        'lot_number',
        'expire_date',
        'price',
        'tax_id',
        'purchase_price',
        'tax_type',
        'profit_margin',
        'sales_price',
        'stock',
        'item_image',
        'system_ip',
        'system_name',
        'created_date',
        'created_time',
        'created_by',
        'company_id',
        'status',
        'discount_type',
        'discount',
        'service_bit',
        'seller_points',
        'custom_barcode',
        'description',
        'item_group',
        'parent_id',
        'variant_id',
        'child_bit',
        'mrp'
    ];

    /**
     * Get the store that owns the item.
     */
    public function store()
    {
        return $this->belongsTo(DbStore::class, 'store_id');
    }

    public function category()
    {
        return $this->belongsTo(DbCategory::class, 'category_id');
    }

    public function brand()
    {
        return $this->belongsTo(DbBrand::class, 'brand_id');
    }

    public function unit()
    {
        return $this->belongsTo(DbUnit::class, 'unit_id');
    }

    public function tax()
    {
        return $this->belongsTo(DbTax::class, 'tax_id');
    }

    public function variant()
    {
        return $this->belongsTo(DbVariant::class, 'variant_id');
    }

    /**
     * Get the warehouse items associated with this item.
     */
    public function warehouseItems()
    {
        return $this->hasMany(DbWarehouseItem::class, 'item_id');
    }

    public function variants()
    {
        return $this->hasMany(DbItem::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(DbItem::class, 'parent_id');
    }

    public function serials()
    {
        return $this->hasMany(DbItemSerial::class, 'item_id');
    }
}
