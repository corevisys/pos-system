<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerGuardian extends Model
{
    use HasFactory;

    protected $table = 'customer_guardians';

    protected $fillable = [
        'customer_id',
        'name',
        'relationship',
        'mobile',
        'nid_front',
        'nid_back',
        'photo'
    ];

    public function customer()
    {
        return $this->belongsTo(DbCustomer::class, 'customer_id');
    }
}
