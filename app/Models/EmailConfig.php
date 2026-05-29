<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailConfig extends Model
{
    protected $fillable = [
        'name', 'driver', 'category', 'is_default', 'is_active',
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password',
        'mailgun_domain', 'mailgun_secret', 'mailgun_endpoint',
        'sendgrid_api_key',
        'from_name', 'from_email', 'reply_to',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
        'smtp_port'  => 'integer',
    ];

    protected $hidden = [
        'smtp_password', 'mailgun_secret', 'sendgrid_api_key',
    ];

    public static function forCategory(string $category): ?self
    {
        return static::where('category', $category)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first()
            ?? static::where('category', 'general')
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->first();
    }
}
