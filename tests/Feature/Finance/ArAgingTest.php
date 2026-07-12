<?php

namespace Tests\Feature\Finance;

use App\Models\Customer;
use App\Models\GeneralInvoice;
use Illuminate\Support\Str;
use Tests\Support\FeatureTestCase;

/**
 * Cover for the AR aging report's bucketing math. Three issued invoices for one
 * customer, with due dates that fall in distinct buckets, must land in the
 * right current / 31-60 / 90+ columns and sum correctly.
 *
 * Assertions are scoped to a freshly-created customer via the report's
 * per-customer breakdown, so any seeded issued invoices don't interfere.
 */
class ArAgingTest extends FeatureTestCase
{
    private function issuedInvoice(Customer $customer, string $dueDate, float $amount): GeneralInvoice
    {
        return GeneralInvoice::create([
            'invoice_no'       => 'AGING-' . Str::upper(Str::random(10)),
            'invoice_type'     => 'invoice',
            'customer_id'      => $customer->id,
            'billing_party_id' => $customer->id,
            'invoice_date'     => $dueDate,
            'due_date'         => $dueDate,
            'currency'         => 'LKR',
            'exchange_rate'    => 1,
            'grand_total'      => $amount,
            'balance_due'      => $amount,
            'status'           => 'issued',
            'created_by'       => auth()->id(),
        ]);
    }

    public function test_ar_aging_buckets_outstanding_invoices_by_due_date(): void
    {
        $this->actingAsSystemAdmin();
        $customer = Customer::factory()->create();

        $this->issuedInvoice($customer, now()->toDateString(), 100);              // due today  → current
        $this->issuedInvoice($customer, now()->subDays(45)->toDateString(), 200); // 45d overdue → 31-60
        $this->issuedInvoice($customer, now()->subDays(120)->toDateString(), 300); // 120d overdue → 90+

        $response = $this->get(route('finance.ar.aging', [
            'as_of'  => now()->toDateString(),
            'age_by' => 'due_date',
        ]));
        $response->assertOk();

        $byCustomer = $response->viewData('byCustomer');
        $row = $byCustomer->get($customer->id);

        // TEMP DEBUG — replicate the controller's exact expression on a full model load.
        $ctrl = GeneralInvoice::whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->orderBy('invoice_date')->get()
            ->map(fn ($i) => [
                'id'          => $i->id,
                'cust'        => $i->customer_id,
                'bp'          => $i->billing_party_id,
                'coalesce'    => $i->billing_party_id ?? $i->customer_id,
                'bp_type'     => gettype($i->billing_party_id),
            ])->all();
        $diag = json_encode([
            'customer_id'      => $customer->id,
            'controller_view'  => $ctrl,
            'byCustomer_keys'  => $byCustomer->keys()->all(),
            'grandTotals'      => $response->viewData('grandTotals'),
        ], JSON_PRETTY_PRINT);
        $this->assertNotNull($row, "Customer not present in the aging report. DIAG: {$diag}");

        $this->assertEqualsWithDelta(100.0, (float) $row['current'], 0.01);
        $this->assertEqualsWithDelta(0.0,   (float) $row['1-30'],   0.01);
        $this->assertEqualsWithDelta(200.0, (float) $row['31-60'],  0.01);
        $this->assertEqualsWithDelta(0.0,   (float) $row['61-90'],  0.01);
        $this->assertEqualsWithDelta(300.0, (float) $row['90+'],    0.01);
        $this->assertEqualsWithDelta(600.0, (float) $row['total'],  0.01);
    }
}
