<?php

namespace Tests\Feature\Billing;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\StorageHandlingInvoice;
use App\Models\YardStorage;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * The same days are never invoiced twice.
 *
 * Until this existed, raising a second invoice for the same customer and period
 * produced a complete duplicate — every container returned, every lift event
 * returned, and nothing objected. The period load filtered on customer and date
 * overlap and nothing else.
 *
 * Storage is billed by the day, so the rule is a trim rather than an exclusion:
 * a container invoiced for 1–15 March and re-billed for 1–31 comes back with a
 * 16–31 window. Dropping it would match a looser reading of "show only unbilled
 * containers" but would leave those sixteen days invoiced by nobody — March's
 * bill skipped them and April's covers April.
 *
 * The guard is deliberately not manual-only. Billing the same days twice is
 * wrong wherever the rates came from, and the tariff flow is the one in daily
 * use.
 */
class StorageHandlingNoDoubleBillingTest extends FeatureTestCase
{
    private Customer $shippingLine;
    private EquipmentType $equipment;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-04-15 09:00:00');
        $this->shippingLine = Customer::factory()->create(['name' => 'Repeat Billing Lines']);
        $this->equipment    = EquipmentType::query()->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function stored(string $no, string $gateIn = '2026-03-01'): Container
    {
        $container = Container::factory()->create([
            'container_no'      => $no,
            'customer_id'       => $this->shippingLine->id,
            'equipment_type_id' => $this->equipment->id,
        ]);

        YardStorage::create([
            'container_id'  => $container->id,
            'customer_id'   => $this->shippingLine->id,
            'gate_in_date'  => $gateIn,
            'gate_out_date' => null,
            'free_days'     => 0,
            'daily_rate'    => 0,
        ]);

        return $container;
    }

    private function preview(string $from, string $to)
    {
        return $this->postJson(route('billing.storage-handling.manual.preview'), [
            'bill_type'        => 'storage_only',
            'shipping_line_id' => $this->shippingLine->id,
            'period_from'      => $from,
            'period_to'        => $to,
            'invoice_currency' => 'LKR',
            'exchange_rate'    => 1,
            'manual_free_days' => 0,
        ]);
    }

    /** Save whatever the preview returned, at a flat rate. */
    private function saveFrom(array $data, string $from, string $to, float $rate = 10.0)
    {
        $lines = collect($data['lines'])->map(fn ($l) => array_merge($l, [
            'storage_daily_rate' => $rate,
            'storage_subtotal'   => round($l['storage_chargeable_days'] * $rate, 2),
            'line_total'         => round($l['storage_chargeable_days'] * $rate, 2),
            'line_sscl'          => 0,
            'line_vat'           => 0,
            'line_grand_total'   => round($l['storage_chargeable_days'] * $rate, 2),
            'line_value'         => round($l['storage_chargeable_days'] * $rate, 2),
        ]))->all();

        return $this->post(route('billing.storage-handling.store'), [
            'bill_type'        => 'storage_only',
            'shipping_line_id' => $this->shippingLine->id,
            'pricing_mode'     => StorageHandlingInvoice::PRICING_MANUAL,
            'manual_free_days' => 0,
            'invoice_date'     => $to,
            'period_from'      => $from,
            'period_to'        => $to,
            'invoice_currency' => 'LKR',
            'exchange_rate'    => 1,
            'lines'            => $lines,
        ]);
    }

    // ── The headline behaviour ───────────────────────────────────────────────

    public function test_billing_the_same_period_twice_returns_nothing_the_second_time(): void
    {
        $this->actingAsSystemAdmin();
        $this->stored('DUPE0000001');

        $first = $this->preview('2026-03-01', '2026-03-31')->assertOk()->json();
        $this->assertCount(1, $first['lines'], 'Precondition: the container is on the first bill.');
        $this->saveFrom($first, '2026-03-01', '2026-03-31')->assertSessionHasNoErrors();

        $second = $this->preview('2026-03-01', '2026-03-31')->assertOk()->json();

        $this->assertSame([], $second['lines'],
            'Every day is invoiced, so there is nothing left to put on a second bill.');
    }

    public function test_a_partly_billed_container_comes_back_for_the_remaining_days(): void
    {
        $this->actingAsSystemAdmin();
        $this->stored('DUPE0000002');

        $first = $this->preview('2026-03-01', '2026-03-15')->assertOk()->json();
        $this->assertSame(15, $first['lines'][0]['storage_total_days']);
        $this->saveFrom($first, '2026-03-01', '2026-03-15')->assertSessionHasNoErrors();

        $second = $this->preview('2026-03-01', '2026-03-31')->assertOk()->json();

        $this->assertCount(1, $second['lines'], 'It comes back — the rest of the month is still owed.');
        $line = $second['lines'][0];

        $this->assertSame('2026-03-16', $line['storage_from'], 'The window starts where the last bill ended.');
        $this->assertSame('2026-03-31', $line['storage_to']);
        $this->assertSame(16, $line['storage_total_days'], '16 days, not 31.');
        $this->assertSame(15, $line['already_billed_days'],
            'And the screen can say why the window is short.');
    }

