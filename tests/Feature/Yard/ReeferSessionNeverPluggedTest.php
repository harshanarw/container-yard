<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\ReeferPlugSession;
use App\Models\YardJobType;
use App\Services\ReeferBillingService;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * A reefer that leaves without ever being plugged in.
 *
 * Gate-in opens a plug session for every laden reefer, as a reminder to plug it
 * in. When the box leaves without that having happened, gate-out used to close
 * the session as **completed**, because the status enum offered nothing else.
 *
 * That one word did three things, none of them visible:
 *
 *   - the screen read "Completed" against blank Plug-In and Plug-Out columns;
 *   - "Ready to Bill" counted sessions that can never be billed;
 *   - `ReeferBillingService` selected them on `status = completed` and then
 *     dropped them, because it returns null without both timestamps — so the
 *     container simply never appeared on the invoice, with nothing said.
 *
 * The yard found it by noticing containers missing from an electricity bill.
 */
class ReeferSessionNeverPluggedTest extends FeatureTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-09 12:00:00');
        $this->customer = Customer::factory()->create();
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── What gate-out records ───────────────────────────────────────────────

    public function test_a_session_never_plugged_in_is_not_called_completed(): void
    {
        $this->gateInLadenReefer('NPLG1234567');
        $session = $this->sessionFor('NPLG1234567');
        $this->assertSame('pending', $session->status, 'Gate-in should open it pending.');

        $this->gateOut('NPLG1234567', '2026-09-09 10:00:00');

        $session->refresh();
        $this->assertSame('not_plugged', $session->status);
        $this->assertNull($session->plug_in_at);
        $this->assertNull($session->plug_out_at);
    }

    /** A session that did run still closes as completed, with its plug-out. */
    public function test_a_session_that_was_plugged_in_still_completes(): void
    {
        $this->gateInLadenReefer('PLGD1234567');
        $session = $this->sessionFor('PLGD1234567');

        $session->update(['status' => 'active', 'plug_in_at' => '2026-09-08 08:00:00']);

        $this->gateOut('PLGD1234567', '2026-09-09 10:00:00');

        $session->refresh();
        $this->assertSame('completed', $session->status);
        $this->assertNotNull($session->plug_out_at);
    }

    // ── What the screen counts ──────────────────────────────────────────────

    /**
     * "Ready to Bill" is read as work waiting to be invoiced, so it must not
     * include sessions no invoice can ever carry.
     */
    public function test_ready_to_bill_counts_only_billable_sessions(): void
    {
        $this->makeSession(['status' => 'completed', 'plug_in_at' => '2026-09-01 08:00:00', 'plug_out_at' => '2026-09-02 08:00:00']);
        $this->makeSession(['status' => 'completed', 'plug_in_at' => null, 'plug_out_at' => null]);
        $this->makeSession(['status' => 'not_plugged']);

        $this->assertSame(1, ReeferPlugSession::unbilled()->count());
    }

    public function test_a_completed_session_missing_a_timestamp_is_not_billable(): void
    {
        $half = $this->makeSession(['status' => 'completed', 'plug_in_at' => '2026-09-01 08:00:00', 'plug_out_at' => null]);

        $this->assertFalse($half->isBillable(), 'Half a session cannot be charged.');
        $this->assertSame(0, ReeferPlugSession::unbilled()->count());
    }

    // ── What the bill picks up ──────────────────────────────────────────────

    /**
     * The symptom the yard reported: a container it expected on the electricity
     * bill and did not find. It must now be absent from the *selection* rather
     * than silently discarded while computing lines.
     */
    public function test_an_unpluggable_session_never_reaches_the_invoice_preview(): void
    {
        $this->makeSession(['status' => 'completed', 'plug_in_at' => '2026-09-01 08:00:00', 'plug_out_at' => '2026-09-02 08:00:00']);
        $this->makeSession(['status' => 'completed', 'plug_in_at' => null, 'plug_out_at' => null]);
        $this->makeSession(['status' => 'not_plugged']);

        $preview = ReeferBillingService::preview(
            $this->customer->id, 'long_term', null, null, 'LKR', 1.0, 0.0, 0.0
        );

        $this->assertCount(1, $preview['lines'], 'Only the session with both timestamps should price.');
    }

    // ── What a deleted gate-out gives back ──────────────────────────────────

    /**
     * Deleting a gate-out puts the container back in the yard, so the plug
     * session that gate-out closed has to come back with it. The plug-in screen
     * accepts `pending` only and the plug-out screen `active` only, so a
     * session left closed is a box sitting in the yard drawing power with no
     * screen willing to record it.
     */
    public function test_deleting_a_gate_out_reopens_a_session_closed_unplugged(): void
    {
        $this->gateInLadenReefer('RSTP1234567');
        $this->gateOut('RSTP1234567', '2026-09-09 10:00:00');

        $this->assertSame('not_plugged', $this->sessionFor('RSTP1234567')->status);

        $this->deleteGateOut('RSTP1234567');

        $session = $this->sessionFor('RSTP1234567');
        $this->assertSame('pending', $session->status, 'It is awaiting a plug-in again.');
        $this->assertNull($session->gate_out_movement_id);
        $this->assertSame('in_yard', Container::where('container_no', 'RSTP1234567')->value('status'));
    }

    /** A session that had run reopens as active, keeping the plug-in it recorded. */
    public function test_deleting_a_gate_out_reopens_a_session_that_had_run(): void
    {
        $this->gateInLadenReefer('RSTA1234567');
        $this->sessionFor('RSTA1234567')->update([
            'status'     => 'active',
            'plug_in_at' => '2026-09-08 09:00:00',
        ]);
        $this->gateOut('RSTA1234567', '2026-09-09 10:00:00');

        $closed = $this->sessionFor('RSTA1234567');
        $this->assertSame('completed', $closed->status);
        $this->assertNotNull($closed->plug_out_at);

        $this->deleteGateOut('RSTA1234567');

        $session = $this->sessionFor('RSTA1234567');
        $this->assertSame('active', $session->status);
        $this->assertNull($session->plug_out_at, 'Only the plug-out is undone.');
        $this->assertSame('2026-09-08 09:00:00', $session->plug_in_at->format('Y-m-d H:i:s'),
            'The recorded plug-in survives the delete.');
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function sessionFor(string $containerNo): ReeferPlugSession
    {
        $container = Container::where('container_no', $containerNo)->firstOrFail();

        return ReeferPlugSession::where('container_id', $container->id)->latest('id')->firstOrFail();
    }

    /**
     * Not `session()` — `Illuminate\Foundation\Testing\TestCase` declares a
     * public helper of that name, and narrowing it to private is a fatal.
     */
    private function makeSession(array $attributes): ReeferPlugSession
    {
        $eqt = $this->reeferType();

        $container = Container::factory()->create([
            'customer_id'       => $this->customer->id,
            'equipment_type_id' => $eqt->id,
            'type_code'         => $eqt->type_code,
            'cargo_status'      => 'laden',
            'status'            => 'in_yard',
        ]);

        return ReeferPlugSession::create(array_merge([
            'container_id'     => $container->id,
            'gate_movement_id' => $this->arrivalFor($container)->id,
            'customer_id'      => $this->customer->id,
            'service_type'     => 'long_term',
            'created_by'       => auth()->id(),
            'updated_by'       => auth()->id(),
        ], $attributes));
    }

    /**
     * `reefer_plug_sessions.gate_movement_id` is NOT NULL — a plug session only
     * exists because a box arrived — so the fixture needs a real arrival rather
     * than a bare session row.
     */
    private function arrivalFor(Container $container): GateMovement
    {
        return GateMovement::create([
            'container_id'    => $container->id,
            'container_no'    => $container->container_no,
            'customer_id'     => $this->customer->id,
            'movement_type'   => 'in',
            'size'            => '40',
            'container_type'  => 'RF',
            'condition'       => 'sound',
            // enum('empty','laden') — migration 000079 replaced 'full'.
            'cargo_status'    => 'laden',
            'gate_in_time'    => '2026-09-01 08:00:00',
            'movement_status' => 'done',
            'created_by'      => auth()->id(),
        ]);
    }

    private function reeferType(): EquipmentType
    {
        return EquipmentType::all()->first(fn ($e) => $e->isReefer())
            ?? $this->fail('No reefer equipment type is seeded.');
    }

    private function gateInLadenReefer(string $containerNo): void
    {
        $jobType = YardJobType::where('movement_direction', 'gate_in')->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')->first();

        $this->from(route('yard.gate'))->post(route('yard.gate.in'), [
            'job_type_id'        => $jobType->id,
            'container_no'       => $containerNo,
            'equipment_type_id'  => $this->reeferType()->id,
            'customer_id'        => $this->customer->id,
            'condition'          => 'sound',
            'cargo_status'       => 'laden',
            'seal_no'            => 'SEAL0001',
            'vehicle_plate'      => 'TRUCK01',
            'gate_in_time'       => '2026-09-08 08:00:00',
            // Operating, so a plug session is opened at all: a NOR deliberately
            // opens none, which is the other half of this problem's answer.
            'reefer_mode'        => 'operating',
            'reefer_service_type' => 'long_term',
        ])->assertSessionHasNoErrors();
    }

    /** Delete the container's newest gate-out, asserting nothing blocked it. */
    private function deleteGateOut(string $containerNo): void
    {
        $container = Container::where('container_no', $containerNo)->firstOrFail();

        $out = GateMovement::where('container_id', $container->id)
            ->where('movement_type', 'out')
            ->latest('id')
            ->firstOrFail();

        $this->delete(route('yard.movements.destroy', $out))
            ->assertSessionMissing('error');
    }

    private function gateOut(string $containerNo, string $at): void
    {
        $this->from(route('yard.gate'))->post(route('yard.gate.out'), [
            'container_no'  => $containerNo,
            'vehicle_plate' => 'ABC1234',
            'driver_name'   => 'Test Driver',
            'driver_ic'     => '900101015555',
            'release_order' => 'RO-NPLG-1',
            'gate_out_time' => $at,
        ])->assertSessionHasNoErrors();
    }
}
