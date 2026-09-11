<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbBrand extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_brands';

    protected $fillable = [
        'store_id',
        'brand_code',
        'brand_name',
        'description',
        'status',
    ];

    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('brand_name', 'like', "%{$term}%")
              ->orWhere('brand_code', 'like', "%{$term}%");
        });
    }

    public function scopeFilterStatus($query, $status)
    {
        return $query->when($status !== null && $status !== '', fn($q) => $q->where('status', $status));
    }
}
