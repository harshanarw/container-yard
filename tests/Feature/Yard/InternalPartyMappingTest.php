<?php

namespace Tests\Feature\Yard;

use App\Models\CompanySetting;
use App\Models\Container;
use App\Models\Customer;
use App\Models\YardJob;
use App\Services\InternalPartyService;
use App\Services\LessorOnHireService;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Which contact represents the yard itself.
 *
 * A lease-in is the one job where the counterparty and the holder are different
 * parties: the shipping line is still who the agreement is with and who
 * invoices, but for the length of the lease the yard holds the box. So the yard
 * has to be nameable, and the service has been creating a `Customer` with the
 * reserved code `SELF` to do it.
 *
 * Creating one was the right fallback and the wrong default. A yard that has
 * been running for years usually already has a contact for itself — internal
 * storage, own-container work, inter-company billing — and a second record for
 * the same company means its name appears twice in every picker and its history
 * is split across two ledgers.
 *
 * So the mapping has to work on an installation that is **already running**:
 * pointing it at the contact they had all along must take the existing on-hire
 * history with it, not just change the answer for future leases.
 */
class InternalPartyMappingTest extends FeatureTestCase
{
    private Customer $line;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-20 10:00:00');
        $this->actingAsSystemAdmin();

        $this->line = Customer::factory()->create(['name' => 'Maersk Line']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Unmapped: today's behaviour, unchanged ──────────────────────────────

    public function test_with_nothing_mapped_a_placeholder_is_created_on_first_use(): void
    {
        $this->assertNull(InternalPartyService::existing(), 'Nothing exists until it is needed.');

        $yard = InternalPartyService::customer();

        $this->assertSame(InternalPartyService::CODE, $yard->code);
        $this->assertTrue(InternalPartyService::isInternal($yard));
    }

    public function test_the_placeholder_is_created_once(): void
    {
        InternalPartyService::customer();
        InternalPartyService::customer();

        $this->assertSame(1, Customer::where('code', InternalPartyService::CODE)->count());
    }

    /** Asking which contact represents the yard must not create one. */
    public function test_existing_never_creates_a_record(): void
    {
        $before = Customer::count();

        $this->assertNull(InternalPartyService::existing());
        $this->assertSame($before, Customer::count());
    }

    // ── Mapping an existing contact ─────────────────────────────────────────

    public function test_a_mapped_contact_is_used_instead_of_the_placeholder(): void
    {
        $own = $this->mapTo(Customer::factory()->create(['name' => 'ACDO Depot (Pvt) Ltd']));

        $this->assertSame($own->id, InternalPartyService::customerId());
        $this->assertSame(0, Customer::where('code', InternalPartyService::CODE)->count(),
            'No second record for the same company.');
    }

    /**
     * A mapped contact has an ordinary code, so asking only about `SELF` would
     * report the yard's own record as an external party.
     */
    public function test_a_mapped_contact_reads_as_internal(): void
    {
        $own = $this->mapTo(Customer::factory()->create(['code' => 'ACDO']));

        $this->assertTrue(InternalPartyService::isInternal($own));
        $this->assertFalse(InternalPartyService::isInternal($this->line));
        $this->assertFalse(InternalPartyService::isInternal(null));
    }

    public function test_mapping_tags_the_contact_as_internal(): void
    {
        $own = $this->mapTo(Customer::factory()->create());

        InternalPartyService::customer();

        $this->assertTrue($own->fresh()->types->contains('name', InternalPartyService::TYPE));
    }

    // ── The installation that is already running ────────────────────────────

    /**
     * The case this whole change exists for. The yard has been leasing
     * containers in for months against the auto-created placeholder; the
     * operator now maps the contact they had all along.
     */
    public function test_mapping_moves_the_existing_on_hire_history(): void
    {
        $lease = $this->lease();
        $this->assertSame(InternalPartyService::CODE, $lease->yardJob->holder()->code);

        $own = Customer::factory()->create(['name' => 'ACDO Depot (Pvt) Ltd']);

        $this->saveSettings(['internal_customer_id' => $own->id])
            ->assertSessionHas('success');

        $this->assertSame($own->id, $lease->fresh()->yardJob->held_by_customer_id,
            'The past leases move with the identity, not just the future ones.');
        $this->assertTrue(InternalPartyService::isInternal($lease->fresh()->yardJob->holder()));
    }

    public function test_the_previous_contact_stops_reading_as_the_yard(): void
    {
        $this->lease();
        $placeholder = Customer::where('code', InternalPartyService::CODE)->firstOrFail();

        $own = Customer::factory()->create();
        $this->saveSettings(['internal_customer_id' => $own->id]);

        $this->assertFalse($placeholder->fresh()->types->contains('name', InternalPartyService::TYPE),
            'Two records describing themselves as this company is worse than none.');
    }

    public function test_the_operator_is_told_how_much_moved(): void
    {
        $this->lease();

        $this->saveSettings(['internal_customer_id' => Customer::factory()->create()->id]);

        $this->assertStringContainsString('1 on-hire job moved', session('success'));
    }

    /**
     * Narrowed to lease-in jobs on purpose. A mapped contact can be an ordinary
     * customer that genuinely rents containers, and a blanket rewrite of
     * `held_by_customer_id` would move its real rentals too.
     */
    public function test_a_rental_held_by_the_same_contact_is_not_moved(): void
    {
        $this->lease();

        $own    = Customer::factory()->create();
        $rental = $this->reletJob($own);

        // Map, then clear — the round trip that would drag an unrelated rental
        // back to the placeholder if the repoint were not narrowed.
        $this->saveSettings(['internal_customer_id' => $own->id]);
        $this->saveSettings(['internal_customer_id' => '']);

        $this->assertSame($own->id, $rental->fresh()->held_by_customer_id,
            'Their own rental is theirs, whatever the mapping does.');
    }

    /** Saving the page without touching the picker changes nothing. */
    public function test_an_unrelated_save_does_not_create_a_placeholder(): void
    {
        $this->saveSettings(['internal_customer_id' => '']);

        $this->assertSame(0, Customer::where('code', InternalPartyService::CODE)->count(),
            'A yard that never leases a container should never acquire the record.');
    }

    /** New leases use the mapped contact from then on. */
    public function test_a_lease_opened_after_mapping_uses_the_mapped_contact(): void
    {
        $own = $this->mapTo(Customer::factory()->create());

        $this->assertSame($own->id, $this->lease()->yardJob->held_by_customer_id);
    }

    // ── It stays out of the pickers ─────────────────────────────────────────

    public function test_the_placeholder_is_hidden_from_customer_pickers(): void
    {
        $placeholder = InternalPartyService::customer();

        $this->assertNotContains(
            $placeholder->id,
            Customer::selectable()->pluck('id')->all(),
            'Nobody gates a container in for the yard itself.',
        );
    }

    /** A contact the operator mapped is one they already trade with. */
    public function test_a_mapped_contact_stays_in_the_pickers(): void
    {
        $own = $this->mapTo(Customer::factory()->create());

        $this->assertContains($own->id, Customer::selectable()->pluck('id')->all());
    }

    /**
     * `code != 'SELF'` is unknown, not true, for a row whose code is null — so
     * without the null arm every contact without a code vanishes from every
     * dropdown in the system.
     */
    public function test_contacts_without_a_code_are_still_selectable(): void
    {
        $plain = Customer::factory()->create(['code' => null]);

        $this->assertContains($plain->id, Customer::selectable()->pluck('id')->all());
    }

    // ── It cannot be deleted out from under the history ─────────────────────

    /**
     * It holds no containers, so the existing guard let it through — and
     * `held_by_customer_id` is nullOnDelete while `holder()` falls back to the
     * counterparty. Deleting it turned every lease-in from *held by the yard*
     * into *held by the shipping line*, across the whole history, silently.
     */
    public function test_the_yards_own_contact_cannot_be_deleted(): void
    {
        $yard = InternalPartyService::customer();

        $this->delete(route('customers.destroy', $yard))->assertSessionHas('error');

        $this->assertNotNull($yard->fresh());
    }

    public function test_a_mapped_contact_cannot_be_deleted_either(): void
    {
        $own = $this->mapTo(Customer::factory()->create());

        $this->delete(route('customers.destroy', $own))->assertSessionHas('error');

        $this->assertNotNull($own->fresh());
    }

    public function test_an_ordinary_contact_can_still_be_deleted(): void
    {
        $other = Customer::factory()->create();

        $this->delete(route('customers.destroy', $other));

        $this->assertNull($other->fresh());
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /** A re-let job this contact genuinely holds as a renting customer. */
    private function reletJob(Customer $renter): YardJob
    {
        $type = \App\Models\YardJobType::where('job_type_code', 'CONTAINER_RELET')->firstOrFail();

        ['job_no' => $no, 'job_seq' => $seq] = YardJob::generateJobNo($type);

        return YardJob::create([
            'job_no'              => $no,
            'job_seq'             => $seq,
            'job_type_id'         => $type->id,
            'job_type_code'       => $type->job_type_code,
            'type_short_code'     => $type->type_short_code,
            'customer_id'         => $renter->id,
            'held_by_customer_id' => $renter->id,
            'status'              => 'open',
            'started_at'          => '2026-09-25 08:00:00',
            'created_by'          => auth()->id(),
        ]);
    }

    private function mapTo(Customer $customer): Customer
    {
        CompanySetting::current()->update(['internal_customer_id' => $customer->id]);
        CompanySetting::flushCache();

        return $customer;
    }

    /** Post the main settings form, which owns the picker. */
    private function saveSettings(array $fields): \Illuminate\Testing\TestResponse
    {
        $settings = CompanySetting::current();

        return $this->from(route('settings.company.index'))
            ->post(route('settings.company.update'), array_merge([
                'company_name' => $settings->company_name ?: 'Test Yard',
            ], $fields));
    }

    private function lease(): \App\Models\LessorOnHire
    {
        $container = Container::factory()->create([
            'customer_id' => $this->line->id,
            'status'      => 'in_yard',
        ]);

        return app(LessorOnHireService::class)->onHireInYard(
            $container,
            ['lessor_id' => $this->line->id, 'on_hire_date' => '2026-09-21'],
            auth()->id(),
        );
    }
}
