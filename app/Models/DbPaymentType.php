<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbPaymentType extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_paymenttypes';

    protected $fillable = [
        'store_id',
        'payment_type',
        'status',
    ];

    public function store()
    {
        return $this->belongsTo(DbStore::class, 'store_id');
    }
}
