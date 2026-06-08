<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'role', 'status',
        'title', 'first_name', 'last_name', 'gender', 'date_of_birth', 'national_id',
        'employee_reg_no', 'department', 'joined_date', 'profile_photo',
        'emergency_contact', 'emergency_phone',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login'        => 'datetime',
        'date_of_birth'     => 'date',
        'joined_date'       => 'date',
        'password'          => 'hashed',
    ];

    // Accessors
    public function getFullNameAttribute(): string
    {
        if ($this->first_name || $this->last_name) {
            return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        }
        return $this->name;
    }

    public function getAvatarInitialsAttribute(): string
    {
        $name = $this->full_name;
        $parts = array_filter(explode(' ', trim($name)));
        if (count($parts) >= 2) {
            return strtoupper(substr(reset($parts), 0, 1) . substr(end($parts), 0, 1));
        }
        return strtoupper(substr($name, 0, 2));
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo ? Storage::url($this->profile_photo) : null;
    }

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
    public function isSystemAdmin(): bool
    {
        return $this->role === 'system_administrator';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'administrator';
    }

    public function isSuperUser(): bool
    {
        return $this->isAdmin() || $this->isSystemAdmin();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSecurityOfficer(): bool
    {
        return $this->role === 'security_officer';
    }
}
