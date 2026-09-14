<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 5 — the shared, cross-store customer identity.
 *
 * Deliberately NOT StoreScoped: a person's identity is global. Only
 * CustomerIdentityResolver reads it (a sanctioned cross-store read). The model is
 * never used to expose another store's customer/dues data.
 */
class CustomerIdentity extends Model
{
    protected $table = 'db_customer_identities';

    protected $fillable = [
        'phone',
        'name',
        'email',
        'nid',
    ];

    /** Store-scoped customer rows (one per store) that refer to this identity. */
    public function customers()
    {
        return $this->hasMany(DbCustomer::class, 'customer_identity_id');
    }
}
