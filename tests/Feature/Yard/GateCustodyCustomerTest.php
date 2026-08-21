<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\YardJob;
use App\Models\YardJobType;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * The party at the gate must be the same on the way out as on the way in.
 *
 * A container has two parties and they are not the same thing: the **owner**,
 * which belongs to the box and changes rarely, and the **customer**, who brought
 * it in this time and takes it out again. The customer belongs to the visit.
 *
 * Gate-out used to read `containers.customer_id` — a field gate-in overwrites
 * every visit and the Container Master screen could change at any time — so a
 * box could leave under a different party than it arrived under, silently. The
 * customer now comes from the visit's YardJob, which both gates share, so the
 * two ends agree by construction rather than by rule.
 */
class GateCustodyCustomerTest extends FeatureTestCase
{
    private Customer $bringer;
    private Customer $other;

    protected function setUp(): void
    {
        parent::setUp();
        // Comfortably after every gate time the helpers use, so a second visit
        // can be recorded without back- or future-dating.
        Carbon::setTestNow('2026-12-10 12:00:00');
        $this->bringer = Customer::factory()->create(['name' => 'Bringer Lines']);
        $this->other   = Customer::factory()->create(['name' => 'Someone Else Ltd']);
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param string $at Gate-in time. Parameterised because a container cannot
     *                   be gated in while an earlier stay is still open — the
     *                   overlap guard rejects it — so a second visit needs
     *                   times after the first one's gate-out.
     */
    private function gateIn(string $no, ?Customer $customer = null, string $at = '2026-12-01 08:00:00'): Container
    {
        $jobType = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')
            ->first();

        $equipment = EquipmentType::all()->first(fn ($e) => ! $e->isReefer())
            ?? EquipmentType::query()->firstOrFail();

        $this->from(route('yard.gate'))->post(route('yard.gate.in'), [
            'job_type_id'       => $jobType->id,
            'container_no'      => $no,
            'equipment_type_id' => $equipment->id,
            'customer_id'       => ($customer ?? $this->bringer)->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
            'gate_in_time'      => $at,
        ])->assertSessionHasNoErrors();

        return Container::where('container_no', $no)->firstOrFail();
    }

    private function gateOut(string $no, string $ro, string $at = '2026-12-02 09:00:00'): void
    {
        $this->from(route('yard.gate'))->post(route('yard.gate.out'), [
            'container_no'  => $no,
            'vehicle_plate' => 'ABC1234',
            'driver_name'   => 'Test Driver',
            'driver_ic'     => '900101015555',
            'release_order' => $ro,
            'gate_out_time' => $at,
        ])->assertSessionHasNoErrors();
    }

    private function movements(string $no): array
    {
        return [
            GateMovement::where('container_no', $no)->where('movement_type', 'in')->latest('id')->first(),
            GateMovement::where('container_no', $no)->where('movement_type', 'out')->latest('id')->first(),
        ];
    }

    // ── The reported defect ──────────────────────────────────────────────────

    /**
     * The headline case: the container master is re-pointed mid-stay — exactly
     * what the Container Master screen used to allow — and the release must
     * still record the party that brought the box in.
     */
    public function test_gate_out_keeps_the_gate_in_customer_even_if_the_master_changes(): void
    {
        $container = $this->gateIn('CUST0000001');

        // Behind the back of the gate, as a master edit or an import would.
        Container::where('id', $container->id)->update(['customer_id' => $this->other->id]);

        $this->gateOut('CUST0000001', 'RO-CUST-1');

        [$in, $out] = $this->movements('CUST0000001');

        $this->assertSame($this->bringer->id, (int) $in->customer_id);
        $this->assertSame($this->bringer->id, (int) $out->customer_id,
            'The box must leave under the party that brought it in, not whatever the master says today.');
    }

    public function test_gate_out_joins_the_gate_ins_visit(): void
    {
        $this->gateIn('CUST0000002');
        $this->gateOut('CUST0000002', 'RO-CUST-2');

        [$in, $out] = $this->movements('CUST0000002');

        $this->assertNotNull($in->yard_job_id, 'Gate-in opens the visit.');
        $this->assertSame((int) $in->yard_job_id, (int) $out->yard_job_id,
            'Both gates belong to one visit — which is what makes pairing exact rather than a time guess.');

        $this->assertSame(
            (int) YardJob::whereKey($in->yard_job_id)->value('customer_id'),
            (int) $out->customer_id,
            'The visit owns the customer; the movements copy it.'
        );
    }

    /** Two visits by different parties keep their own customer. */
    public function test_a_later_visit_by_another_party_does_not_rewrite_the_earlier_one(): void
    {
        // The second stay must start after the first one closes; a container
        // cannot be gated in while an earlier stay is still open.
        $this->gateIn('CUST0000003', null, '2026-12-01 08:00:00');
        $this->gateOut('CUST0000003', 'RO-CUST-3A', '2026-12-02 09:00:00');

        $this->gateIn('CUST0000003', $this->other, '2026-12-05 08:00:00');
        $this->gateOut('CUST0000003', 'RO-CUST-3B', '2026-12-06 09:00:00');

        $ins  = GateMovement::where('container_no', 'CUST0000003')->where('movement_type', 'in')->orderBy('id')->get();
        $outs = GateMovement::where('container_no', 'CUST0000003')->where('movement_type', 'out')->orderBy('id')->get();

        $this->assertCount(2, $ins);
        $this->assertCount(2, $outs);

        $this->assertSame($this->bringer->id, (int) $ins[0]->customer_id);
        $this->assertSame($this->bringer->id, (int) $outs[0]->customer_id, 'The first visit keeps its party.');
        $this->assertSame($this->other->id,   (int) $ins[1]->customer_id);
        $this->assertSame($this->other->id,   (int) $outs[1]->customer_id, 'The second visit has its own.');
    }

    // ── Correcting a mis-keyed customer ──────────────────────────────────────

    public function test_correcting_the_gate_in_customer_moves_the_whole_visit(): void
    {
        $this->gateIn('CUST0000004');
        $this->gateOut('CUST0000004', 'RO-CUST-4');

        [$in] = $this->movements('CUST0000004');

        // The operator picked the wrong party and corrects it after the fact.
        $this->patch(route('yard.movements.update', $in), [
            'customer_id' => $this->other->id,
        ])->assertSessionHasNoErrors();

        [$in, $out] = $this->movements('CUST0000004');

        $this->assertSame($this->other->id, (int) $in->customer_id);
        $this->assertSame($this->other->id, (int) $out->customer_id,
            'Correcting one end used to leave the other behind — that is how they drifted apart.');
        $this->assertSame($this->other->id, (int) YardJob::whereKey($in->yard_job_id)->value('customer_id'),
            'The job is the writer, so it moves too.');
    }

    // ── Owner is a separate thing ────────────────────────────────────────────

    public function test_the_owner_is_independent_of_who_is_at_the_gate(): void
    {
        $container = $this->gateIn('CUST0000005');

        $owner = Customer::factory()->create(['name' => 'Owning Line']);
        $container->forceFill(['owner_customer_id' => $owner->id])->save();

        $this->gateOut('CUST0000005', 'RO-CUST-5');

        [$in, $out] = $this->movements('CUST0000005');
        $container->refresh();

        $this->assertSame($owner->id, (int) $container->owner_customer_id, 'The owner belongs to the box.');
        $this->assertSame($this->bringer->id, (int) $in->customer_id, 'The customer belongs to the visit.');
        $this->assertSame($this->bringer->id, (int) $out->customer_id);
        $this->assertSame('Owning Line', $container->ownerLabel());
    }

    public function test_the_owner_label_falls_back_to_the_recorded_text(): void
    {
        $container = $this->gateIn('CUST0000006');
        $container->forceFill(['owner_customer_id' => null, 'owner_name' => 'Unregistered Line'])->save();

        $this->assertSame('Unregistered Line', $container->refresh()->ownerLabel(),
            'Owners the yard does not trade with keep the text fields.');
    }

    /** The master screen must no longer be able to re-point the current visit. */
    public function test_the_container_master_can_no_longer_change_the_visit_customer(): void
    {
        $container = $this->gateIn('CUST0000007');

        $this->patch(route('containers.update', $container), [
            'container_no' => 'CUST0000007',
            'category'     => 'consignee',
            'customer_id'  => $this->other->id,   // ignored — not a master field
            'owner_name'   => 'Owning Line',
        ])->assertSessionHasNoErrors();

        $container->refresh();

        $this->assertSame($this->bringer->id, (int) $container->customer_id,
            'The customer is visit data; the master screen edits the owner.');
        $this->assertSame('Owning Line', $container->owner_name);
    }
}
