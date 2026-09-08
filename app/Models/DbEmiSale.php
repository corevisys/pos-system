<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbEmiSale extends Model
{
    use HasFactory;

    protected $table = 'db_emi_sales';

    protected $fillable = [
        'sale_id',
        'customer_id',
        'loan_amount',
        'total_payable',
        'duration_months',
        'monthly_installment',
        'processing_fee',
        'start_date',
        'status',
        'notes',
    ];

    public function sale()
    {
        return $this->belongsTo(DbSale::class, 'sale_id');
    }

    public function customer()
    {
        return $this->belongsTo(DbCustomer::class, 'customer_id');
    }

    public function schedule()
    {
        return $this->hasMany(DbEmiSchedule::class, 'emi_sale_id');
    }
}
