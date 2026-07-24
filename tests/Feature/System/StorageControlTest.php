<?php

namespace Tests\Feature\System;

use App\Exceptions\StorageLimitException;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Document;
use App\Models\FileAsset;
use App\Services\StorageService;
use App\Services\StorageUsageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FeatureTestCase;

/**
 * File-storage control (Phase 1): the ledger, usage summing, and the quota that
 * blocks over-limit uploads when enforced.
 */
class StorageControlTest extends FeatureTestCase
{
    private function usage(): StorageUsageService
    {
        return app(StorageUsageService::class);
    }

    private function storage(): StorageService
    {
        return app(StorageService::class);
    }

    private function setLimit(int $mb, bool $enforce = true): void
    {
        DB::table('company_settings')->update([
            'max_storage_mb'        => $mb,
            'enforce_storage_limit' => $enforce,
        ]);
        CompanySetting::flushCache();
        $this->usage()->flush();
    }

    private function seedUsage(int $kilobytes, string $path = 'seed/a'): void
    {
        FileAsset::create(['disk' => 'public', 'path' => $path, 'section' => 'other', 'size' => $kilobytes * 1024]);
        $this->usage()->flush();
    }

    public function test_store_records_a_ledger_row_and_counts_toward_usage(): void
    {
        Storage::fake('public');
        $this->actingAsSystemAdmin();

        $path = $this->storage()->store(UploadedFile::fake()->create('logo.jpg', 500), 'company', 'company');

        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('file_assets', ['path' => $path, 'section' => 'company']);
        $this->assertGreaterThan(0, $this->usage()->usedBytes());
    }

    public function test_upload_over_the_limit_is_blocked_when_enforced(): void
    {
        Storage::fake('public');
        $this->actingAsSystemAdmin();
        $this->setLimit(1);                 // 1 MB
        $this->seedUsage(900);              // 900 KB already used

        $this->expectException(StorageLimitException::class);
        $this->storage()->store(UploadedFile::fake()->create('big.jpg', 300), 'company', 'company'); // → 1.2 MB
    }

    public function test_upload_within_the_limit_is_allowed(): void
    {
        Storage::fake('public');
        $this->actingAsSystemAdmin();
        $this->setLimit(10);                // 10 MB
        $this->seedUsage(1024);             // 1 MB used

        $path = $this->storage()->store(UploadedFile::fake()->create('ok.jpg', 200), 'company', 'company');
        $this->assertDatabaseHas('file_assets', ['path' => $path]);
    }

    public function test_limit_is_not_enforced_when_the_toggle_is_off(): void
    {
        Storage::fake('public');
        $this->actingAsSystemAdmin();
        $this->setLimit(1, enforce: false); // limit set but enforcement off
        $this->seedUsage(2048);             // already 2 MB — over the "limit"

        $path = $this->storage()->store(UploadedFile::fake()->create('ok.jpg', 500), 'company', 'company');
        $this->assertDatabaseHas('file_assets', ['path' => $path]); // not blocked
    }

    public function test_delete_removes_both_the_file_and_the_ledger_row(): void
    {
        Storage::fake('public');
        $this->actingAsSystemAdmin();

        $path = $this->storage()->store(UploadedFile::fake()->create('x.jpg', 100), 'company', 'company');
        $this->assertDatabaseHas('file_assets', ['path' => $path]);

        $this->storage()->delete('public', $path);

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('file_assets', ['path' => $path]);
    }

    public function test_document_upload_and_delete_sync_the_ledger(): void
    {
        $this->actingAsSystemAdmin();
        $customer = Customer::factory()->create();

        $doc = new Document();
        $doc->documentable_type = Customer::class;
        $doc->documentable_id   = $customer->id;
        $doc->provider          = 'local';
        $doc->disk              = 'public';
        $doc->path              = 'docs/test.pdf';
        $doc->original_name     = 'test.pdf';
        $doc->mime_type         = 'application/pdf';
        $doc->size              = 2048;
        $doc->uploaded_by       = auth()->id();
        $doc->save();

        // The observer buckets the file by its owning record's class — a
        // Customer-owned document lands in 'customer', not the generic 'document'.
        $this->assertDatabaseHas('file_assets', [
            'document_id' => $doc->id,
            'section'     => 'customer',
            'size'        => 2048,
        ]);

        $doc->delete();
        $this->assertDatabaseMissing('file_assets', ['document_id' => $doc->id]);
    }

    public function test_usage_summary_breaks_down_by_section(): void
    {
        $this->actingAsSystemAdmin();
        FileAsset::create(['disk' => 'public', 'path' => 'a', 'section' => 'guard_post', 'size' => 1000]);
        FileAsset::create(['disk' => 'public', 'path' => 'b', 'section' => 'guard_post', 'size' => 500]);
        FileAsset::create(['disk' => 'public', 'path' => 'c', 'section' => 'company', 'size' => 300]);
        $this->usage()->flush();

        $summary = $this->usage()->summary();
        $this->assertSame(1800, $summary['used']);
        $bySection = $summary['sections']->keyBy('section');
        $this->assertSame(1500, (int) $bySection['guard_post']->bytes);
        $this->assertSame(2, (int) $bySection['guard_post']->files);
    }
}
