<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbCategory extends Model
{
    use HasFactory;

    protected $table = 'db_category';

    protected $fillable = [
        'category_name',
        'category_code',
        'description',
        'status',
        'store_id',
        'company_id',
        'count_id'
    ];

    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('category_name', 'like', "%{$term}%")
              ->orWhere('category_code', 'like', "%{$term}%");
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
