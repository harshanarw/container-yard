<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Ledger row for a single uploaded file. Populated by StorageService (direct
 * uploads) and by the Document observer (DocumentManager uploads). The sum of
 * `size` is the app's total storage usage.
 */
class FileAsset extends Model
{
    protected $fillable = [
        'disk', 'path', 'section', 'size', 'mime_type',
        'owner_type', 'owner_id', 'document_id', 'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    /** Human labels for each storage section (used by the report / dashboard). */
    public const SECTION_LABELS = [
        'guard_post' => 'Guard Post captures',
        'gate_ocr'   => 'Gate OCR images',
        'gate_photo' => 'Gate movement photos',
        'document'   => 'Documents',
        'company'    => 'Company assets',
        'customer'   => 'Customer logos',
        'user'       => 'User photos',
        'other'      => 'Other',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function getSectionLabelAttribute(): string
    {
        return self::SECTION_LABELS[$this->section] ?? ucfirst(str_replace('_', ' ', (string) $this->section));
    }
}
