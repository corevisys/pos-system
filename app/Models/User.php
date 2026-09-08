<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id',
        'username',
        'name',
        'first_name',
        'last_name',
        'email',
        'email_verified_at',
        'password',
        'member_of',

        'mobile',
        'photo',
        'gender',
        'dob',
        'country',
        'state',
        'city',
        'address',
        'postcode',
        'role_name',
        'role_id',
        'profile_picture',
        'created_date',
        'created_time',
        'created_by',
        'system_ip',
        'system_name',
        'status',
        'creater_id',
        'updater_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'integer',
        ];
    }

    public function role()
    {
        return $this->belongsTo(DbRole::class, 'role_id');
    }

    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('username', 'like', "%{$term}%")
              ->orWhere('first_name', 'like', "%{$term}%")
              ->orWhere('last_name', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%");
        });
    }

    public function scopeFilterRole($query, $roleId)
    {
        return $query->when($roleId, fn($q) => $q->where('role_id', $roleId));
    }

    public function scopeFilterStatus($query, $status)
    {
        return $query->when($status !== null && $status !== '', fn($q) => $q->where('status', $status));
    }

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function isSuperAdmin()
    {
        return $this->role_id === 1 || $this->role_name === 'Super Admin';
    }

    public function hasPermission($permission)
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (!$this->role || !$this->role->permissions) {
            return false;
        }

        $permissions = $this->role->permissions->permissions ?? [];
        return in_array($permission, $permissions);
    }

    public function store()
    {
        return $this->belongsTo(\App\Models\DbStore::class, 'store_id');
    }
}
