<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsBlacklist extends Model
{
    protected $fillable = ['phone', 'reason', 'added_by'];
}
