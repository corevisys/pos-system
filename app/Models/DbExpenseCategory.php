<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbExpenseCategory extends Model
{
    use HasFactory;

    protected $table = 'db_expense_category';

    protected $fillable = [
        'store_id',
        'category_code',
        'category_name',
        'description',
        'created_by',
        'status',
    ];
}
