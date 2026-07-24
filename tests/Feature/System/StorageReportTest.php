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

    public function test_document_owner_maps_to_a_specific_section(): void
    {
        // Files uploaded through the Document system are bucketed by their owning
        // record's class instead of a single "Documents" catch-all.
        $this->assertSame('survey', FileAsset::sectionForOwner(\App\Models\Inquiry::class));
        $this->assertSame('repair_estimate', FileAsset::sectionForOwner(\App\Models\Estimate::class));
        $this->assertSame('gate_photo', FileAsset::sectionForOwner(\App\Models\GateMovement::class));
        $this->assertSame('supplier_invoice', FileAsset::sectionForOwner(\App\Models\SupplierInvoice::class));

        // Unmapped owners and owner-less files fall back to the generic bucket.
        $this->assertSame('document', FileAsset::sectionForOwner(\App\Models\User::class));
        $this->assertSame('document', FileAsset::sectionForOwner(null));
    }

    public function test_report_labels_split_document_sections(): void
    {
        $this->actingAsSystemAdmin();
        $this->asset(['section' => 'survey', 'path' => 'documents/survey/a.jpg']);
        $this->asset(['section' => 'repair_estimate', 'path' => 'documents/est/b.pdf']);

        $this->get(route('storage.report'))
            ->assertOk()
            ->assertSee('Survey Captures')
            ->assertSee('Repair Estimates');
    }

    public function test_reference_filter_matches_the_owning_records_number(): void
    {
        $this->actingAsSystemAdmin();
        $wanted = \App\Models\Customer::factory()->create(['code' => 'CUST-WANT']);
        $other  = \App\Models\Customer::factory()->create(['code' => 'CUST-NOPE']);

        $this->asset([
            'section' => 'customer', 'path' => 'customers/wanted.png',
            'owner_type' => \App\Models\Customer::class, 'owner_id' => $wanted->id,
        ]);
        $this->asset([
            'section' => 'customer', 'path' => 'customers/other.png',
            'owner_type' => \App\Models\Customer::class, 'owner_id' => $other->id,
        ]);

        // Reference search resolves to the owning record, then filters its files.
        $this->get(route('storage.report', ['ref' => 'CUST-WANT']))
            ->assertOk()
            ->assertSee('wanted.png')
            ->assertDontSee('other.png');

        // A reference that matches nothing returns an empty list, not everything.
        $this->get(route('storage.report', ['ref' => 'NO-SUCH-REF']))
            ->assertOk()
            ->assertDontSee('wanted.png')
            ->assertDontSee('other.png');
    }

    public function test_container_filter_finds_files_by_container_number(): void
    {
        $this->actingAsSystemAdmin();
        $customer = \App\Models\Customer::factory()->create();

        $c1 = \App\Models\Container::factory()->create(['customer_id' => $customer->id, 'container_no' => 'TCLU1112223']);
        $c2 = \App\Models\Container::factory()->create(['customer_id' => $customer->id, 'container_no' => 'MSKU9998887']);

        $m1 = \App\Models\GateMovement::create([
            'container_id' => $c1->id, 'container_no' => $c1->container_no, 'customer_id' => $customer->id,
            'movement_type' => 'in', 'size' => $c1->size, 'container_type' => $c1->type_code, 'created_by' => auth()->id(),
        ]);
        $m2 = \App\Models\GateMovement::create([
            'container_id' => $c2->id, 'container_no' => $c2->container_no, 'customer_id' => $customer->id,
            'movement_type' => 'in', 'size' => $c2->size, 'container_type' => $c2->type_code, 'created_by' => auth()->id(),
        ]);

        $this->asset(['section' => 'gate_photo', 'path' => 'docs/gm/box1.jpg',
            'owner_type' => \App\Models\GateMovement::class, 'owner_id' => $m1->id]);
        $this->asset(['section' => 'gate_photo', 'path' => 'docs/gm/box2.jpg',
            'owner_type' => \App\Models\GateMovement::class, 'owner_id' => $m2->id]);

        $this->get(route('storage.report', ['container' => 'TCLU1112223']))
            ->assertOk()
            ->assertSee('box1.jpg')
            ->assertDontSee('box2.jpg');
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
