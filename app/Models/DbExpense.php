<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbExpense extends Model
{
    use HasFactory;

    protected $table = 'db_expense';

    protected $fillable = [
        'store_id',
        'count_id',
        'expense_code',
        'category_id',
        'expense_date',
        'reference_no',
        'expense_for',
        'expense_amt',
        'payment_type',
        'account_id',
        'note',
        'created_by',
        'created_date',
        'created_time',
        'system_ip',
        'system_name',
        'status',
    ];

    public function category()
    {
        return $this->belongsTo(DbExpenseCategory::class, 'category_id');
    }

    public function account()
    {
        return $this->belongsTo(AcAccount::class, 'account_id');
    }
}
