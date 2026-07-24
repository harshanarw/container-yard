<?php

namespace App\Observers;

use App\Models\Document;
use App\Models\FileAsset;
use App\Services\StorageUsageService;

/**
 * Mirrors DocumentManager uploads/deletes into the file_assets ledger so
 * documents count toward storage usage alongside direct uploads — without
 * touching every controller that uploads a document.
 */
class DocumentObserver
{
    public function created(Document $document): void
    {
        FileAsset::updateOrCreate(
            ['disk' => $document->disk ?: 'public', 'path' => $document->path],
            [
                'size'        => (int) $document->size,
                'section'     => 'document',
                'mime_type'   => $document->mime_type,
                'owner_type'  => $document->documentable_type,
                'owner_id'    => $document->documentable_id,
                'document_id' => $document->id,
                'uploaded_by' => $document->uploaded_by,
            ]
        );

        app(StorageUsageService::class)->flush();
    }

    public function deleted(Document $document): void
    {
        FileAsset::where('document_id', $document->id)->delete();

        app(StorageUsageService::class)->flush();
    }
}
