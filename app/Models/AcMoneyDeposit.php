<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcMoneyDeposit extends Model
{
    use HasFactory;

    protected $table = 'ac_moneydeposits';

    protected $fillable = [
        'store_id',
        'deposit_date',
        'reference_no',
        'debit_account_id',
        'credit_account_id',
        'amount',
        'note',
        'created_by_username',
        'created_date',
        'created_time',
        'system_ip',
        'system_name',
        'status',
        'delete_bit',
        'created_by'
    ];

    /**
     * Get the debit account.
     */
    public function debitAccount()
    {
        return $this->belongsTo(AcAccount::class, 'debit_account_id');
    }

    /**
     * Get the credit account.
     */
    public function creditAccount()
    {
        return $this->belongsTo(AcAccount::class, 'credit_account_id');
    }

    /**
     * Get the creator (user).
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
