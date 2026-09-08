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
     * and synchronizes the db_store.language_id foreign key inside a database transaction.
     *
     * @param int $languageId
     * @return self
     */
    public static function activateLanguage(int $languageId): self
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($languageId) {
            $language = self::findOrFail($languageId);

            // Deactivate all other languages
            self::query()->where('id', '!=', $languageId)->update(['status' => 0]);

            // Activate the selected language
            $language->update(['status' => 1]);

            // Synchronize the single active store setting
            DbStore::query()->update(['language_id' => $language->id]);

            return $language;
        });
    }
}
