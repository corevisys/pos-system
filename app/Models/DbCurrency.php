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
     * Also updates the primary store's currency_id.
     */
    public static function activateCurrency(int $currencyId): self
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($currencyId) {
            $currency = self::findOrFail($currencyId);

            self::where('id', '!=', $currency->id)->update(['status' => 0]);
            $currency->update(['status' => 1]);
            DbStore::query()->update(['currency_id' => $currency->id]);

            // Bust both memoized caches so next call reflects the new active currency
            if (function_exists('store_settings')) {
                store_settings(true);
            }

            return $currency;
        });
    }
}
