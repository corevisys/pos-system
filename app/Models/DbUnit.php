<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbUnit extends Model
{
    use HasFactory;

    protected $table = 'db_units';

    protected $fillable = [
        'store_id',
        'unit_name',
        'description',
        'company_id',
        'status',
    ];

    public function store()
    {
        return $this->belongsTo(DbStore::class, 'store_id');
    }
}
