<?php

namespace App\Models;

use App\Models\Traits\StoreScoped;
use Illuminate\Database\Eloquent\Model;

class DbPermission extends Model
{
    use StoreScoped;
    use \App\Models\Concerns\PinsExplicitIdInTests;

    protected $table = 'db_permissions';

    protected $fillable = [
        'store_id',
        'role_id',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function role()
    {
        return $this->belongsTo(DbRole::class, 'role_id');
    }
}
