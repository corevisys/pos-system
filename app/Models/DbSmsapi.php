<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbSmsapi extends Model
{
    use HasFactory;

    protected $table = 'db_smsapi';

    protected $fillable = [
        'store_id',
        'info',
        'key',
        'key_value',
        'delete_bit',
    ];
}
