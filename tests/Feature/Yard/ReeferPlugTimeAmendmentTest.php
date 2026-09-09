<?php

namespace Tests\Feature\Yard;

use App\Models\AuditLog;
use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\ReeferPlugSession;
use App\Services\ReeferBillingService;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Correcting a plug time after it has been recorded.
 *
 * Until this existed the module was a one-way door: recording a plug-in moves a
 * session to `active` and a plug-out moves it to `completed`, and each screen
 * accepts only the status before its own. A mis-keyed time could be changed by
 * nothing short of a direct database edit.
 *
 * The case that made it urgent is `not_plugged` — a reefer that left with no
 * plug-in ever recorded. Those sessions carry no timestamps, so they are
 * unbillable and unreachable at once. Giving them both times promotes them back
 * to `completed`, which is the only thing that puts them back in front of an
 * invoice.
 */
class ReeferPlugTimeAmendmentTest extends FeatureTestCase
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

    // ── The ordinary correction ─────────────────────────────────────────────

    public function test_a_completed_session_can_have_both_times_amended(): void
    {
        $session = $this->makeSession([
            'status'      => 'completed',
            'plug_in_at'  => '2026-09-02 08:00:00',
            'plug_out_at' => '2026-09-04 08:00:00',
        ]);

        $this->amend($session, [
            'plug_in_at'  => '2026-09-02 06:30',
            'plug_out_at' => '2026-09-04 17:45',
            'reason'      => 'Times corrected from the reefer log sheet.',
        ])->assertSessionHasNoErrors();

        $session->refresh();
        $this->assertSame('2026-09-02 06:30:00', $session->plug_in_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-04 17:45:00', $session->plug_out_at->format('Y-m-d H:i:s'));
        $this->assertSame('completed', $session->status);
    }

    /** An active box has not come off power, so only its plug-in is in play. */
    public function test_an_active_session_amends_its_plug_in_and_stays_active(): void
    {
        $session = $this->makeSession(
            ['status' => 'active', 'plug_in_at' => '2026-09-02 08:00:00'],
            ['out' => null],
        );

        $this->amend($session, [
            'plug_in_at' => '2026-09-01 09:15',
            // Sent anyway, to prove it is ignored rather than quietly accepted.
            'plug_out_at' => '2026-09-03 09:00',
            'reason'      => 'Plug-in was keyed a day late.',
        ])->assertSessionHasNoErrors();

        $session->refresh();
        $this->assertSame('2026-09-01 09:15:00', $session->plug_in_at->format('Y-m-d H:i:s'));
        $this->assertSame('active', $session->status);
        $this->assertNull($session->plug_out_at, 'The plug-out screen records a plug-out, not this one.');
    }

    // ── The case this was built for ─────────────────────────────────────────

    public function test_a_not_plugged_session_given_both_times_becomes_billable(): void
    {
        $session = $this->makeSession(['status' => 'not_plugged']);

        $this->assertSame(0, ReeferPlugSession::unbilled()->count());

        $this->amend($session, [
            'plug_in_at'  => '2026-09-01 08:00',
            'plug_out_at' => '2026-09-05 10:00',
            'reason'      => 'Plugged for the whole visit; plug-in was never recorded at the time.',
        ])->assertSessionHasNoErrors();

        $session->refresh();
        $this->assertSame('completed', $session->status);
        $this->assertTrue($session->isBillable());
        $this->assertSame(1, ReeferPlugSession::unbilled()->count());
    }

    /** The point of the promotion: the session now reaches the invoice. */
    public function test_an_amended_session_reaches_the_invoice_preview(): void
    {
        $session = $this->makeSession(['status' => 'not_plugged']);

        $before = ReeferBillingService::preview($this->customer->id, 'long_term', null, null, 'LKR', 1.0, 0.0, 0.0);
        $this->assertCount(0, $before['lines'], 'Unbillable before the amendment.');

        $this->amend($session, [
            'plug_in_at'  => '2026-09-01 08:00',
            'plug_out_at' => '2026-09-05 10:00',
            'reason'      => 'Times reconstructed from the reefer log sheet.',
        ])->assertSessionHasNoErrors();

        $after = ReeferBillingService::preview($this->customer->id, 'long_term', null, null, 'LKR', 1.0, 0.0, 0.0);
        $this->assertCount(1, $after['lines']);
        $this->assertGreaterThan(0, $after['lines'][0]['subtotal'], 'It prices at more than nothing.');
    }

    /** A re-amendment prices at the new figure, not the one previewed before it. */
    public function test_a_second_amendment_reprices(): void
    {
        $session = $this->makeSession([
            'status'      => 'completed',
            'plug_in_at'  => '2026-09-04 08:00:00',
            'plug_out_at' => '2026-09-05 08:00:00',
        ]);

        $first = ReeferBillingService::preview($this->customer->id, 'long_term', null, null, 'LKR', 1.0, 0.0, 0.0);

        $this->amend($session, [
            'plug_in_at'  => '2026-09-01 08:00',
            'plug_out_at' => '2026-09-05 08:00',
            'reason'      => 'Plug-in was recorded three days late.',
        ])->assertSessionHasNoErrors();

        $second = ReeferBillingService::preview($this->customer->id, 'long_term', null, null, 'LKR', 1.0, 0.0, 0.0);

        $this->assertGreaterThan(
            $first['lines'][0]['subtotal'],
            $second['lines'][0]['subtotal'],
            'A longer session costs more; the preview must use the amended times.',
        );
    }

    // ── What it refuses ─────────────────────────────────────────────────────

    public function test_a_billed_session_refuses_and_names_the_route_back(): void
    {
        $session = $this->makeSession([
            'status'      => 'billed',
            'plug_in_at'  => '2026-09-02 08:00:00',
            'plug_out_at' => '2026-09-04 08:00:00',
        ]);

        $this->get(route('yard.reefer.amend', $session))
            ->assertRedirect(route('yard.reefer.show', $session))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Cancel the electricity invoice'));

        // And the POST is guarded too, not just the screen that leads to it.
        $this->amend($session, [
            'plug_in_at'  => '2026-09-01 08:00',
            'plug_out_at' => '2026-09-05 08:00',
            'reason'      => 'Trying to go round the front door.',
        ])->assertSessionHas('error');

        $session->refresh();
        $this->assertSame('2026-09-02 08:00:00', $session->plug_in_at->format('Y-m-d H:i:s'));
    }

    public function test_a_pending_session_is_sent_to_the_plug_in_screen(): void
    {
        $session = $this->makeSession(['status' => 'pending']);

        $this->get(route('yard.reefer.amend', $session))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Record Plug-In'));
    }

    // ── The five validation rules, each by name ─────────────────────────────

    /** Rule 1: plug-out strictly after plug-in - equal times bill nothing. */
    public function test_it_rejects_a_plug_out_at_or_before_the_plug_in(): void
    {
        $session = $this->makeSession(['status' => 'not_plugged']);

        $this->amend($session, [
            'plug_in_at'  => '2026-09-03 08:00',
            'plug_out_at' => '2026-09-03 08:00',
            'reason'      => 'Equal times are almost always a mis-key.',
        ])->assertSessionHasErrors('plug_out_at');

        $this->amend($session, [
            'plug_in_at'  => '2026-09-03 08:00',
            'plug_out_at' => '2026-09-02 08:00',
            'reason'      => 'Out before in.',
        ])->assertSessionHasErrors('plug_out_at');
    }

    /** Rule 2: neither time in the future. */
    public function test_it_rejects_a_time_in_the_future(): void
    {
        $session = $this->makeSession(['status' => 'active', 'plug_in_at' => '2026-09-02 08:00:00'], ['out' => null]);

        $this->amend($session, [
            'plug_in_at' => '2026-09-10 08:00',
            'reason'     => 'Tomorrow has not happened yet.',
        ])->assertSessionHasErrors('plug_in_at');
    }

    /** Rule 3: a reefer cannot be plugged in before it arrives. */
    public function test_it_rejects_a_plug_in_before_the_container_arrived(): void
    {
        $session = $this->makeSession(['status' => 'not_plugged']);

        $this->amend($session, [
            'plug_in_at'  => '2026-08-30 08:00',   // gate-in is 2026-09-01 08:00
            'plug_out_at' => '2026-09-05 08:00',
            'reason'      => 'Before the box was even here.',
        ])->assertSessionHasErrors('plug_in_at');
    }

    /** Rule 4: it cannot draw power after it leaves. */
    public function test_it_rejects_a_plug_out_after_the_container_left(): void
    {
        $session = $this->makeSession(['status' => 'not_plugged']);

        $this->amend($session, [
            'plug_in_at'  => '2026-09-01 08:00',
            'plug_out_at' => '2026-09-07 08:00',   // gate-out is 2026-09-05 10:00
            'reason'      => 'After the box had gone.',
        ])->assertSessionHasErrors('plug_out_at');
    }

    /** Rule 5: a container still in the yard cannot plug out later than now. */
    public function test_it_rejects_a_plug_out_beyond_now_for_a_container_still_in_the_yard(): void
    {
        $session = $this->makeSession(
            ['status' => 'completed', 'plug_in_at' => '2026-09-02 08:00:00', 'plug_out_at' => '2026-09-03 08:00:00'],
            ['out' => null],
        );

        $this->amend($session, [
            'plug_in_at'  => '2026-09-02 08:00',
            'plug_out_at' => '2026-09-09 18:00',   // now is 12:00
            'reason'      => 'Still in the yard, so this has not happened.',
        ])->assertSessionHasErrors('plug_out_at');
    }

    public function test_a_reason_is_required(): void
    {
        $session = $this->makeSession(['status' => 'not_plugged']);

        $this->amend($session, [
            'plug_in_at'  => '2026-09-01 08:00',
            'plug_out_at' => '2026-09-05 08:00',
        ])->assertSessionHasErrors('reason');

        $session->refresh();
        $this->assertSame('not_plugged', $session->status, 'Nothing is written without one.');
    }

    // ── The audit trail ─────────────────────────────────────────────────────

    public function test_the_old_values_and_the_reason_reach_the_audit_log(): void
    {
        $session = $this->makeSession([
            'status'      => 'completed',
            'plug_in_at'  => '2026-09-02 08:00:00',
            'plug_out_at' => '2026-09-04 08:00:00',
        ]);

        $this->amend($session, [
            'plug_in_at'  => '2026-09-01 09:00',
            'plug_out_at' => '2026-09-05 09:00',
            'reason'      => 'Corrected against the reefer log sheet.',
        ])->assertSessionHasNoErrors();

        $entry = AuditLog::where('event', 'amended')
            ->where('subject_id', $session->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($entry, 'An amendment must be auditable.');
        $this->assertStringContainsString('Corrected against the reefer log sheet.', $entry->description);
        $this->assertStringContainsString('02 Sep 2026 08:00', $entry->description, 'The old plug-in is named.');

        // The observer records the new values on its own, as a separate entry.
        $this->assertTrue(
            AuditLog::where('subject_id', $session->id)->where('event', 'updated')->exists(),
            'The before/after diff comes from the observer.',
        );
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /**
     * A session sitting inside a visit: gate-in 01 Sep 08:00, gate-out 05 Sep
     * 10:00, which is the window rules 3 and 4 are measured against. Pass
     * `['out' => null]` for a container still in the yard.
     */
    private function makeSession(array $attributes, array $window = []): ReeferPlugSession
    {
        $eqt = $this->reeferType();

        $container = Container::factory()->create([
            'customer_id'       => $this->customer->id,
            'equipment_type_id' => $eqt->id,
            'type_code'         => $eqt->type_code,
            'cargo_status'      => 'laden',
            'status'            => 'in_yard',
        ]);

        $in  = $window['in']  ?? '2026-09-01 08:00:00';
        $out = array_key_exists('out', $window) ? $window['out'] : '2026-09-05 10:00:00';

        return ReeferPlugSession::create(array_merge([
            'container_id'         => $container->id,
            'gate_movement_id'     => $this->movement($container, 'in', $in)->id,
            'gate_out_movement_id' => $out ? $this->movement($container, 'out', $out)->id : null,
            'customer_id'          => $this->customer->id,
            'service_type'         => 'long_term',
            'created_by'           => auth()->id(),
            'updated_by'           => auth()->id(),
        ], $attributes));
    }

    private function movement(Container $container, string $direction, string $at): GateMovement
    {
        return GateMovement::create([
            'container_id'    => $container->id,
            'container_no'    => $container->container_no,
            'customer_id'     => $this->customer->id,
            'movement_type'   => $direction,
            'size'            => '40',
            'container_type'  => 'RF',
            'condition'       => 'sound',
            // enum('empty','laden') - migration 000079 replaced 'full'.
            'cargo_status'    => 'laden',
            'gate_in_time'    => $direction === 'in'  ? $at : null,
            'gate_out_time'   => $direction === 'out' ? $at : null,
            'movement_status' => 'done',
            'created_by'      => auth()->id(),
        ]);
    }

    private function reeferType(): EquipmentType
    {
        return EquipmentType::all()->first(fn ($e) => $e->isReefer())
            ?? $this->fail('No reefer equipment type is seeded.');
    }

    private function amend(ReeferPlugSession $session, array $payload)
    {
        return $this->from(route('yard.reefer.amend', $session))
            ->post(route('yard.reefer.store-amend', $session), $payload);
    }
}
