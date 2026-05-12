<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudStorageSetting extends Model
{
    protected $fillable = [
        'provider',
        'dropbox_access_token', 'dropbox_app_key', 'dropbox_app_secret', 'dropbox_root_folder',
        'gdrive_client_id', 'gdrive_client_secret', 'gdrive_refresh_token', 'gdrive_folder_id',
        'tested_at', 'last_test_ok', 'updated_by',
    ];

    protected $casts = [
        'tested_at'    => 'datetime',
        'last_test_ok' => 'boolean',
    ];

    // Always hidden in JSON/array output so credentials are not accidentally exposed
    protected $hidden = [
        'dropbox_access_token', 'dropbox_app_secret',
        'gdrive_client_secret', 'gdrive_refresh_token',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Returns the single settings row, creating it with defaults if missing
    public static function current(): self
    {
        return static::firstOrCreate([], ['provider' => 'local']);
    }
}
