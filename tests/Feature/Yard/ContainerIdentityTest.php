<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\GateMovement;
use App\Models\Inquiry;
use App\Services\ContainerInquiryService;
use App\Services\ContainerMrStatusService;
use App\Support\ContainerNumbers;
use App\Support\MrStatusCatalogue;
use Illuminate\Support\Facades\DB;
use Tests\Support\FeatureTestCase;

/**
 * A box is its record, not its number (Rule 7.1; Phase 8a).
 *
 * The scenario renumbering produces: the box arrives under one number, its
 * number changes on the master, and it leaves under the new one. Container
 * Inquiry and the M&R status used to group a box's records by the number text,
 * so this read as two boxes: a visit that never closed and a departure with no
 * arrival. Each test asserts first that the records really do carry different
 * numbers, so it cannot pass by matching text.
 */
class ContainerIdentityTest extends FeatureTestCase
{
    private const OLD = 'OLDU1234565';
    private const NEW = 'NEWU7654321';

    /** Arrival and an inquiry under the old number; master renumbered; departure under the new. */
    private function renumberedVisit(): array
    {
        $box = Container::factory()->create(['container_no' => self::OLD, 'status' => 'in_yard']);

        $arrival = GateMovement::create([
            'container_id' => $box->id, 'container_no' => self::OLD,
            'size' => $box->size, 'container_type' => $box->type_code,
            'customer_id' => $box->customer_id, 'movement_type' => 'in', 'cargo_status' => 'empty',
            'gate_in_time' => now()->subDays(10), 'created_by' => auth()->id(),
        ]);

        $inquiry = Inquiry::create([
            'inquiry_no' => 'INQ-ID-0001', 'container_id' => $box->id, 'container_no' => self::OLD,
            'size' => $box->size, 'type_code' => 'GP', 'customer_id' => $box->customer_id,
            'inquiry_type' => 'condition_survey', 'created_by' => auth()->id(),
        ]);

        // The number changes on the master. Done directly, as the Container
        // Master form can do today (G7.1); the renumbering flow comes in 8c.
        DB::table('containers')->where('id', $box->id)->update(['container_no' => self::NEW]);
        $box->refresh();

        $departure = GateMovement::create([
            'container_id' => $box->id, 'container_no' => self::NEW,
            'size' => $box->size, 'container_type' => $box->type_code,
            'customer_id' => $box->customer_id, 'movement_type' => 'out', 'cargo_status' => 'empty',
            'gate_out_time' => now()->subDays(2), 'created_by' => auth()->id(),
        ]);

        // Precondition: the two ends really carry different numbers.
        $this->assertNotSame($arrival->container_no, $departure->container_no);

        return [$box, $arrival, $departure, $inquiry];
    }

    public function test_inquiry_history_reads_a_renumbered_box_as_one_visit(): void
    {
        $this->actingAsSystemAdmin();
        [, $arrival, $departure] = $this->renumberedVisit();

        $history = app(ContainerInquiryService::class)->getContainerHistory(self::NEW);

        $this->assertSame(self::NEW, $history['container_no']);
        $this->assertSame(1, $history['stats']['total_visits']);
        $this->assertCount(1, $history['cycles']);

        $cycle = $history['cycles']->first();
        $this->assertSame($arrival->id, $cycle['gate_in']->id);
        $this->assertSame($departure->id, $cycle['gate_out']?->id, 'The departure under the new number should close the visit.');
    }

    public function test_the_inquiry_list_pairs_the_departure_with_the_arrival(): void
    {
        $this->actingAsSystemAdmin();
        [, $arrival, $departure] = $this->renumberedVisit();

        $map = app(ContainerInquiryService::class)->matchGateOutsForPage(collect([$arrival->fresh()]));

        $this->assertSame($departure->id, $map[$arrival->id]?->id);
    }

    public function test_mr_status_sees_the_box_as_gated_out(): void
    {
        $this->actingAsSystemAdmin();
        [, $arrival] = $this->renumberedVisit();

        $status = app(ContainerMrStatusService::class)->forGateIns(collect([$arrival->fresh()]))[$arrival->id];

        $this->assertSame(MrStatusCatalogue::GATED_OUT, $status->code);
    }

    public function test_records_made_under_the_former_number_stay_with_the_box(): void
    {
        $this->actingAsSystemAdmin();
        [$box, , , $inquiry] = $this->renumberedVisit();

        $found = ContainerNumbers::whereBelongsTo(Inquiry::query(), $box)->pluck('id');

        $this->assertSame(self::OLD, $inquiry->container_no); // precondition: made under the old number
        $this->assertTrue($found->contains($inquiry->id));
    }

    public function test_a_row_with_no_container_id_still_matches_by_the_current_number(): void
    {
        $this->actingAsSystemAdmin();
        $box = Container::factory()->create(['container_no' => 'LEGU1234560']);

        // gate_movements.container_id is NOT NULL, but several line tables
        // (storage invoice details, repair invoices) allow null. Built in
        // memory: the rule is the same for every table.
        $legacy = new GateMovement(['container_id' => null, 'container_no' => 'LEGU1234560']);
        $other  = new GateMovement(['container_id' => null, 'container_no' => 'ZZZU0000000']);

        $grouped = ContainerNumbers::groupByContainer(collect([$legacy, $other]), [$box]);
        $this->assertSame([$box->id], $grouped->keys()->all());
        $this->assertCount(1, $grouped->get($box->id));

        // And the query form has the same fallback branch.
        $sql = ContainerNumbers::whereBelongsTo(GateMovement::query(), $box)->toSql();
        $this->assertStringContainsString('`container_id` is null', $sql);
    }

    public function test_find_normalises_what_was_typed(): void
    {
        $this->actingAsSystemAdmin();
        $box = Container::factory()->create(['container_no' => 'FNDU1234569']);

        $this->assertSame($box->id, ContainerNumbers::find('  fndu1234569 ')?->id);
        $this->assertNull(ContainerNumbers::find(''));
        $this->assertNull(ContainerNumbers::find('NONE0000000'));
    }
}
