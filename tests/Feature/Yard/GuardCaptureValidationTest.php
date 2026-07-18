<?php

namespace Tests\Feature\Yard;

use App\Models\CompanySetting;
use App\Models\GuardCapture;
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
        $cs = CompanySetting::current();
        $cs->update(['enable_guard_post' => true]);
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
        // ── TEMP DIAGNOSTIC (pre-POST flag state) ────────────────────────
        fwrite(STDERR, "\n[DIAG2] db_flag=" . var_export(\Illuminate\Support\Facades\DB::table('company_settings')->value('enable_guard_post'), true)
            . " row_count=" . \Illuminate\Support\Facades\DB::table('company_settings')->count()
            . " current_flag=" . var_export(\App\Models\CompanySetting::current()->enable_guard_post, true) . "\n");
        // ─────────────────────────────────────────────────────────────────

        // Correct shape, wrong check digit (should be 3, not 4).
        $res = $this->post(route('guard-post.store'), [
            'direction'        => 'gate_in',
            'container_number' => 'CSQU3054384',
        ]);

        // ── TEMP DIAGNOSTIC ──────────────────────────────────────────────
        fwrite(STDERR, "\n[DIAG] status=" . $res->getStatusCode()
            . " redirect=" . ($res->headers->get('Location') ?? '-')
            . " cd_valid=" . var_export(\App\Support\Iso6346::checkDigitValid('CSQU3054384'), true)
            . " warning=" . var_export(session('warning'), true)
            . " capture_cno=" . var_export(\App\Models\GuardCapture::latest('id')->value('container_number'), true)
            . "\n");
        // ─────────────────────────────────────────────────────────────────

        $res->assertSessionHasNoErrors()->assertSessionHas('warning');

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
