<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerGuarantor extends Model
{
    use HasFactory;

    protected $table = 'customer_guarantors';

    protected $fillable = [
        'customer_id',
        'name',
        'father_name',
        'address',
        'mobile',
        'occupation',
        'monthly_income',
        'photo',
        'nid_front',
        'nid_back',
        'job_id'
    ];

    protected $casts = [
        'monthly_income' => 'decimal:2'
    ];

    public function customer()
    {
        return $this->belongsTo(DbCustomer::class, 'customer_id');
    }
}
