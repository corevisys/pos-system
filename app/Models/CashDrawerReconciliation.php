<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashDrawerReconciliation extends Model
{
    use HasFactory;

    protected $table = 'cash_drawer_reconciliations';

    protected $fillable = [
        'reconciliation_code',
        'store_id',
        'warehouse_id',
        'account_id',
        'user_id',
        'opened_by',
        'opened_at',
        'closed_by',
        'closed_at',
        'reconciliation_date',
        'period_start',
        'period_end',
        'system_opening_balance',
        'opening_balance',
        'opening_variance',
        'is_initial',
        'opening_notes',
        'cash_sales_amount',
        'cash_refunds_amount',
        'cash_expenses_amount',
        'cash_deposits_amount',
        'cash_transfers_in',
        'cash_transfers_out',
        'expected_closing_balance',
        'counted_amount',
        'variance',
        'denominations',
        'status',
        'adjustment_transaction_id',
        'notes',
        'delete_bit',
    ];

    protected $casts = [
        'reconciliation_date' => 'date',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'system_opening_balance' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'opening_variance' => 'decimal:2',
        'is_initial' => 'boolean',
        'cash_sales_amount' => 'decimal:2',
        'cash_refunds_amount' => 'decimal:2',
        'cash_expenses_amount' => 'decimal:2',
        'cash_deposits_amount' => 'decimal:2',
        'cash_transfers_in' => 'decimal:2',
        'cash_transfers_out' => 'decimal:2',
        'expected_closing_balance' => 'decimal:2',
        'counted_amount' => 'decimal:2',
        'variance' => 'decimal:2',
        'denominations' => 'array',
        'delete_bit' => 'integer',
    ];

    public function account()
    {
        return $this->belongsTo(AcAccount::class, 'account_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(DbWarehouse::class, 'warehouse_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function adjustmentTransaction()
    {
        return $this->belongsTo(AcTransaction::class, 'adjustment_transaction_id');
    }
}
