<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'role', 'status',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login'        => 'datetime',
        'password'          => 'hashed',
    ];

    // Relationships
    public function inspectedInquiries()
    {
        return $this->hasMany(Inquiry::class, 'inspector_id');
    }

    public function createdEstimates()
    {
        return $this->hasMany(Estimate::class, 'created_by');
    }

    public function approvedEstimates()
    {
        return $this->hasMany(Estimate::class, 'approved_by');
    }

    public function gateMovements()
    {
        return $this->hasMany(GateMovement::class, 'created_by');
    }

    // Helpers
    public function isAdmin(): bool
    {
        return $this->role === 'administrator';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
