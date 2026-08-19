<?php

namespace Tests\Unit\Support;

use App\Models\CargoTransfer;
use App\Models\Container;
use App\Models\ContainerHire;
use App\Models\ContainerHold;
use App\Models\Estimate;
use App\Models\GateMovement;
use App\Models\Inquiry;
use App\Models\WorkOrder;
use App\Models\YardJobType;
use App\Services\ContainerMrStatusService;
use App\Support\MrStatus;
use App\Support\MrStatusCatalogue as Cat;
use App\Support\MrStatusContext;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The M&R status resolution ladder.
 *
 * ContainerMrStatusService::resolve() runs no queries — it decides purely from
 * a context the caller assembled. That is what lets the whole 21-rung order be
 * covered here as a table of plain cases, with unsaved models and no database,
 * instead of a fixture marathon. If a case in this file ever needs a database,
 * the resolver has stopped being pure.
 *
 * The data providers deliberately return plain arrays rather than models:
 * providers are evaluated before the application boots, and building an
 * Eloquent model with a date attribute needs a booted app. Models are built
 * inside the test method, from the spec.
 *
 * The ladder's *order* is the design, so the orderings that actually decide
 * behaviour are asserted individually below the per-rung table.
 */
class MrStatusResolutionTest extends TestCase
{
    private const WASH_CATEGORY_ID   = 99;
    private const REPAIR_CATEGORY_ID = 1;

