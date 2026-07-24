<?php

namespace Tests\Feature\System;

use App\Models\FileAsset;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Support\FeatureTestCase;

/**
 * Storage report (Phase 2): admin-gated overview + file list, signed previews,
 * and the reconcile command.
 */
class StorageReportTest extends FeatureTestCase
{
    private function asset(array $attrs = []): FileAsset
    {
        return FileAsset::create(array_merge([
            'disk' => 'public', 'path' => 'company/logo-' . uniqid() . '.png',
            'section' => 'company', 'size' => 200 * 1024, 'mime_type' => 'image/png',
        ], $attrs));
    }

    public function test_report_lists_files_and_breaks_down_by_section(): void
    {
        $this->actingAsSystemAdmin();
        $this->asset(['section' => 'guard_post', 'size' => 500 * 1024, 'path' => 'guard-captures/a.jpg']);
        $this->asset(['section' => 'company', 'size' => 100 * 1024]);

        $this->get(route('storage.report'))
            ->assertOk()
            ->assertSee('Storage Report')
            ->assertSee('Guard Post captures')
            ->assertSee('Company assets');
    }

    public function test_section_filter_narrows_the_list(): void
    {
        $this->actingAsSystemAdmin();
        $this->asset(['section' => 'guard_post', 'path' => 'guard-captures/keep.jpg']);
        $this->asset(['section' => 'company', 'path' => 'company/hide.png']);

        $res = $this->get(route('storage.report', ['section' => 'guard_post']))->assertOk();
        $res->assertSee('keep.jpg')->assertDontSee('hide.png');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAsRole('gate_officer');

        $this->get(route('storage.report'))->assertForbidden();
    }

    public function test_signed_preview_streams_a_file(): void
    {
        Storage::fake('public');
        $this->actingAsSystemAdmin();

        Storage::disk('public')->put('company/pic.png', 'binary-image-bytes');
        $asset = $this->asset(['path' => 'company/pic.png']);

        $url = URL::temporarySignedRoute('storage.preview', now()->addMinutes(5), ['asset' => $asset->id, 'inline' => 1]);

        $this->get($url)->assertOk();
    }

    public function test_reconcile_fix_indexes_orphans_and_removes_missing(): void
    {
        Storage::fake('public');
        $this->actingAsSystemAdmin();

        // Orphan: a file on disk in a tracked dir, not in the ledger.
        Storage::disk('public')->put('guard-captures/orphan.jpg', 'x');
        // Missing: a ledger row whose file does not exist.
        $missing = $this->asset(['path' => 'company/gone.png']);

        $this->artisan('storage:reconcile --fix')->assertSuccessful();

        $this->assertDatabaseHas('file_assets', ['path' => 'guard-captures/orphan.jpg', 'section' => 'guard_post']);
        $this->assertDatabaseMissing('file_assets', ['id' => $missing->id]);
    }
}
