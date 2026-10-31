<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, \App\Models\Concerns\Auditable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'branch_id',
        'permissions',
        'is_active'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'permissions' => 'array',
        'is_active' => 'boolean'
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isReception()
    {
        return $this->role === 'reception';
    }

    public function hasPermission($permission)
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($permission, $this->permissions ?? []);
    }

    /** @return array<int, string> */
    protected function auditExcludedKeys(): array
    {
        return ['last_login_at'];
    }

    /** @return array<int, string> */
    protected function auditMaskedKeys(): array
    {
        return ['password', 'remember_token'];
    }
}
