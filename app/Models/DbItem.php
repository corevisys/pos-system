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

    /**
     * Phase 4.2 — THE canonical stock-availability rule (single source).
     *
     * Stock lives in db_warehouseitems.available_qty. db_items.stock is kept only as
     * the authoritative figure for items that have NO warehouse allocation at all
     * (legacy/opening items created without a warehouse) — it is NEVER dropped.
     *
     * Return value:
     *  - $warehouseId given and that warehouse has a row → that row's available_qty.
     *  - $warehouseId given but no row for it (nor any other) → db_items.stock fallback.
     *  - no $warehouseId (store-wide) and warehouse rows exist → SUM(available_qty).
     *  - no $warehouseId and no warehouse rows → db_items.stock fallback.
     *
     * Uses the eager-loaded relation when present so list/report loops do not trigger
     * an N+1 query.
     */
    public function availableStock(?int $warehouseId = null): float
    {
        if ($this->relationLoaded('warehouseItems')) {
            $rows = $this->warehouseItems;
            if ($warehouseId !== null) {
                $rows = $rows->where('warehouse_id', $warehouseId);
            }
            if ($rows->isEmpty()) {
                return (float) ($this->stock ?? 0);
            }

            return (float) $rows->sum('available_qty');
        }

        $query = $this->warehouseItems();
        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        $agg = $query->selectRaw('COUNT(*) as wh_count, COALESCE(SUM(available_qty), 0) as wh_total')->first();
        if (!$agg || (int) $agg->wh_count === 0) {
            return (float) ($this->stock ?? 0);
        }

        return (float) $agg->wh_total;
    }

    /**
     * Phase 4.2 — canonical aggregation for the db_items.stock figure.
     *
     * Sets db_items.stock = SUM(db_warehouseitems.available_qty) for an item that HAS
     * warehouse rows. Items with no warehouse rows are left untouched (their
     * db_items.stock is authoritative and must not be zeroed).
     */
    public static function syncGlobalStock(int $itemId): void
    {
        $aggregate = DbWarehouseItem::where('item_id', $itemId)
            ->selectRaw('COUNT(*) as wh_count, COALESCE(SUM(available_qty), 0) as wh_total')
            ->first();

        if ($aggregate && (int) $aggregate->wh_count > 0) {
            static::withoutGlobalScopes()->where('id', $itemId)->update(['stock' => (float) $aggregate->wh_total]);
        }
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
