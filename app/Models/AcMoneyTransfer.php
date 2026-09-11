<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcMoneyTransfer extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'ac_moneytransfer';

    protected $fillable = [
        'store_id',
        'count_id',
        'transfer_code',
        'transfer_date',
        'reference_no',
        'debit_account_id',
        'credit_account_id',
        'amount',
        'note',
        'created_by',
        'created_date',
        'created_time',
        'system_ip',
        'system_name',
        'status',
        'delete_bit',
    ];

    /**
     * Get the debit account (From Account).
     */
    public function debitAccount()
    {
        return $this->belongsTo(AcAccount::class, 'debit_account_id');
    }

    /**
     * Get the credit account (To Account).
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
