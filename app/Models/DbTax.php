<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbTax extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_tax';

    protected $fillable = [
        'store_id',
        'tax_name',
        'tax',
        'group_bit',
        'subtax_ids',
        'status',
    ];

    /**
     * Get the store that owns the tax.
     */
    public function store()
    {
        return $this->belongsTo(DbStore::class, 'store_id');
    }
}
