<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\GateMovement;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * The gate date on the movement edit screen follows `yard.backdate`, the same
 * ability the save checks.
 *
 * The screen used to unlock the field only for the `administrator` role
 * (`isAdmin()`), so a System Administrator, who holds every ability, and any
 * role granted `yard.backdate`, saw it read-only and labelled "Admin only",
 * while the save itself would have accepted their change.
 */
class MovementEditBackdateTest extends FeatureTestCase
{
    private function arrival(): GateMovement
    {
        $box = Container::factory()->create(['status' => 'in_yard']);

        return GateMovement::create([
            'container_id'   => $box->id,
            'container_no'   => $box->container_no,
            'customer_id'    => $box->customer_id,
            'movement_type'  => 'in',
            'size'           => $box->size,
            'container_type' => $box->type_code,
            'cargo_status'   => 'empty',
            'gate_in_time'   => Carbon::now()->subDay()->setTime(9, 0),
            'created_by'     => auth()->id(),
        ]);
    }

    /** The rendered `<input … id="editGateInTime" …>` tag. */
    private function dateInputTag(string $html): string
    {
        $this->assertSame(1, preg_match('/<input[^>]*id="editGateInTime"[^>]*>/s', $html, $m), 'Date field not rendered.');

        return $m[0];
    }

    public function test_a_system_administrator_can_edit_the_gate_date(): void
    {
        $admin = $this->actingAsSystemAdmin();
        $this->assertFalse($admin->isAdmin()); // precondition: not the `administrator` role
        $movement = $this->arrival();

        $html = $this->get(route('yard.movements.edit', $movement))->assertOk()->getContent();

        $this->assertStringNotContainsString('readonly', $this->dateInputTag($html));
        $this->assertStringNotContainsString('Backdate access only', $html);

        $newTime = Carbon::now()->subDays(3)->setTime(14, 30);
        $this->from(route('yard.movements.edit', $movement))
            ->patch(route('yard.movements.update', $movement), ['gate_in_time' => $newTime->format('Y-m-d H:i')])
            ->assertSessionHasNoErrors();

        $this->assertSame($newTime->format('Y-m-d H:i'), $movement->fresh()->gate_in_time->format('Y-m-d H:i'));
    }

    public function test_a_role_granted_backdate_can_edit_the_gate_date(): void
    {
        $user = $this->actingAsRole('yard_supervisor'); // yard.* includes yard.backdate
        $this->assertTrue($user->can('yard.backdate'));
        $this->assertFalse($user->isAdmin());

        $html = $this->get(route('yard.movements.edit', $this->arrival()))->assertOk()->getContent();

        $this->assertStringNotContainsString('readonly', $this->dateInputTag($html));
    }

    public function test_a_role_without_backdate_sees_the_date_locked(): void
    {
        // A role that may edit movements but was not granted backdating.
        $role = \App\Models\Role::create(['name' => 'movement_editor_test', 'display_name' => 'Movement editor (test)']);
        $role->syncPermissions(['yard.view', 'yard.movement-edit']);

        $user = $this->actingAsRole('movement_editor_test');
        $this->assertTrue($user->can('yard.movement-edit'));
        $this->assertFalse($user->can('yard.backdate'));

        $html = $this->get(route('yard.movements.edit', $this->arrival()))->assertOk()->getContent();
        $this->assertStringContainsString('readonly', $this->dateInputTag($html));
        $this->assertStringContainsString('Backdate access only', $html);
    }
}
