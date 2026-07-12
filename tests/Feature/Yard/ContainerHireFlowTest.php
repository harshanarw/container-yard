<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\ContainerHire;
use App\Models\Customer;
use App\Models\YardStorage;
use Tests\Support\FeatureTestCase;

/**
 * End-to-end cover for the container hire lifecycle and its billing linkage.
 *
 * Hire billing is modelled as a YardStorage split (there is no separate hire
 * invoice): on-hire closes the original customer's storage and opens a
 * hire-period record; off-hire closes that and resumes the original customer.
 * An active hire also blocks gate-out (releasability), which we assert.
 */
class ContainerHireFlowTest extends FeatureTestCase
{
    public function test_on_hire_then_off_hire_splits_storage_and_completes(): void
    {
        $this->actingAsSystemAdmin();

        $owner     = Customer::factory()->create();
        $hirer     = Customer::factory()->create();
        $container = Container::factory()->create([
            'customer_id' => $owner->id,
            'status'      => 'in_yard',
        ]);

        // The open 'normal' storage the real flow creates at gate-in.
        $original = YardStorage::create([
            'container_id'  => $container->id,
            'customer_id'   => $owner->id,
            'gate_in_date'  => now()->subDays(10)->toDateString(),
            'gate_out_date' => null,
            'free_days'     => 0,
            'daily_rate'    => 0,
            'hire_type'     => 'normal',
        ]);

        // ── On-hire ──
        $this->from(route('yard.hires.create'))
            ->post(route('yard.hires.store'), [
                'container_id'     => $container->id,
                'on_hire_date'     => now()->subDays(5)->toDateString(),
                'hire_customer_id' => $hirer->id,
                'hire_reference'   => 'HIRE-TEST-1',
                'on_hire_notes'    => '',
            ])->assertSessionHasNoErrors();

        $hire = ContainerHire::where('container_id', $container->id)->latest('id')->first();
        $this->assertNotNull($hire, 'Hire was not created.');
        $this->assertSame('active', $hire->status);
        $this->assertSame($hirer->id, $hire->hire_customer_id);
        $this->assertSame($owner->id, $hire->original_customer_id);

        // Original customer's storage closed; a hire-period storage opened.
        $original->refresh();
        $this->assertNotNull($original->gate_out_date, 'Original storage was not closed at hire start.');
        $this->assertDatabaseHas('yard_storage', [
            'container_id'  => $container->id,
            'hire_type'     => 'on_hire',
            'gate_out_date' => null,
        ]);

        // An active hire blocks gate-out.
        $this->assertTrue($container->fresh()->activeHire()->exists(), 'Active hire should exist.');

        // ── Off-hire ──
        $this->from(route('yard.hires.off-hire', $hire))
            ->post(route('yard.hires.off-hire.process', $hire), [
                'off_hire_date'  => now()->subDay()->toDateString(),
                'off_hire_notes' => '',
            ])->assertSessionHasNoErrors();

        $hire->refresh();
        $this->assertSame('completed', $hire->status);
        $this->assertNotNull($hire->off_hire_date);

        // A resumed storage for the original customer was opened.
        $this->assertDatabaseHas('yard_storage', [
            'container_id'  => $container->id,
            'customer_id'   => $owner->id,
            'hire_type'     => 'resumed',
            'gate_out_date' => null,
        ]);

        // Hire is no longer active → gate-out unblocked again.
        $this->assertFalse($container->fresh()->activeHire()->exists(), 'Hire should no longer be active.');
    }
}
