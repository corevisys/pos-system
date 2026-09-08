<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbVariant extends Model
{
    use HasFactory;

    protected $table = 'db_variants';

    protected $fillable = [
        'variant_name',
        'variant_code',
        'description',
        'status',
        'store_id'
    ];

    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('variant_name', 'like', "%{$term}%")
              ->orWhere('variant_code', 'like', "%{$term}%");
        });
    }

    public function scopeFilterStatus($query, $status)
    {
        if ($status !== null && $status !== '') {
            return $query->where('status', $status);
        }
        return $query;
    }
}
