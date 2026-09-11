<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcAccount extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'ac_accounts';

    protected $fillable = [
        'count_id',
        'store_id',
        'account_code',
        'account_name',
        'system_key',
        'is_system',
        'parent_id',
        'sort_code',
        'balance',
        'note',
        'status',
        'delete_bit',
        'created_by',
        'created_date',
        'created_time',
        'system_ip',
        'system_name',
    ];

    /**
     * Find-or-create a store-scoped system account (contra/equity) under a lock,
     * keyed by the stable system_key discriminator.
     *
     * Phase C: guarded by (a) lockForUpdate() on the existing row lookup, (b) a
     * (store_id, system_key) unique index, and (c) a re-select backstop when a
     * genuine concurrent first insert violates the unique constraint (handles both
     * MySQL 1062 and SQLite 19 / SQLSTATE 23000 variants).
     */
    public static function findOrCreateSystemAccount(int $storeId, string $systemKey, string $accountName): self
    {
        // 1. Lock any existing row for this store + system_key (serializes first-use).
        $existing = static::where('store_id', $storeId)
            ->where('system_key', $systemKey)
            ->where('delete_bit', 0)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return $existing;
        }

        // 2. Also tolerate a legacy row created before the system_key column existed.
        $legacy = static::where('store_id', $storeId)
            ->where('account_name', $accountName)
            ->where('delete_bit', 0)
            ->lockForUpdate()
            ->first();

        if ($legacy) {
            $legacy->system_key = $systemKey;
            $legacy->is_system = true;
            $legacy->save();
            return $legacy;
        }

        // 3. No row exists — create it under the transaction lock.
        $code = \App\Services\CodeGeneratorService::generate('account');
        $sortCode = (static::max('id') ?? 0) + 1;
        $now = now();

        try {
            $account = new static();
            $account->count_id = (static::max('count_id') ?? 0) + 1;
            $account->store_id = $storeId;
            $account->parent_id = null;
            $account->account_name = $accountName;
            $account->account_code = $code;
            $account->system_key = $systemKey;
            $account->is_system = true;
            $account->sort_code = (string) $sortCode;
            $account->balance = 0;
            $account->note = 'System generated ' . $accountName . ' contra account';
            $account->created_by = auth()->id() ?? 1;
            $account->created_date = $now->format('Y-m-d');
            $account->created_time = $now->format('H:i:s');
            $account->system_ip = request()->ip() ?? '127.0.0.1';
            $account->system_name = gethostbyaddr(request()->ip() ?? '127.0.0.1') ?: 'unknown';
            $account->delete_bit = 0;
            $account->status = 1;
            $account->save();

            return $account;
        } catch (\Illuminate\Database\QueryException $e) {
            $driverCode = $e->errorInfo[1] ?? null;
            $sqlState = $e->errorInfo[0] ?? null;
            $isUniqueViolation =
                $driverCode === 1062 ||
                $driverCode === 19 ||
                $sqlState === '23000' ||
                str_contains(strtolower($e->getMessage()), 'unique');

            if ($isUniqueViolation) {
                $winner = static::where('store_id', $storeId)
                    ->where('system_key', $systemKey)
                    ->where('delete_bit', 0)
                    ->first();

                if ($winner) {
                    return $winner;
                }
            }
            throw $e;
        }
    }

    public function parent()
    {
        return $this->belongsTo(AcAccount::class, 'parent_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
