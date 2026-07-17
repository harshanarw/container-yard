<?php

namespace Tests\Feature\Repair;

use App\Models\Container;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\PortalToken;
use Tests\Support\FeatureTestCase;

/**
 * The customer approval (portal) link is available right after an estimate is
 * saved — no email needed — but an explicitly revoked link is not resurrected.
 */
class EstimatePortalLinkTest extends FeatureTestCase
{
    private function makeEstimate(Customer $customer): Estimate
    {
        $container = Container::factory()->create([
            'customer_id' => $customer->id, 'size' => '40', 'type_code' => 'HC',
        ]);

        return Estimate::create([
            'estimate_no'  => 'RE-' . substr(uniqid(), -6),
            'container_id' => $container->id,
            'container_no' => $container->container_no,
            'customer_id'  => $customer->id,
            'size'         => '40', 'type_code' => 'HC',
            'estimate_date'=> now()->toDateString(),
            'valid_until'  => now()->addDays(30)->toDateString(),
            'priority'     => 'normal',
            'status'       => 'draft',
            'currency'     => 'USD', 'exchange_rate' => 1,
            'created_by'   => auth()->id(),
        ]);
    }

    public function test_approval_link_shows_on_the_estimate_view_without_an_email(): void
    {
        $this->actingAsSystemAdmin();

        $customer = Customer::factory()->create(['email' => 'client@example.com']);
        $estimate = $this->makeEstimate($customer);

        // No email sent, no token yet.
        $this->assertSame(0, PortalToken::where('tokenable_id', $estimate->id)->count());

        $this->get(route('estimates.show', $estimate))
            ->assertOk()
            ->assertSee('/portal/estimate/');

        // Viewing created the approval token.
        $token = PortalToken::where('tokenable_type', Estimate::class)
            ->where('tokenable_id', $estimate->id)->whereNull('revoked_at')->first();
        $this->assertNotNull($token, 'An active approval token should exist after viewing.');
    }

    public function test_a_revoked_link_is_not_resurrected_on_view(): void
    {
        $this->actingAsSystemAdmin();

        $customer = Customer::factory()->create(['email' => 'client@example.com']);
        $estimate = $this->makeEstimate($customer);

        // First view creates the token; then the operator revokes it.
        $this->get(route('estimates.show', $estimate))->assertOk();
        PortalToken::where('tokenable_id', $estimate->id)->update(['revoked_at' => now()]);

        // Viewing again must NOT create a fresh active token.
        $this->get(route('estimates.show', $estimate))->assertOk();

        $this->assertSame(
            0,
            PortalToken::where('tokenable_id', $estimate->id)->whereNull('revoked_at')->count(),
            'A revoked approval link must not be auto-recreated on view.'
        );
    }
}
