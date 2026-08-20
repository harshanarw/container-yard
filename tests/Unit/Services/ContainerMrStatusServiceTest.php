<?php

namespace Tests\Unit\Services;

use App\Models\Container;
use App\Models\GateMovement;
use App\Services\ContainerMrStatusService;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The service's two pure algorithms, which the resolution ladder tests do not
 * reach: pairing gate-outs to visits, and deciding whether a projection write
 * would actually change anything.
 *
 * Both are private, and both are reached here by reflection. That is a
 * deliberate trade: neither is a capability a caller should invoke — the public
 * surface is resolve/refresh/forGateIns — but both are intricate enough that
 * their correctness matters more than their visibility, and neither can be
 * exercised through the public surface without a database, which would put them
 * out of reach of a unit test entirely.
 *
 * Pairing in particular is not a detail. If it is wrong, a visit's status lands
 * on the wrong row in Container Inquiry, and the projection quietly disagrees
 * with the screen it feeds.
 */
class ContainerMrStatusServiceTest extends TestCase
{
    private ContainerMrStatusService $svc;
    private ReflectionMethod $pairGateOuts;
    private ReflectionMethod $differs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = new ContainerMrStatusService();

        $this->pairGateOuts = new ReflectionMethod($this->svc, 'pairGateOuts');
        $this->pairGateOuts->setAccessible(true);

