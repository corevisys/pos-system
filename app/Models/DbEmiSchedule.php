<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbEmiSchedule extends Model
{
    use HasFactory;

    protected $table = 'db_emi_schedule';

    protected $fillable = [
        'emi_sale_id',
        'installment_no',
        'due_date',
        'amount',
        'paid_amount',
        'paid_date',
        'status',
    ];

    public function emiSale()
    {
        return $this->belongsTo(DbEmiSale::class, 'emi_sale_id');
    }

    public function payments()
    {
        return $this->hasMany(DbSalePayment::class, 'emi_schedule_id');
    }
}
