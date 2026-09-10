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
                $query->where(function ($q) use ($table, $storeId) {
                    $q->where($table . '.store_id', $storeId)
                      ->orWhereNull($table . '.store_id');
                });
            }
        });
    }

    public function scopeAllStores($query)
    {
        return $query->withoutGlobalScope('store_id');
    }
}