        $this->differs = new ReflectionMethod($this->svc, 'differs');
        $this->differs->setAccessible(true);
    }

    // ── Builders ─────────────────────────────────────────────────────────────

    private function gateIn(int $id, string $at, ?int $jobId = null): GateMovement
    {
        return (new GateMovement)->forceFill([
            'id'            => $id,
            'movement_type' => 'in',
            'gate_in_time'  => Carbon::parse($at),
            'yard_job_id'   => $jobId,
        ]);
    }

    private function gateOut(int $id, string $at, ?int $jobId = null): GateMovement
    {
        return (new GateMovement)->forceFill([
            'id'            => $id,
            'movement_type' => 'out',
            'gate_out_time' => Carbon::parse($at),
            'yard_job_id'   => $jobId,
        ]);
    }

    /**
     * Pair the given visits and flatten to [gate-in id => gate-out id|null],
     * so a case reads as the mapping it is asserting.
     *
     * @return array<int,int|null>
     */
    private function pair(array $gateIns, array $gateOuts): array
    {
        $map = $this->pairGateOuts->invoke($this->svc, collect($gateIns), collect($gateOuts));

        $out = [];
        foreach ($gateIns as $in) {
            $out[$in->id] = $map[$in->id]->id ?? null;
        }

        return $out;
    }

    private function differs(Container $model, array $new): bool
    {
        return $this->differs->invoke($this->svc, $model, $new);
    }

    private function container(array $attrs = []): Container
    {
        return (new Container)->forceFill($attrs);
    }

    // ── pairGateOuts ─────────────────────────────────────────────────────────

    public function test_a_visit_with_no_gate_out_stays_open(): void
    {
        $this->assertSame(
            [1 => null],
            $this->pair([$this->gateIn(1, '2025-01-01 09:00')], [])
        );
    }

    public function test_a_visit_pairs_by_its_shared_job_id(): void
    {
        $this->assertSame(
            [1 => 50],
            $this->pair(
                [$this->gateIn(1, '2025-01-01 09:00', 7)],
                [$this->gateOut(50, '2025-01-05 16:00', 7)]
            )
        );
    }

    public function test_visits_without_a_job_link_pair_by_time_window(): void
    {
        $this->assertSame(
            [1 => 50, 2 => 51],
            $this->pair(
                [$this->gateIn(1, '2025-01-01 09:00'), $this->gateIn(2, '2025-03-01 09:00')],
                [$this->gateOut(50, '2025-02-01 12:00'), $this->gateOut(51, '2025-04-01 12:00')]
            )
        );
    }

    /**
     * The case the obvious rule gets wrong.
     *
     * "The first gate-out at or after this gate-in" would hand visit 1 the
     * gate-out belonging to visit 2, and then hand the same gate-out to visit 2
     * as well. Only the job link separates two visits opened at the same instant
     * and closed out of order.
     */
    public function test_visits_opened_at_the_same_instant_pair_by_job_not_time_order(): void
    {
        $this->assertSame(
            [1 => 61, 2 => 60],
            $this->pair(
                [$this->gateIn(1, '2025-01-01 09:00', 1), $this->gateIn(2, '2025-01-01 09:00', 2)],
                [$this->gateOut(60, '2025-01-01 10:00', 2), $this->gateOut(61, '2025-01-01 11:00', 1)]
            )
        );
    }

    /** Out in the morning and back the same afternoon — a real pattern here. */
    public function test_a_same_day_turnaround_keeps_each_visit_its_own_gate_out(): void
    {
        $this->assertSame(
            [1 => 60, 2 => 61],
            $this->pair(
                [$this->gateIn(1, '2025-01-01 08:00', 1), $this->gateIn(2, '2025-01-01 13:00', 2)],
                [$this->gateOut(60, '2025-01-01 10:00', 1), $this->gateOut(61, '2025-01-01 17:00', 2)]
            )
        );
    }

    public function test_an_unlinked_gate_out_is_consumed_once(): void
    {
        $this->assertSame(
            [1 => 50, 2 => null],
            $this->pair(
                [$this->gateIn(1, '2025-01-01 09:00'), $this->gateIn(2, '2025-03-01 09:00')],
                [$this->gateOut(50, '2025-02-01 12:00')]
            ),
            'One gate-out cannot close two visits.'
        );
    }

    public function test_a_gate_out_before_every_visit_pairs_with_nothing(): void
    {
        $this->assertSame(
            [1 => null],
            $this->pair(
                [$this->gateIn(1, '2025-05-01 09:00')],
                [$this->gateOut(50, '2025-01-01 12:00')]
            )
        );
    }

    public function test_job_linked_and_unlinked_visits_coexist(): void
    {
        $this->assertSame(
            [1 => 60, 2 => 61],
            $this->pair(
                [$this->gateIn(1, '2025-01-01 09:00', 9), $this->gateIn(2, '2025-03-01 09:00')],
                [$this->gateOut(60, '2025-02-01 12:00', 9), $this->gateOut(61, '2025-04-01 12:00')]
            )
        );
    }

    // ── differs ──────────────────────────────────────────────────────────────
    //
    // This is what keeps refresh() idempotent. Too eager and every hook rewrites
    // the row and churns mr_status_at; too lax and a real transition is never
    // written at all.

    private function storedProjection(): Container
    {
        return $this->container([
            'mr_status'            => 'awaiting_qc',
            'mr_status_group'      => 'pending',
            'mr_lane'              => 'repair',
            'mr_status_at'         => Carbon::parse('2025-01-01 10:00'),
            'export_ready'         => false,
            'mr_status_expires_at' => Carbon::parse('2026-01-31'),
        ]);
    }

    public function test_an_identical_projection_does_not_differ(): void
    {
        $this->assertFalse($this->differs($this->storedProjection(), [
            'mr_status'            => 'awaiting_qc',
            'mr_status_group'      => 'pending',
            'mr_lane'              => 'repair',
            'mr_status_at'         => Carbon::parse('2025-01-01 10:00'),
            'export_ready'         => false,
            'mr_status_expires_at' => Carbon::parse('2026-01-31'),
        ]), 'Rewriting an unchanged row would churn mr_status_at on every hook.');
    }

    public function test_each_projected_field_is_compared(): void
    {
        $stored = $this->storedProjection();

        $this->assertTrue($this->differs($stored, ['mr_status' => 'repaired_available']), 'code');
        $this->assertTrue($this->differs($stored, ['mr_status_group' => 'ready']), 'group');
        $this->assertTrue($this->differs($stored, ['mr_lane' => 'wash']), 'lane');
        $this->assertTrue($this->differs($stored, ['export_ready' => true]), 'export_ready');
        $this->assertTrue($this->differs($stored, ['mr_status_at' => Carbon::parse('2025-02-01 10:00')]), 'timestamp');
        $this->assertTrue($this->differs($stored, ['mr_status_expires_at' => Carbon::parse('2026-02-28')]), 'expiry');
    }

    public function test_equal_timestamps_do_not_differ(): void
    {
        $this->assertFalse($this->differs($this->storedProjection(), [
            'mr_status_at' => Carbon::parse('2025-01-01 10:00'),
        ]));

        $this->assertFalse($this->differs($this->storedProjection(), [
            'mr_status_expires_at' => Carbon::parse('2026-01-31'),
        ]));
    }

    public function test_clearing_a_timestamp_is_a_change(): void
    {
        $this->assertTrue($this->differs($this->storedProjection(), ['mr_status_at' => null]));
        $this->assertTrue($this->differs($this->storedProjection(), ['mr_status_expires_at' => null]),
            'A reefer whose PTI record went away no longer has a boundary.');
    }

    /** A row that has never been projected: every column null. */
    public function test_a_never_projected_row_differs_from_a_real_status(): void
    {
        $blank = $this->container();

        $this->assertTrue($this->differs($blank, ['mr_status' => 'awaiting_qc']));
        $this->assertTrue($this->differs($blank, ['mr_status_expires_at' => Carbon::parse('2026-01-31')]));
        $this->assertTrue($this->differs($blank, ['export_ready' => true]));
    }

    public function test_a_never_projected_row_does_not_differ_from_nothing(): void
    {
        $blank = $this->container();

        $this->assertFalse($this->differs($blank, ['mr_status' => null]));
        $this->assertFalse($this->differs($blank, ['mr_status_expires_at' => null]));
        $this->assertFalse($this->differs($blank, ['export_ready' => false]),
            'A null boolean column and false are the same state, so the backfill must not rewrite every row.');
    }
}
