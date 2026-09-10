<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbLanguage extends Model
{
    use HasFactory;

    protected $table = 'db_languages';

    protected $fillable = [
        'language',
        'status',
    ];

    /**
     * Atomically activates a single language, deactivates all other languages,
     * and synchronizes the acting store's language_id foreign key.
     *
     * The db_store write is explicitly scoped to the acting store so activating
     * a language for Store A never rewrites Store B's language_id.
     *
     * @param int $languageId
     * @param int|null $storeId Acting store id (null = resolve current store)
     * @return self
     */
    public static function activateLanguage(int $languageId, ?int $storeId = null): self
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($languageId, $storeId) {
            $language = self::findOrFail($languageId);

            // Deactivate all other languages
            self::query()->where('id', '!=', $languageId)->update(['status' => 0]);

            // Activate the selected language
            $language->update(['status' => 1]);

            $targetStoreId = $storeId;
            if ($targetStoreId === null && function_exists('current_store_id')) {
                $targetStoreId = current_store_id();
            }

            // Synchronize the acting store's language setting (scoped, not all rows)
            if ($targetStoreId) {
                DbStore::where('id', $targetStoreId)->update(['language_id' => $language->id]);
            } else {
                // No resolvable store: never touch every row. Fall back to the
                // first store only, preserving prior single-store behaviour.
                DbStore::query()->orderBy('id')->limit(1)->update(['language_id' => $language->id]);
            }

            return $language;
        });
    }
}
