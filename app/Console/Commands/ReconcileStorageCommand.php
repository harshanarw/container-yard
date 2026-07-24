<?php

namespace App\Console\Commands;

use App\Models\FileAsset;
use App\Services\StorageUsageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Compares the file_assets ledger against what's actually on disk:
 *   - "missing"  → a ledger row whose file no longer exists.
 *   - "orphan"   → a file in a tracked upload directory with no ledger row.
 *
 * Reports both; with --fix, removes missing rows and indexes orphans.
 *
 *   php artisan storage:reconcile          # report only
 *   php artisan storage:reconcile --fix    # repair the ledger
 */
class ReconcileStorageCommand extends Command
{
    protected $signature = 'storage:reconcile {--fix : remove missing rows and index orphan files}';

    protected $description = 'Reconcile the storage ledger against actual files (orphans / missing).';

    /** Direct-upload directories on the public disk → section. */
    private const DIRECT_DIRS = [
        'guard-captures'     => 'guard_post',
        'gate-movements/ocr' => 'gate_ocr',
        'company'            => 'company',
        'customers'          => 'customer',
        'users'              => 'user',
    ];

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');

        // 1) Missing — a local/public ledger row whose file is gone (cloud not checked).
        $missing = 0;
        foreach (FileAsset::cursor() as $asset) {
            if (! in_array($asset->disk, ['public', 'local'], true)) {
                continue;
            }
            if (! Storage::disk($asset->disk)->exists($asset->path)) {
                $missing++;
                $this->line("  missing: {$asset->disk}:{$asset->path}");
                if ($fix) {
                    $asset->delete();
                }
            }
        }

        // 2) Orphans — files in the tracked upload dirs with no ledger row.
        $orphans = 0;
        $disk = Storage::disk('public');
        foreach (self::DIRECT_DIRS as $dir => $section) {
            if (! $disk->exists($dir)) {
                continue;
            }
            foreach ($disk->allFiles($dir) as $path) {
                if (FileAsset::where('disk', 'public')->where('path', $path)->exists()) {
                    continue;
                }
                $orphans++;
                $this->line("  orphan: public:{$path}");
                if ($fix) {
                    FileAsset::create([
                        'disk'      => 'public',
                        'path'      => $path,
                        'size'      => $disk->size($path),
                        'section'   => $section,
                        'mime_type' => rescue(fn () => $disk->mimeType($path), null, false) ?: null,
                    ]);
                }
            }
        }

        app(StorageUsageService::class)->flush();

        $this->info(sprintf(
            'Reconcile %s: %d orphan(s), %d missing.',
            $fix ? 'repaired' : 'found',
            $orphans,
            $missing
        ));

        if (! $fix && ($orphans || $missing)) {
            $this->comment('Re-run with --fix to repair the ledger.');
        }

        return self::SUCCESS;
    }
}
