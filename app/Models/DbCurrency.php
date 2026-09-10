<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbCurrency extends Model
{
    use HasFactory;

    protected $table = 'db_currency';

    protected $fillable = [
        'currency_name',
        'currency_code',
        'currency',
        'symbol',
        'status',
    ];

    public function store()
    {
        return $this->belongsTo(DbStore::class, 'store_id');
    }

    /**
     * Atomically activate a currency by ID and deactivate all other currencies.
     * Also updates the acting store's currency_id when a store id is available.
     *
     * The db_store write is explicitly scoped to the acting store so activating
     * a currency for Store A never rewrites Store B's currency_id.
     *
     * @param int $currencyId
     * @param int|null $storeId Acting store id (null = resolve current store)
     */
    public static function activateCurrency(int $currencyId, ?int $storeId = null): self
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($currencyId, $storeId) {
            $currency = self::findOrFail($currencyId);

            self::where('id', '!=', $currency->id)->update(['status' => 0]);
            $currency->update(['status' => 1]);

            $targetStoreId = $storeId;
            if ($targetStoreId === null && function_exists('current_store_id')) {
                $targetStoreId = current_store_id();
            }

            if ($targetStoreId) {
                DbStore::where('id', $targetStoreId)->update(['currency_id' => $currency->id]);
            } else {
                // No resolvable store: never touch every row. Fall back to the
                // first store only, preserving prior single-store behaviour.
                DbStore::query()->orderBy('id')->limit(1)->update(['currency_id' => $currency->id]);
            }

            // Bust the memoized caches so next call reflects the new active currency
            if (function_exists('store_settings')) {
                store_settings(true);
            }

            return $currency;
        });
    }
}
