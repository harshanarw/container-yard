<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    protected $fillable = [
        'documentable_type', 'documentable_id',
        'provider', 'path', 'disk',
        'original_name', 'mime_type', 'size',
        'label', 'document_type', 'uploaded_by',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ── Type helpers ──────────────────────────────────────────────────────────

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function isOffice(): bool
    {
        return str_contains($this->mime_type, 'word')
            || str_contains($this->mime_type, 'spreadsheet')
            || str_contains($this->mime_type, 'presentation')
            || str_contains($this->mime_type, 'excel')
            || in_array($this->mime_type, [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/msword',
                'application/vnd.ms-excel',
            ]);
    }

    public function isPreviewable(): bool
    {
        return $this->isImage() || $this->isPdf() || $this->isOffice();
    }

    public function icon(): string
    {
        return match(true) {
            $this->isImage()  => 'bi-file-image',
            $this->isPdf()    => 'bi-file-earmark-pdf',
            $this->isOffice() => str_contains($this->mime_type, 'spreadsheet') || str_contains($this->mime_type, 'excel')
                                    ? 'bi-file-earmark-spreadsheet'
                                    : 'bi-file-earmark-word',
            default           => 'bi-file-earmark',
        };
    }

    public function iconColor(): string
    {
        return match(true) {
            $this->isImage() => 'text-success',
            $this->isPdf()   => 'text-danger',
            $this->isOffice() => 'text-primary',
            default           => 'text-secondary',
        };
    }

    public function formattedSize(): string
    {
        if ($this->size < 1024)       return $this->size . ' B';
        if ($this->size < 1048576)    return round($this->size / 1024, 1) . ' KB';
        return round($this->size / 1048576, 2) . ' MB';
    }
}
