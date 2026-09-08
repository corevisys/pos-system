<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbPermission extends Model
{
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
