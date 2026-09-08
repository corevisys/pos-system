<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbCountry extends Model
{
    protected $table = 'db_country';

    protected $guarded = [];

    protected $casts = [
        'added_on' => 'datetime',
    ];
}
