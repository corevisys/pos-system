<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbState extends Model
{
    protected $table = 'db_states';

    protected $guarded = [];

    public function country()
    {
        return $this->belongsTo(DbCountry::class, 'country_id');
    }
}
