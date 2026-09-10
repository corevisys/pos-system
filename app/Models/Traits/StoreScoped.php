<?php

namespace App\Models\Traits;

trait StoreScoped
{
    public static function bootStoreScoped()
    {
        static::addGlobalScope('store_id', function ($query) {
            if (function_exists('current_store_id') && auth()->check()) {
                $storeId = current_store_id();
                $query->where($query->getModel()->getTable() . '.store_id', $storeId);
            }
        });
    }

    public function scopeAllStores($query)
    {
        return $query->withoutGlobalScope('store_id');
    }
}
