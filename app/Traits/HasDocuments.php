<?php

namespace App\Traits;

use App\Models\Document;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasDocuments
{
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->latest();
    }

    public function photos(): MorphMany
    {
        return $this->documents()->where('mime_type', 'like', 'image/%');
    }

    public function pdfDocuments(): MorphMany
    {
        return $this->documents()->where('mime_type', 'application/pdf');
    }
}
