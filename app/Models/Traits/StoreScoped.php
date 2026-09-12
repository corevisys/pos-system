<?php

namespace App\Models\Traits;

trait StoreScoped
{
    public static function bootStoreScoped()
    {
        static::addGlobalScope('store_id', function ($query) {
            if (function_exists('current_store_id') && auth()->check()) {
                $storeId = current_store_id();
                $table = $query->getModel()->getTable();

                // Strictly store-scoped — NO orWhereNull branch.
                //
                // The old `store_id IS NULL` escape hatch meant any legacy null row was
                // visible to EVERY store. That is now closed: every store-scoped table
                // has been migrated to NOT NULL store_id
                // (2026_09_11_000001_add_not_null_store_id_to_store_scoped_tables), and
                // this was verified live against the production schema (0 NULL rows
                // across all affected tables before this change), so dropping the
                // NULL branch cannot hide any legitimate row.
                $query->where($table . '.store_id', $storeId);
            }
        });
    }

    public function scopeAllStores($query)
    {
        return $query->withoutGlobalScope('store_id');
    }
}
