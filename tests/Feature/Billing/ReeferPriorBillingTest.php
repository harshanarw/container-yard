<?php

namespace Tests\Feature\Billing;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\ReeferElectricityInvoice;
use App\Models\ReeferElectricityInvoiceLine;
use App\Models\ReeferPlugSession;
use App\Services\Billing\ReeferPriorBilling;
use Tests\Support\FeatureTestCase;

/**
 * The ledger of days a plug session has already been invoiced for.
 *
 * Reefer power is a continuing service, so `status = 'billed'` on the session --
 * one flag, one shot -- cannot describe a container billed for March and still
 * running in April. What can is a record of the days each invoice charged,
 * subtracted from the days being billed now.
 *
 * The rules here are the same ones storage billing settled on, and they are not
 * arbitrary: a **draft** reserves its days so two operators cannot both bill the
 * same month, and cancelling releases them so cancel-and-re-raise stays the way
 * to correct a bill.
 */
class ReeferPriorBillingTest extends FeatureTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::factory()->create();
        $this->actingAsSystemAdmin();
    }

    public function test_a_session_never_invoiced_has_nothing_billed(): void
    {
        $session = $this->makeSession();

        $ledger = ReeferPriorBilling::for([$session->id]);

        $this->assertSame([], $ledger->billedIntervals($session->id));
        $this->assertSame(
            [['2026-03-01', '2026-03-31']],
            $ledger->unbilled($session->id, '2026-03-01', '2026-03-31'),
            'With nothing billed, the whole period is owed.',
        );
    }

    /** @param string[] $statuses */
    public function test_draft_issued_and_paid_invoices_all_reserve_their_days(): void
    {
        foreach (['draft', 'issued', 'paid'] as $status) {
            $session = $this->makeSession();
            $this->line($session, '2026-03-01', '2026-03-15', $status);

            $ledger = ReeferPriorBilling::for([$session->id]);

            $this->assertSame(
                [['2026-03-01', '2026-03-15']],
                $ledger->billedIntervals($session->id),
                "A {$status} invoice must reserve its days.",
            );
        }
    }

    public function test_a_cancelled_invoice_releases_its_days(): void
    {
        $session = $this->makeSession();
        $this->line($session, '2026-03-01', '2026-03-15', 'cancelled');

        $ledger = ReeferPriorBilling::for([$session->id]);

        $this->assertSame([], $ledger->billedIntervals($session->id));
        $this->assertSame(
            [['2026-03-01', '2026-03-31']],
            $ledger->unbilled($session->id, '2026-03-01', '2026-03-31'),
            'Cancelling is what makes cancel-and-re-raise work.',
        );
    }

    public function test_the_invoice_being_edited_does_not_subtract_its_own_days(): void
    {
        $session = $this->makeSession();
        $line    = $this->line($session, '2026-03-01', '2026-03-31', 'draft');

        $ledger = ReeferPriorBilling::for([$session->id], $line->reefer_electricity_invoice_id);

        $this->assertSame([], $ledger->billedIntervals($session->id),
            'An edit that subtracted its own days would leave nothing to bill.');
    }

    /** Adjacent months fuse: 1-15 and 16-31 leave no gap between the 15th and 16th. */
    public function test_touching_intervals_are_merged(): void
    {
        $session = $this->makeSession();
        $this->line($session, '2026-03-16', '2026-03-31', 'issued');
        $this->line($session, '2026-03-01', '2026-03-15', 'issued');

        $ledger = ReeferPriorBilling::for([$session->id]);

        $this->assertSame([['2026-03-01', '2026-03-31']], $ledger->billedIntervals($session->id));
        $this->assertTrue($ledger->nothingLeft($session->id, '2026-03-01', '2026-03-31'));
    }

    public function test_it_reports_only_the_days_not_yet_billed(): void
    {
        $session = $this->makeSession();
        $this->line($session, '2026-03-01', '2026-03-10', 'issued');

        $ledger = ReeferPriorBilling::for([$session->id]);

        $this->assertSame(
            [['2026-03-11', '2026-03-31']],
            $ledger->unbilled($session->id, '2026-03-01', '2026-03-31'),
        );
    }

    /** A line predating the billed_from column, and not backfilled, is not a claim on any day. */
    public function test_a_line_with_no_billed_window_is_ignored(): void
    {
        $session = $this->makeSession();
        $line    = $this->line($session, '2026-03-01', '2026-03-15', 'issued');
        $line->update(['billed_from' => null, 'billed_to' => null]);

        $ledger = ReeferPriorBilling::for([$session->id]);

        $this->assertSame([], $ledger->billedIntervals($session->id));
    }

    public function test_sessions_are_kept_apart(): void
    {
        $a = $this->makeSession();
        $b = $this->makeSession();
        $this->line($a, '2026-03-01', '2026-03-31', 'issued');

        $ledger = ReeferPriorBilling::for([$a->id, $b->id]);

        $this->assertTrue($ledger->nothingLeft($a->id, '2026-03-01', '2026-03-31'));
        $this->assertFalse($ledger->nothingLeft($b->id, '2026-03-01', '2026-03-31'),
            "One container's bill must not silence another's.");
    }

    public function test_an_empty_id_list_queries_nothing(): void
    {
        $ledger = ReeferPriorBilling::for([]);

        $this->assertSame([], $ledger->billedIntervals(1));
        $this->assertSame([], $ledger->billedIntervals(null));
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /**
     * Not `session()`: Illuminate\Foundation\Testing\TestCase declares a public
     * helper of that name, and narrowing it to private is a fatal.
     */
    private function makeSession(): ReeferPlugSession
    {
        $eqt = EquipmentType::all()->first(fn ($e) => $e->isReefer())
            ?? $this->fail('No reefer equipment type is seeded.');

        $container = Container::factory()->create([
            'customer_id'       => $this->customer->id,
            'equipment_type_id' => $eqt->id,
            'type_code'         => $eqt->type_code,
            'cargo_status'      => 'laden',
            'status'            => 'in_yard',
        ]);

        $movement = GateMovement::create([
            'container_id'    => $container->id,
            'container_no'    => $container->container_no,
            'customer_id'     => $this->customer->id,
            'movement_type'   => 'in',
            'size'            => '40',
            'container_type'  => 'RF',
            'condition'       => 'sound',
            'cargo_status'    => 'laden',
            'gate_in_time'    => '2026-02-20 08:00:00',
            'movement_status' => 'done',
            'created_by'      => auth()->id(),
        ]);

        return ReeferPlugSession::create([
            'container_id'     => $container->id,
            'gate_movement_id' => $movement->id,
            'customer_id'      => $this->customer->id,
            'service_type'     => 'long_term',
            'status'           => 'active',
            'plug_in_at'       => '2026-02-20 09:00:00',
            'created_by'       => auth()->id(),
            'updated_by'       => auth()->id(),
        ]);
    }

    private function line(
        ReeferPlugSession $session,
        string $from,
        string $to,
        string $invoiceStatus,
    ): ReeferElectricityInvoiceLine {
        static $seq = 0;
        $seq++;

        $invoice = ReeferElectricityInvoice::create([
            'invoice_no'          => 'TEST-REF-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
            'customer_id'         => $this->customer->id,
            'invoice_date'        => $to,
            'billing_period_from' => $from,
            'billing_period_to'   => $to,
            'status'              => $invoiceStatus,
            'created_by'          => auth()->id(),
        ]);

        return ReeferElectricityInvoiceLine::create([
            'reefer_electricity_invoice_id' => $invoice->id,
            'plug_session_id'               => $session->id,
            'container_id'                  => $session->container_id,
            'container_no'                  => $session->container?->container_no ?? 'UNKN0000000',
            'plug_in_at'                    => $session->plug_in_at,
            'billed_from'                   => $from,
            'billed_to'                     => $to,
            'is_interim'                    => true,
            'billing_mode'                  => 'daily',
        ]);
    }
}
