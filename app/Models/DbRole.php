<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbRole extends Model
{
    protected $table = 'db_roles';

    protected $fillable = [
        'store_id',
        'role_name',
        'description',
        'status',
    ];

    public function permissions()
    {
        return $this->hasOne(DbPermission::class, 'role_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'role_id');
    }
}