    public function test_sequential_monthly_billing_is_unaffected(): void
    {
        $this->actingAsSystemAdmin();
        $this->stored('DUPE0000003', '2026-03-01');

        $march = $this->preview('2026-03-01', '2026-03-31')->assertOk()->json();
        $this->saveFrom($march, '2026-03-01', '2026-03-31')->assertSessionHasNoErrors();

        $april = $this->preview('2026-04-01', '2026-04-30')->assertOk()->json();

        $this->assertCount(1, $april['lines'], 'The ordinary case must not be broken by the guard.');
        $this->assertSame(30, $april['lines'][0]['storage_total_days']);
        $this->assertSame(0, $april['lines'][0]['already_billed_days']);
    }

    // ── Which invoices reserve their days ────────────────────────────────────

    public function test_a_draft_invoice_still_reserves_its_days(): void
    {
        $this->actingAsSystemAdmin();
        $this->stored('DUPE0000004');

        $first = $this->preview('2026-03-01', '2026-03-31')->assertOk()->json();
        $this->saveFrom($first, '2026-03-01', '2026-03-31')->assertSessionHasNoErrors();

        $this->assertSame('draft', StorageHandlingInvoice::latest('id')->first()->status,
            'Precondition: it was never issued.');

        $this->assertSame([], $this->preview('2026-03-01', '2026-03-31')->assertOk()->json('lines'),
            'Two operators previewing the same period at once must not both bill it.');
    }

    public function test_cancelling_an_invoice_releases_its_days(): void
    {
        $this->actingAsSystemAdmin();
        $this->stored('DUPE0000005');

        $first = $this->preview('2026-03-01', '2026-03-31')->assertOk()->json();
        $this->saveFrom($first, '2026-03-01', '2026-03-31')->assertSessionHasNoErrors();

        $invoice = StorageHandlingInvoice::latest('id')->first();
        $this->patch(route('billing.storage-handling.cancel', $invoice))->assertSessionHasNoErrors();

        $again = $this->preview('2026-03-01', '2026-03-31')->assertOk()->json();

        $this->assertCount(1, $again['lines'],
            'Cancel-and-re-raise is how a wrong bill is corrected, so it has to keep working.');
        $this->assertSame(31, $again['lines'][0]['storage_total_days']);
    }

    // ── The save-time re-check ───────────────────────────────────────────────

    /**
     * A preview is a snapshot. Another operator can save between the preview and
     * the submit, and the browser's numbers are not evidence — so the overlap is
     * resolved again at save.
     */
    public function test_a_stale_preview_is_rejected_rather_than_double_billing(): void
    {
        $this->actingAsSystemAdmin();
        $this->stored('DUPE0000006');

        // Two operators preview the same period.
        $operatorA = $this->preview('2026-03-01', '2026-03-31')->assertOk()->json();
        $operatorB = $this->preview('2026-03-01', '2026-03-31')->assertOk()->json();

        $this->saveFrom($operatorA, '2026-03-01', '2026-03-31')->assertSessionHasNoErrors();
        $this->assertSame(1, StorageHandlingInvoice::count());

        // B submits the preview taken before A saved.
        $response = $this->saveFrom($operatorB, '2026-03-01', '2026-03-31');

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(1, StorageHandlingInvoice::count(),
            'The second save is refused, not silently duplicated.');
        $this->assertStringContainsString('DUPE0000006', session('error'),
            'And it names the container so the operator knows what happened.');
    }

    // ── The tariff flow is guarded too ───────────────────────────────────────

    public function test_the_tariff_flow_is_guarded_as_well(): void
    {
        $this->actingAsSystemAdmin();
        $this->stored('DUPE0000007');

        $first = $this->preview('2026-03-01', '2026-03-31')->assertOk()->json();
        $this->saveFrom($first, '2026-03-01', '2026-03-31')->assertSessionHasNoErrors();

        // The tariff preview — no pricing_mode — sees the same trimmed load.
        $tariffPreview = $this->postJson(route('billing.storage-handling.preview'), [
            'bill_type'        => 'storage_only',
            'shipping_line_id' => $this->shippingLine->id,
            'period_from'      => '2026-03-01',
            'period_to'        => '2026-03-31',
            'invoice_currency' => 'LKR',
            'exchange_rate'    => 1,
        ])->assertOk()->json();

        $this->assertSame([], $tariffPreview['lines'],
            'Billing the same days twice is wrong wherever the rates came from, and the '
            . 'tariff flow is the one in daily use.');
    }

    // ── Containers are independent ───────────────────────────────────────────

    public function test_billing_one_container_does_not_hide_another(): void
    {
        $this->actingAsSystemAdmin();
        $this->stored('DUPE0000008');
        $this->stored('DUPE0000009');

        $preview = $this->preview('2026-03-01', '2026-03-31')->assertOk()->json();
        $this->assertCount(2, $preview['lines']);

        // Only the first is billed — as if the operator unticked the second.
        $onlyFirst = $preview;
        $onlyFirst['lines'] = [collect($preview['lines'])->firstWhere('container_no', 'DUPE0000008')];
        $this->saveFrom($onlyFirst, '2026-03-01', '2026-03-31')->assertSessionHasNoErrors();

        $second = $this->preview('2026-03-01', '2026-03-31')->assertOk()->json();

        $this->assertCount(1, $second['lines']);
        $this->assertSame('DUPE0000009', $second['lines'][0]['container_no'],
            'The container dropped from the first invoice comes back in full on the next one — '
            . 'which is the requirement this guard was built for.');
        $this->assertSame(31, $second['lines'][0]['storage_total_days'], 'And it was never billed, so nothing is trimmed.');
    }
}
