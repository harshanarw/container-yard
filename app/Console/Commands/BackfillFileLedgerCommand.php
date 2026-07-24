<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\FileAsset;
use App\Services\StorageUsageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Seed the file_assets ledger from files that already exist: every Document, plus
 * the direct-upload directories on the public disk (guard captures, gate OCR,
 * company/customer/user images). Idempotent — safe to re-run; skips files already
 * indexed. Run once after migrating:
 *
 *   php artisan storage:backfill-ledger
 */
class BackfillFileLedgerCommand extends Command
{
    protected $signature = 'storage:backfill-ledger';

    protected $description = 'Populate the file_assets storage ledger from existing documents and uploaded files.';

    /** Public-disk directories written to directly (not via DocumentManager) → section. */
    private const DIRECT_DIRS = [
        'guard-captures'     => 'guard_post',
        'gate-movements/ocr' => 'gate_ocr',
        'company'            => 'company',
        'customers'          => 'customer',
        'users'              => 'user',
    ];

    public function handle(): int
    {
        $indexed = 0;

        // 1) Documents (already carry size + owner in the documents table).
        foreach (Document::cursor() as $doc) {
            $asset = FileAsset::updateOrCreate(
                ['disk' => $doc->disk ?: 'public', 'path' => $doc->path],
                [
                    'size'        => (int) $doc->size,
                    'section'     => 'document',
                    'mime_type'   => $doc->mime_type,
                    'owner_type'  => $doc->documentable_type,
                    'owner_id'    => $doc->documentable_id,
                    'document_id' => $doc->id,
                    'uploaded_by' => $doc->uploaded_by,
                ]
            );
            $indexed += $asset->wasRecentlyCreated ? 1 : 0;
        }
        $this->info('Documents indexed.');

        // 2) Direct uploads on the public disk (skip anything already in the ledger).
        $disk = Storage::disk('public');
        foreach (self::DIRECT_DIRS as $dir => $section) {
            if (! $disk->exists($dir)) {
                continue;
            }
            foreach ($disk->allFiles($dir) as $path) {
                if (FileAsset::where('disk', 'public')->where('path', $path)->exists()) {
                    continue;
                }
                FileAsset::create([
                    'disk'      => 'public',
                    'path'      => $path,
                    'size'      => $disk->size($path),
                    'section'   => $section,
                    'mime_type' => rescue(fn () => $disk->mimeType($path), null, false) ?: null,
                ]);
                $indexed++;
            }
            $this->line("  {$dir} → {$section}");
        }

        $usage = app(StorageUsageService::class);
        $usage->flush();

        $this->info(sprintf(
            'Backfill complete: %d file(s) indexed. Total usage: %s MB.',
            $indexed,
            number_format($usage->usedBytes() / 1048576, 1)
        ));

        return self::SUCCESS;
    }
}
