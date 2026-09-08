<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbFivemojo extends Model
{
    use HasFactory;

    protected $table = 'db_fivemojo';

    protected $fillable = [
        'store_id',
        'url',
        'token',
        'instance_id',
        'status',
    ];
}
