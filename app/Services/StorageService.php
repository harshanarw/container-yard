<?php

namespace App\Services;

use App\Exceptions\StorageLimitException;
use App\Models\FileAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The single entry point for storing uploaded files. Enforces the storage quota
 * before writing, records every file in the file_assets ledger, and keeps the
 * usage cache fresh. Direct uploads (guard post, OCR, logos, photos) go through
 * here; DocumentManager uploads are quota-checked in DocumentManager and recorded
 * via the Document observer.
 */
class StorageService
{
    public function __construct(private readonly StorageUsageService $usage)
    {
    }

    /**
     * Throw if adding $incomingBytes would exceed the limit (when enforced).
     *
     * @throws StorageLimitException
     */
    public function assertWithinQuota(int $incomingBytes): void
    {
        if ($this->usage->wouldExceed($incomingBytes)) {
            throw new StorageLimitException($this->usage->usedBytes(), $this->usage->limitBytes());
        }
    }

    /** Store a file in $folder, record it, and return the stored path. */
    public function store(UploadedFile $file, string $folder, string $section, ?Model $owner = null, string $disk = 'public'): string
    {
        $this->assertWithinQuota((int) $file->getSize());
        $path = $file->store($folder, $disk);
        $this->remember($disk, $path, (int) $file->getSize(), $section, $owner, $file->getClientMimeType());

        return $path;
    }

    /** Store a file under a specific name, record it, and return the stored path. */
    public function storeAs(UploadedFile $file, string $folder, string $name, string $section, ?Model $owner = null, string $disk = 'public'): string
    {
        $this->assertWithinQuota((int) $file->getSize());
        $path = $file->storeAs($folder, $name, $disk);
        $this->remember($disk, $path, (int) $file->getSize(), $section, $owner, $file->getClientMimeType());

        return $path;
    }

    /** Record (or update) a ledger row for a file that is already stored. */
    public function remember(string $disk, string $path, int $size, string $section, ?Model $owner = null, ?string $mime = null): FileAsset
    {
        $asset = FileAsset::updateOrCreate(
            ['disk' => $disk, 'path' => $path],
            [
                'size'        => $size,
                'section'     => $section,
                'mime_type'   => $mime,
                'owner_type'  => $owner?->getMorphClass(),
                'owner_id'    => $owner?->getKey(),
                'uploaded_by' => auth()->id(),
            ]
        );

        $this->usage->flush();

        return $asset;
    }

    /** Remove only the ledger row (file already gone / handled elsewhere). */
    public function forget(string $disk, string $path): void
    {
        FileAsset::where('disk', $disk)->where('path', $path)->delete();
        $this->usage->flush();
    }

    /** Delete the physical file AND its ledger row. */
    public function delete(string $disk, string $path): void
    {
        try {
            Storage::disk($disk)->delete($path);
        } catch (\Throwable) {
            // File may already be gone; still clear the ledger.
        }
        $this->forget($disk, $path);
    }
}
