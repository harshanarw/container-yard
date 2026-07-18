<?php

namespace Tests\Feature\Yard;

use App\Models\CompanySetting;
use App\Models\GuardCapture;
use Illuminate\Support\Facades\DB;
use Tests\Support\FeatureTestCase;

/**
 * Phase 1 — Guard Post now enforces the ISO 6346 container-number shape (like the
 * Ops gate-in), with the check digit as a soft, non-blocking warning.
 */
class GuardCaptureValidationTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsSystemAdmin();

        // Enable the Guard Post feature directly on the DB row (a direct update
        // hits the real row regardless of the cached settings model), then bust
        // the settings cache so the request reads the new value.
        DB::table('company_settings')->update(['enable_guard_post' => true]);
        CompanySetting::flushCache();
    }

    public function test_malformed_container_number_is_rejected(): void
    {
        $this->post(route('guard-post.store'), [
            'direction'        => 'gate_in',
            'container_number' => 'ABC123', // not 4 letters + 7 digits
        ])->assertSessionHasErrors('container_number');

        $this->assertSame(0, GuardCapture::count());
    }

    public function test_valid_format_but_bad_check_digit_saves_with_a_warning(): void
    {
        // Correct shape, wrong check digit (should be 3, not 4).
        $this->post(route('guard-post.store'), [
            'direction'        => 'gate_in',
            'container_number' => 'CSQU3054384',
        ])->assertSessionHasNoErrors()->assertSessionHas('warning');

        $this->assertDatabaseHas('guard_captures', ['container_number' => 'CSQU3054384']);
    }

    public function test_fully_valid_number_saves_without_a_warning(): void
    {
        // lower-case input is normalised to upper before validation.
        $this->post(route('guard-post.store'), [
            'direction'        => 'gate_in',
            'container_number' => 'csqu3054383',
        ])->assertSessionHasNoErrors()->assertSessionMissing('warning');

        $this->assertDatabaseHas('guard_captures', ['container_number' => 'CSQU3054383']);
    }
}