    private ContainerMrStatusService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new ContainerMrStatusService();
    }

    // ── Builders ─────────────────────────────────────────────────────────────

    private function container(array $attrs = []): Container
    {
        return (new Container)->forceFill(array_merge([
            'id'           => 1,
            'container_no' => 'TEST1234567',
            'status'       => 'in_yard',
            'condition'    => 'sound',
            'type_code'    => 'GP',
        ], $attrs));
    }

    private function gateIn(): GateMovement
    {
        return (new GateMovement)->forceFill([
            'id'            => 10,
            'movement_type' => 'in',
            'gate_in_time'  => Carbon::now()->subDays(5),
        ]);
    }

    private function gateOut(): GateMovement
    {
        return (new GateMovement)->forceFill([
            'id'            => 11,
            'movement_type' => 'out',
            'gate_out_time' => Carbon::now()->subDay(),
        ]);
    }

    private function jobType(array $flags = []): YardJobType
    {
        return (new YardJobType)->forceFill(array_merge([
            'handling_applicable'       => false,
            'survey_applicable'         => false,
            'estimate_applicable'       => false,
            'repair_applicable'         => false,
            'storage_applicable'        => false,
            'wash_applicable'           => false,
            'reefer_applicable'         => false,
            'cargo_transfer_applicable' => false,
        ], $flags));
    }

    private function wo(string $status, array $attrs = []): WorkOrder
    {
        return (new WorkOrder)->forceFill(array_merge([
            'id'                 => 100,
            'status'             => $status,
            'repair_category_id' => self::REPAIR_CATEGORY_ID,
            'created_at'         => Carbon::now()->subDays(3),
        ], $attrs));
    }

    /** A work order in the cleaning/treatment category — the wash lane. */
    private function washWo(string $status, array $attrs = []): WorkOrder
    {
        return $this->wo($status, array_merge(['repair_category_id' => self::WASH_CATEGORY_ID], $attrs));
    }

    private function estimate(string $status, array $attrs = []): Estimate
    {
        return (new Estimate)->forceFill(array_merge([
            'id'            => 200,
            'status'        => $status,
            'estimate_date' => Carbon::now()->subDays(4),
        ], $attrs));
    }

    private function inquiry(string $status, ?string $action = null, array $attrs = []): Inquiry
    {
        return (new Inquiry)->forceFill(array_merge([
            'id'                 => 300,
            'status'             => $status,
            'recommended_action' => $action,
            'created_at'         => Carbon::now()->subDays(4),
        ], $attrs));
    }

    private function hold(string $type = 'customs'): ContainerHold
    {
        return (new ContainerHold)->forceFill([
            'id'         => 400,
            'hold_type'  => $type,
            'cleared_at' => null,
        ]);
    }

    private function hire(): ContainerHire
    {
        return (new ContainerHire)->forceFill([
            'status'       => 'active',
            'on_hire_date' => Carbon::now()->subDays(2),
        ]);
    }

    private function transfer(): CargoTransfer
    {
        return (new CargoTransfer)->forceFill([
            'status'        => 'active',
            'transfer_date' => Carbon::now()->subDay(),
        ]);
    }

    /**
     * Turn a provider spec into context parts.
     *
     * Keys mirror the ctx() parts; list values are [status, extra attributes],
     * so the providers stay plain data and nothing touches Eloquent until the
     * application is up.
     */
    private function buildParts(array $spec): array
    {
        $parts = [];

        if (isset($spec['container'])) {
            $parts['container'] = $this->container($spec['container']);
        }
        if (! empty($spec['gateOut'])) {
            $parts['gateOut'] = $this->gateOut();
        }
        if (isset($spec['jobType'])) {
            $parts['jobType'] = $this->jobType($spec['jobType']);
        }
        if (! empty($spec['hire'])) {
            $parts['hire'] = $this->hire();
        }
        if (! empty($spec['transfer'])) {
            $parts['transfer'] = $this->transfer();
        }
        if (array_key_exists('ptiValid', $spec)) {
            $parts['ptiValid'] = $spec['ptiValid'];
        }

        foreach ($spec['workOrders'] ?? [] as $i => [$status, $attrs]) {
            $wash = (bool) ($attrs['wash'] ?? false);
            unset($attrs['wash']);
            $parts['workOrders'][] = $wash
                ? $this->washWo($status, array_merge(['id' => 100 + $i], $attrs))
                : $this->wo($status, array_merge(['id' => 100 + $i], $attrs));
        }

        foreach ($spec['estimates'] ?? [] as $i => [$status, $attrs]) {
            $parts['estimates'][] = $this->estimate($status, array_merge(['id' => 200 + $i], $attrs));
        }

        foreach ($spec['inquiries'] ?? [] as $i => [$status, $action]) {
            $parts['inquiries'][] = $this->inquiry($status, $action, ['id' => 300 + $i]);
        }

        foreach ($spec['holds'] ?? [] as $type) {
            $parts['holds'][] = $this->hold($type);
        }

        return $parts;
    }

    /** Build a context, defaulting every part to "nothing has happened yet". */
    private function ctx(array $parts = []): MrStatusContext
    {
        return new MrStatusContext(
            container:       $parts['container'] ?? $this->container(),
            gateIn:          array_key_exists('gateIn', $parts) ? $parts['gateIn'] : $this->gateIn(),
            gateOut:         $parts['gateOut']   ?? null,
            jobType:         $parts['jobType']   ?? null,
            inquiries:       collect($parts['inquiries']  ?? []),
            estimates:       collect($parts['estimates']  ?? []),
            workOrders:      collect($parts['workOrders'] ?? []),
            activeHolds:     collect($parts['holds']      ?? []),
            activeHire:      $parts['hire']     ?? null,
            activeTransfer:  $parts['transfer'] ?? null,
            ptiValid:        $parts['ptiValid'] ?? false,
            washCategoryIds: [self::WASH_CATEGORY_ID],
            ptiValidUntil:   $parts['ptiValidUntil'] ?? null,
        );
    }

    private function resolve(array $parts = []): MrStatus
    {
        return $this->svc->resolve($this->ctx($parts));
    }

    private function resolveSpec(array $spec): MrStatus
    {
        return $this->resolve($this->buildParts($spec));
    }

    // ── The ladder, rung by rung ─────────────────────────────────────────────

    /** One case per rung of the ladder, in order. */
    #[DataProvider('ladderCases')]
    public function test_ladder_rung(string $expected, array $spec, string $because): void
    {
        $this->assertSame($expected, $this->resolveSpec($spec)->code, $because);
    }

    public static function ladderCases(): array
    {
        return [
            'rung 1 — matched gate-out closes the cycle' => [
                Cat::GATED_OUT,
                ['gateOut' => true, 'workOrders' => [['in_progress', []]]],
                'A closed cycle is history — nothing in it can still be in progress.',
            ],

            'rung 2 — survey recommends scrap' => [
                Cat::CONDEMNED,
                ['inquiries' => [['closed', 'scrap']]],
                'A scrapped box must never read as work in progress.',
            ],

            'rung 3 — active hire' => [
                Cat::ON_HIRE,
                ['hire' => true],
                'Out on hire is committed to a customer, not the yard to work on.',
            ],

            'rung 4 — work order rejected at QC' => [
                Cat::QC_FAILED,
                ['workOrders' => [['rejected', []]]],
                'QC failure is the one state where a box looks finished and is not.',
            ],

            'rung 5 — work order on hold' => [
                Cat::REPAIR_ON_HOLD,
                ['workOrders' => [['on_hold', []]]],
                'Work started then stopped.',
            ],

            'rung 6 — work order in progress' => [
                Cat::REPAIR_IN_PROGRESS,
                ['workOrders' => [['in_progress', []]]],
                'Work happening right now.',
            ],

            'rung 7 — work order completed, awaiting QC' => [
                Cat::AWAITING_QC,
                ['workOrders' => [['completed', []]]],
                'Finished but not yet signed off.',
            ],

            'rung 8 — work order pending' => [
                Cat::REPAIR_SCHEDULED,
                ['workOrders' => [['pending', []]]],
                'Raised but not started.',
            ],

            'rung 9 — estimate approved, no work order raised' => [
                Cat::ESTIMATE_APPROVED,
                ['estimates' => [['approved', []]]],
                'The gap where approved jobs quietly stall.',
            ],

            'rung 9 — partially approved counts too' => [
                Cat::ESTIMATE_APPROVED,
                ['estimates' => [['partially_approved', []]]],
                'Partial approval still needs a work order raising.',
            ],

            'rung 10 — estimate rejected' => [
                Cat::ESTIMATE_REJECTED,
                ['estimates' => [['rejected', []]]],
                'The customer said no.',
            ],

            'rung 11 — estimate sent' => [
                Cat::ESTIMATE_SENT,
                ['estimates' => [['sent', []]]],
                'With the customer, awaiting a decision.',
            ],

            'rung 11 — estimate under review' => [
                Cat::ESTIMATE_SENT,
                ['estimates' => [['under_review', []]]],
                'Under review is still awaiting the customer.',
            ],

            'rung 11 — inquiry says estimate sent, without an estimate record' => [
                Cat::ESTIMATE_SENT,
                ['inquiries' => [['estimate_sent', null]]],
                'The survey knows the estimate went out even if the estimate is not linked.',
            ],

            'rung 12 — estimate still in draft' => [
                Cat::ESTIMATE_PENDING,
                ['estimates' => [['draft', []]]],
                'Being priced.',
            ],

            'rung 12 — survey recommends repair, no estimate yet' => [
                Cat::ESTIMATE_PENDING,
                ['inquiries' => [['closed', 'repair']]],
                'Needs pricing.',
            ],

            'rung 13 — survey open' => [
                Cat::SURVEY_IN_PROGRESS,
                ['inquiries' => [['open', null]]],
                'Survey underway.',
            ],

            'rung 13 — survey in progress' => [
                Cat::SURVEY_IN_PROGRESS,
                ['inquiries' => [['in_progress', null]]],
                'Survey underway.',
            ],

            'rung 14 — all work orders closed after QC' => [
                Cat::REPAIRED_AVAILABLE,
                ['workOrders' => [['closed', []]]],
                'Everything raised this cycle ran to a QC pass.',
            ],

            'rung 15 — surveyed sound, no work raised' => [
                Cat::SOUND_AVAILABLE,
                ['inquiries' => [['closed', 'no_action']]],
                'Surveyed and found not to need work.',
            ],

            'rung 15 — monitor counts as sound' => [
                Cat::SOUND_AVAILABLE,
                ['inquiries' => [['closed', 'monitor']]],
                'Monitor is not a repair.',
            ],

            'rung 16 — reserved to a booking' => [
                Cat::RESERVED,
                ['container' => ['status' => 'reserved']],
                'Allocated stock is committed, not free.',
            ],

            'rung 17 — active cargo transfer' => [
                Cat::TRANSFER_IN_PROGRESS,
                ['transfer' => true],
                'Standing in while cargo is cross-stuffed.',
            ],

            'rung 18 — reefer with no valid PTI' => [
                Cat::PTI_DUE,
                ['container' => ['type_code' => 'RF'], 'ptiValid' => false],
                'A reefer cannot ship without a live PTI.',
            ],

            'rung 18 — reefer whose PTI failed' => [
                Cat::PTI_FAILED,
                ['container' => ['type_code' => 'RF', 'pti_status' => 'failed'], 'ptiValid' => false],
                'A failed PTI is blocked, not merely due.',
            ],

            'rung 19 — job type wants a survey and none exists' => [
                Cat::AWAITING_SURVEY,
                ['jobType' => ['survey_applicable' => true]],
                'The job type says survey it; nothing has.',
            ],

            'rung 20 — storage-only job type' => [
                Cat::IN_STORAGE,
                ['jobType' => ['storage_applicable' => true]],
                'Here purely to sit.',
            ],

            'rung 21 — nothing attached at all' => [
                Cat::AWAITING_DISPOSITION,
                [],
                'An incomplete history lands here by design — never a confident wrong answer.',
            ],
        ];
    }

    // ── The orderings that actually decide behaviour ──────────────────────────

    public function test_an_open_work_order_beats_the_survey_that_produced_it(): void
    {
        $this->assertSame(Cat::REPAIR_IN_PROGRESS, $this->resolve([
            'inquiries'  => [$this->inquiry('closed', 'repair')],
            'estimates'  => [$this->estimate('approved')],
            'workOrders' => [$this->wo('in_progress')],
        ])->code, 'Later stages override the earlier ones they grew out of.');
    }

    public function test_a_rejected_work_order_never_reads_as_repaired(): void
    {
        $code = $this->resolve([
            'workOrders' => [
                $this->wo('closed', ['id' => 101, 'qc_at' => Carbon::now()->subDays(2)]),
                $this->wo('rejected', ['id' => 102, 'qc_at' => Carbon::now()->subDay()]),
            ],
        ])->code;

        $this->assertSame(Cat::QC_FAILED, $code,
            'A container with a rejected work order must never read as available.');
        $this->assertNotSame(Cat::REPAIRED_AVAILABLE, $code);
    }

    public function test_one_closed_and_one_open_work_order_is_still_in_progress(): void
    {
        $this->assertSame(Cat::REPAIR_IN_PROGRESS, $this->resolve([
            'workOrders' => [
                $this->wo('closed', ['id' => 101, 'qc_at' => Carbon::now()->subDays(2)]),
                $this->wo('in_progress', ['id' => 102]),
            ],
        ])->code, 'One category finishing does not finish the container.');
    }

    /** The shape of the in_repair stranding bug: work orders gone, nothing left open. */
    public function test_cancelled_work_orders_fall_back_rather_than_stranding(): void
    {
        $this->assertSame(Cat::AWAITING_DISPOSITION, $this->resolve([
            'workOrders' => [$this->wo('cancelled')],
        ])->code, 'An abandoned repair leaves the box awaiting disposition, not stuck in repair.');
    }

    public function test_an_approved_estimate_with_all_work_cancelled_still_needs_a_work_order(): void
    {
        $this->assertSame(Cat::ESTIMATE_APPROVED, $this->resolve([
            'estimates'  => [$this->estimate('approved')],
            'workOrders' => [$this->wo('cancelled')],
        ])->code, 'Approved work whose order was cancelled still needs raising.');
    }

    public function test_a_finished_repair_reads_repaired_not_awaiting_a_work_order(): void
    {
        $this->assertSame(Cat::REPAIRED_AVAILABLE, $this->resolve([
            'estimates'  => [$this->estimate('approved')],
            'workOrders' => [$this->wo('closed', ['qc_at' => Carbon::now()->subDay()])],
        ])->code, 'A closed work order means the repair ran — not that one is still owed.');
    }

    public function test_scrap_beats_an_in_flight_estimate(): void
    {
        $this->assertSame(Cat::CONDEMNED, $this->resolve([
            'inquiries' => [$this->inquiry('closed', 'scrap')],
            'estimates' => [$this->estimate('sent')],
        ])->code, 'Pricing a box that is being scrapped is not the headline.');
    }

    public function test_a_job_type_without_survey_applicable_never_awaits_survey(): void
    {
        $this->assertSame(Cat::AWAITING_DISPOSITION, $this->resolve([
            'jobType' => $this->jobType(['handling_applicable' => true]),
        ])->code, 'The classification axis is the job type — a handling job is not awaiting survey.');
    }

    public function test_a_reefer_under_repair_reads_repair_not_pti(): void
    {
        $status = $this->resolve([
            'container'  => $this->container(['type_code' => 'RF']),
            'workOrders' => [$this->wo('in_progress')],
            'ptiValid'   => false,
        ]);

        $this->assertSame(Cat::REPAIR_IN_PROGRESS, $status->code,
            'PTI is ranked below repair on purpose.');
        $this->assertTrue($status->hasModifier(Cat::MODIFIER_PTI_EXPIRED),
            'The lapsed PTI is still surfaced as a modifier, so ranking it low loses nothing.');
    }

    // ── Lanes ────────────────────────────────────────────────────────────────

    public function test_a_wash_category_work_order_speaks_in_wash_terms(): void
    {
        $status = $this->resolve(['workOrders' => [$this->washWo('in_progress')]]);

        $this->assertSame(Cat::WASH_IN_PROGRESS, $status->code);
        $this->assertSame(Cat::LANE_WASH, $status->lane);
        $this->assertSame('Washing', $status->label(), 'A wash must never read as a repair.');
    }

    public function test_wash_scheduled_and_washed_use_the_wash_codes(): void
    {
        $this->assertSame(Cat::WASH_SCHEDULED,
            $this->resolve(['workOrders' => [$this->washWo('pending')]])->code);

        $this->assertSame(Cat::WASHED,
            $this->resolve(['workOrders' => [$this->washWo('closed', ['qc_at' => Carbon::now()->subDay()])]])->code);
    }

    /** Stages that exist in both lanes share a code, and only the wording follows the lane. */
    public function test_shared_stages_keep_one_code_but_take_the_wash_wording(): void
    {
        $status = $this->resolve(['workOrders' => [$this->washWo('on_hold')]]);

        $this->assertSame(Cat::REPAIR_ON_HOLD, $status->code, 'One stored code keeps filters simple.');
        $this->assertSame(Cat::LANE_WASH, $status->lane);
        $this->assertSame('Wash on hold', $status->label());
    }

    public function test_a_repair_work_order_keeps_repair_wording(): void
    {
        $status = $this->resolve(['workOrders' => [$this->wo('on_hold')]]);

        $this->assertSame(Cat::LANE_REPAIR, $status->lane);
        $this->assertSame('Repair on hold', $status->label());
    }

    // ── Modifiers ────────────────────────────────────────────────────────────

    public function test_a_hold_is_a_modifier_not_a_status(): void
    {
        $status = $this->resolve([
            'workOrders' => [$this->wo('in_progress')],
            'holds'      => [$this->hold('customs')],
        ]);

        $this->assertSame(Cat::REPAIR_IN_PROGRESS, $status->code,
            'A held container is still doing whatever it was doing.');
        $this->assertTrue($status->isHeld());
        $this->assertContains('hold_customs', $status->modifiers,
            'The hold type matters at the gate, so it survives as its own chip.');
    }

    public function test_a_stage_past_its_threshold_is_flagged_overdue(): void
    {
        $overdue = Cat::AGE_THRESHOLD_DAYS[Cat::AWAITING_QC] + 5;

        $status = $this->resolve([
            'workOrders' => [$this->wo('completed', ['completed_date' => Carbon::now()->subDays($overdue)])],
        ]);

        $this->assertSame(Cat::AWAITING_QC, $status->code);
        $this->assertTrue($status->isOverdue(), "Awaiting QC for {$overdue} days should be visible without a query.");
        $this->assertSame($overdue, $status->ageDays());
    }

    public function test_a_fresh_stage_is_not_overdue(): void
    {
        $status = $this->resolve([
            'workOrders' => [$this->wo('completed', ['completed_date' => Carbon::now()->subDay()])],
        ]);

        $this->assertFalse($status->isOverdue());
    }

    // ── Export readiness ─────────────────────────────────────────────────────

    public function test_a_repaired_container_is_export_ready(): void
    {
        $status = $this->resolve([
            'container'  => $this->container(['status' => 'available']),
            'workOrders' => [$this->wo('closed', ['qc_at' => Carbon::now()->subDay()])],
        ]);

        $this->assertSame(Cat::REPAIRED_AVAILABLE, $status->code);
        $this->assertTrue($status->exportReady);
    }

    #[DataProvider('exportSuppressors')]
    public function test_export_readiness_is_suppressed(array $spec, string $because): void
    {
        $parts = array_merge([
            'container'  => $this->container(['status' => 'available']),
            'workOrders' => [$this->wo('closed', ['qc_at' => Carbon::now()->subDay()])],
        ], $this->buildParts($spec));

        $this->assertFalse($this->resolve($parts)->exportReady, $because);
    }

    public static function exportSuppressors(): array
    {
        return [
            'an active hold' => [
                ['holds' => ['stop_release']],
                'A held container cannot be released, however sound it is.',
            ],
            'an active hire' => [
                ['hire' => true],
                'On-hire stock belongs to a customer.',
            ],
            'a reefer with a lapsed PTI' => [
                ['container' => ['status' => 'available', 'type_code' => 'RF'], 'ptiValid' => false],
                'A reefer without a live PTI is not exportable.',
            ],
            'a released disposition' => [
                ['container' => ['status' => 'released']],
                'Only containers physically available or in the yard can be allocated.',
            ],
        ];
    }

    public function test_reserved_stock_is_not_export_ready(): void
    {
        $status = $this->resolve(['container' => $this->container(['status' => 'reserved'])]);

        $this->assertSame(Cat::RESERVED, $status->code);
        $this->assertFalse($status->exportReady,
            'Allocated stock is committed, not free — the booking screens ask for it separately.');
    }

    // ── The expiry boundary ──────────────────────────────────────────────────
    //
    // Every rung of the ladder moves because a row was saved, except one: a
    // reefer's PTI lapses because a date passed, and nothing saves. Rather than
    // recomputing the world nightly, the verdict records the date it stops
    // being true, and queries compare it at read time.

    public function test_a_dry_container_records_no_expiry(): void
    {
        $status = $this->resolve([
            'container'  => $this->container(['status' => 'available']),
            'workOrders' => [$this->wo('closed', ['qc_at' => Carbon::now()->subDay()])],
        ]);

        $this->assertTrue($status->exportReady);
        $this->assertNull($status->expiresAt,
            'Nothing about a dry container can go stale on its own.');
        $this->assertFalse($status->hasExpired());
    }

    public function test_a_reefer_records_the_date_its_readiness_lapses(): void
    {
        $until = Carbon::today()->addDays(30);

        $status = $this->resolve([
            'container'     => $this->container(['status' => 'available', 'type_code' => 'RF', 'pti_status' => 'passed']),
            'workOrders'    => [$this->wo('closed', ['qc_at' => Carbon::now()->subDay()])],
            'ptiValid'      => true,
            'ptiValidUntil' => $until,
        ]);

        $this->assertTrue($status->exportReady);
        $this->assertSame($until->toDateString(), $status->expiresAt?->toDateString(),
            'The boundary is what lets a query re-decide readiness without a scheduled job.');
        $this->assertFalse($status->hasExpired());
    }

    public function test_the_boundary_is_normalised_to_midnight(): void
    {
        $status = $this->resolve([
            'container'     => $this->container(['type_code' => 'RF', 'pti_status' => 'passed']),
            'ptiValid'      => true,
            'ptiValidUntil' => Carbon::today()->addDays(10)->setTime(14, 30),
        ]);

        $this->assertSame('00:00:00', $status->expiresAt?->format('H:i:s'),
            'An unchanged PTI must not look like a change and churn the row on every refresh.');
    }

    public function test_a_reefer_whose_pti_has_lapsed_reports_expired(): void
    {
        $status = $this->resolve([
            'container'     => $this->container(['status' => 'available', 'type_code' => 'RF', 'pti_status' => 'passed']),
            'workOrders'    => [$this->wo('closed', ['qc_at' => Carbon::now()->subDay()])],
            'ptiValid'      => false,
            'ptiValidUntil' => Carbon::today()->subDays(3),
        ]);

        $this->assertFalse($status->exportReady);
        $this->assertTrue($status->hasExpired());
    }

    public function test_a_reefer_with_no_pti_at_all_has_no_boundary(): void
    {
        $status = $this->resolve(['container' => $this->container(['type_code' => 'RF'])]);

        $this->assertSame(Cat::PTI_DUE, $status->code);
        $this->assertNull($status->expiresAt, 'There is no date to compare against.');
    }

    public function test_the_projection_carries_the_expiry(): void
    {
        $until = Carbon::today()->addDays(14);

        $projection = $this->resolve([
            'container'     => $this->container(['status' => 'available', 'type_code' => 'RF', 'pti_status' => 'passed']),
            'workOrders'    => [$this->wo('closed', ['qc_at' => Carbon::now()->subDay()])],
            'ptiValid'      => true,
            'ptiValidUntil' => $until,
        ])->toProjection();

        $this->assertArrayHasKey('mr_status_expires_at', $projection);
        $this->assertSame($until->toDateString(), $projection['mr_status_expires_at']?->toDateString());
    }

    // ── Catalogue integrity ──────────────────────────────────────────────────

    public function test_every_catalogue_code_has_a_label_group_and_badge(): void
    {
        foreach (Cat::codes() as $code) {
            $this->assertNotSame('', Cat::label($code), "{$code} has no label.");
            $this->assertArrayHasKey(Cat::group($code), Cat::groups(), "{$code} has an unknown group.");
            $this->assertNotSame('', Cat::badgeClass($code), "{$code} has no badge class.");
        }
    }

    public function test_every_reachable_status_is_in_the_catalogue(): void
    {
        foreach (self::ladderCases() as $name => [$expected, , ]) {
            $this->assertArrayHasKey($expected, Cat::CATALOGUE,
                "Case '{$name}' resolves to a code that is not in the catalogue.");
        }
    }

    public function test_ageing_thresholds_only_reference_real_codes(): void
    {
        foreach (array_keys(Cat::AGE_THRESHOLD_DAYS) as $code) {
            $this->assertArrayHasKey($code, Cat::CATALOGUE,
                "Threshold set for unknown status '{$code}'.");
        }
    }
}
