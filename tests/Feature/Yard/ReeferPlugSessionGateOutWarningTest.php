<?php

namespace Tests\Feature\Yard;

use App\Models\CompanySetting;
use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\ReeferPlugSession;
use App\Models\YardJobType;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Asking the plug-in question while somebody can still answer it.
 *
 * A laden reefer gated in under a plug service opens a `pending` session. If
 * nobody records the plug-in, gate-out closes it as `not_plugged` — and
 * `not_plugged` is excluded from electricity billing by two separate
 * conditions, so the container never reaches an invoice.
 *
 * That was correct behaviour and silent, which is the problem. `pending` at
 * gate-out means one of two things and the system cannot tell them apart:
 *
 *   - the box was never physically plugged, so billing nothing is right;
 *   - it ran on power all stay and nobody recorded it, which is revenue lost.
 *
 * `reefer:unplugged-sessions` finds the second kind weeks later and reports
 * real ones, so it is not hypothetical. The only person who knows is the
 * operator at the gate, so the question is asked there: on the form before
 * submitting, and again on release. `enforce_reefer_plug_session` turns the
 * warning into a block for a yard that wants one.
 */
class ReeferPlugSessionGateOutWarningTest extends FeatureTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-15 12:00:00');
        $this->customer = Customer::factory()->create();
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Warn by default ─────────────────────────────────────────────────────

    /** The default: the release goes through, and says what it cost. */
    public function test_releasing_with_no_plug_in_warns_and_still_gates_out(): void
    {
        $this->enforce(false);
        $this->gateInLadenReefer('WARN0000001');

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutPayload('WARN0000001'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning', fn ($m) => str_contains($m, 'WARN0000001')
                && str_contains($m, 'no plug-in recorded'));

        $this->assertSame('not_plugged', $this->sessionFor('WARN0000001')->status,
            'The release still happened, and the session is marked honestly.');
    }

    /** The warning names the container, because a gate clerk handles many. */
    public function test_the_warning_names_the_container_and_the_consequence(): void
    {
        $this->enforce(false);
        $this->gateInLadenReefer('WARN0000002');

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutPayload('WARN0000002'));

        $message = session('warning');

        $this->assertStringContainsString('WARN0000002', $message);
        $this->assertStringContainsString('not be billed for electricity', $message,
            'Saying it left unplugged is only half the point; the cost is the other half.');
    }

    // ── Block when the yard asks for it ─────────────────────────────────────

    public function test_enforcement_blocks_the_release(): void
    {
        $this->enforce(true);
        $this->gateInLadenReefer('BLOK0000001');

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutPayload('BLOK0000001'))
            ->assertSessionHasErrors('container_no');

        $this->assertSame('pending', $this->sessionFor('BLOK0000001')->status,
            'Refused means nothing moved: the session is untouched, not closed.');
        $this->assertDatabaseMissing('gate_movements', [
            'container_no'  => 'BLOK0000001',
            'movement_type' => 'out',
        ]);
    }

    /** Recording the plug-in clears the block — the message has to be actionable. */
    public function test_recording_the_plug_in_unblocks_the_release(): void
    {
        $this->enforce(true);
        $this->gateInLadenReefer('BLOK0000002');

        $session = $this->sessionFor('BLOK0000002');
        $session->update(['plug_in_at' => '2026-09-09 09:00:00', 'status' => 'active']);

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutPayload('BLOK0000002'))
            ->assertSessionHasNoErrors();

        $this->assertSame('completed', $this->sessionFor('BLOK0000002')->status);
    }

    // ── What must not be caught ─────────────────────────────────────────────

    /**
     * An active session has a plug-in, and gate-out stamps its plug-out, so it
     * bills correctly. Warning on it would train operators to ignore the
     * warning.
     */
    public function test_an_active_session_is_not_warned_about(): void
    {
        $this->enforce(false);
        $this->gateInLadenReefer('OKAY0000001');
        $this->sessionFor('OKAY0000001')
            ->update(['plug_in_at' => '2026-09-09 09:00:00', 'status' => 'active']);

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutPayload('OKAY0000001'))
            ->assertSessionHasNoErrors();

        $this->assertNull(session('warning'));
        $this->assertSame('completed', $this->sessionFor('OKAY0000001')->status);
    }

    /** A dry box has no plug session at all, so nothing changes for it. */
    public function test_a_non_reefer_release_is_untouched(): void
    {
        $this->enforce(true);
        $this->gateInDryBox('PLAIN000001');

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutPayload('PLAIN000001'))
            ->assertSessionHasNoErrors();
    }

    // ── The form says so before the operator submits ────────────────────────

    /**
     * The point of the whole change: the truck is still at the gate and
     * somebody knows whether the box was on power. Afterwards it is a
     * reconciliation, not a fix.
     */
    public function test_the_gate_out_form_cautions_before_submitting(): void
    {
        $this->enforce(false);
        $this->gateInLadenReefer('FORM0000001');

        $json = $this->lookup('FORM0000001');

        $this->assertTrue($json['releasable'], 'A caution, not a block.');
        $this->assertStringContainsString('No plug-in recorded', $json['plug_warning']);
        $this->assertNotNull($json['plug_session_url'],
            'One click to the form that fixes it, not just a diagnosis.');
    }

    public function test_the_form_blocks_when_enforcement_is_on(): void
    {
        $this->enforce(true);
        $this->gateInLadenReefer('FORM0000002');

        $json = $this->lookup('FORM0000002');

        $this->assertFalse($json['releasable']);
        $this->assertStringContainsString('no plug-in recorded', $json['release_block']);
        $this->assertNull($json['plug_warning'],
            'A block and a caution about the same thing reads as two problems.');
    }

    public function test_the_form_is_quiet_when_there_is_nothing_to_say(): void
    {
        $this->enforce(false);
        $this->gateInLadenReefer('FORM0000003');
        $this->sessionFor('FORM0000003')
            ->update(['plug_in_at' => '2026-09-09 09:00:00', 'status' => 'active']);

        $json = $this->lookup('FORM0000003');

        $this->assertTrue($json['releasable']);
        $this->assertNull($json['plug_warning']);
    }

    // ── The dashboard tile ──────────────────────────────────────────────────

    public function test_the_dashboard_counts_pending_plug_sessions(): void
    {
        $this->enforce(false);
        $this->gateInLadenReefer('DASH0000001');
        $this->gateInLadenReefer('DASH0000002');

        $html = $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Reefer Plug-Ins Pending')
            ->assertSee(route('yard.reefer.index', ['status' => 'pending']), false)
            ->getContent();

        // Anchored to this tile's own label. `data-target="2"` on its own would
        // match any other tile that happens to show 2 — and FeatureTestCase
        // seeds a realistic baseline, so several of them might.
        $this->assertMatchesRegularExpression(
            '/Reefer Plug-Ins Pending.*?data-target="2"/s',
            $html,
        );
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function enforce(bool $on): void
    {
        CompanySetting::current()->update(['enforce_reefer_plug_session' => $on]);
        CompanySetting::flushCache();
    }

    /** @return array<string, mixed> */
    private function lookup(string $containerNo): array
    {
        return $this->get(route('yard.container-lookup', ['container_no' => $containerNo]))
            ->assertOk()
            ->json();
    }

    private function sessionFor(string $containerNo): ReeferPlugSession
    {
        $container = Container::where('container_no', $containerNo)->firstOrFail();

        return ReeferPlugSession::where('container_id', $container->id)
            ->orderBy('id')
            ->firstOrFail();
    }

    private function reeferType(): EquipmentType
    {
        return EquipmentType::all()->first(fn ($e) => $e->isReefer())
            ?? EquipmentType::query()->firstOrFail();
    }

    private function gateInJobType(): YardJobType
    {
        return YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')
            ->firstOrFail();
    }

    private function gateInLadenReefer(string $containerNo): void
    {
        $this->from(route('yard.gate'))->post(route('yard.gate.in'), [
            'job_type_id'         => $this->gateInJobType()->id,
            'container_no'        => $containerNo,
            'equipment_type_id'   => $this->reeferType()->id,
            'customer_id'         => $this->customer->id,
            'condition'           => 'sound',
            'cargo_status'        => 'laden',
            'seal_no'             => 'SEAL0001',
            'vehicle_plate'       => 'TRUCK01',
            'gate_in_time'        => '2026-09-08 08:00:00',
            // Operating, so a plug session is opened at all: a NOR opens none.
            'reefer_mode'         => 'operating',
            'reefer_service_type' => 'long_term',
        ])->assertSessionHasNoErrors();
    }

    private function gateInDryBox(string $containerNo): void
    {
        $dry = EquipmentType::all()->first(fn ($e) => ! $e->isReefer())
            ?? EquipmentType::query()->firstOrFail();

        $this->from(route('yard.gate'))->post(route('yard.gate.in'), [
            'job_type_id'       => $this->gateInJobType()->id,
            'container_no'      => $containerNo,
            'equipment_type_id' => $dry->id,
            'customer_id'       => $this->customer->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
            'gate_in_time'      => '2026-09-08 08:00:00',
        ])->assertSessionHasNoErrors();
    }

    /** @return array<string, string> */
    private function gateOutPayload(string $containerNo): array
    {
        return [
            'container_no'  => $containerNo,
            'vehicle_plate' => 'ABC1234',
            'driver_name'   => 'Test Driver',
            'driver_ic'     => '900101015555',
            'release_order' => 'RO-PLUG-1',
            'gate_out_time' => '2026-09-15 09:00:00',
        ];
    }
}
