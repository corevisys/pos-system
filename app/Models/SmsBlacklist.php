<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsBlacklist extends Model
{
    protected $fillable = ['store_id', 'phone', 'reason', 'added_by'];

    public function store()
    {
        return $this->belongsTo(DbStore::class, 'store_id');
    }
}
