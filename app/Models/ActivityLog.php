<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Store-level activity trail row (login / logout / store-settings changes).
 *
 * Intentionally does NOT use the `StoreScoped` trait. `StoreScoped` installs a
 * global scope that aggressively filters reads and even tolerates `store_id`
 * IS NULL — a silent, implicit behaviour that is dangerous on an audit table
 * (it can make genuine audit rows appear to vanish). Instead this model exposes
 * an explicit `scopeForStore()` so every caller opts in to store isolation on
 * purpose. A Super Admin may deliberately omit it to read across stores.
 */
class ActivityLog extends Model
{
    /**
     * A log row is immutable: it has no `updated_at` column.
     */
    public const UPDATED_AT = null;

    protected $table = 'activity_logs';

    protected $fillable = [
        'store_id',
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Explicit, opt-in store isolation (mirrors the call-site style used across
     * this codebase, e.g. WarehouseController). Pass null to leave the query
     * unscoped — used deliberately by Super Admin cross-store reads.
     */
    public function scopeForStore($query, ?int $storeId = null)
    {
        return $query->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId));
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function store()
    {
        return $this->belongsTo(DbStore::class, 'store_id');
    }
}
