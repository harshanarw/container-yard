<?php

namespace App\Services;

use App\Models\CargoTransfer;
use App\Models\Container;
use App\Models\ContainerHire;
use App\Models\ContainerHold;
use App\Models\Estimate;
use App\Models\GateMovement;
use App\Models\Inquiry;
use App\Models\RepairCategory;
use App\Models\WorkOrder;
use App\Support\MrStatus;
use App\Support\MrStatusCatalogue as Cat;
use App\Support\MrStatusContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The single source of truth for "what is this container waiting on?".
 *
 * `containers.status` answers *where* a container is (in the yard, in repair,
 * released). This answers *what stage of work* it is at, in the vocabulary of
 * the job type that brought it in — which is the question the yard actually
 * asks and the one no existing field could answer.
 *
 * resolve() runs no queries. It decides purely from an MrStatusContext that a
 * caller assembled, which is what makes the ladder testable as a table of
 * plain cases and lets a paginated page resolve from a handful of queries
 * rather than a handful per row.
 */
class ContainerMrStatusService
{
    /** Memoised for the request — repair categories are small, static master data. */
    private ?array $washCategoryIds = null;

    // ─────────────────────────────────────────────────────────────────────────
    // Resolution
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Decide the status for one gate-in cycle.
     *
     * First match wins. The order is the design: later stages override the
     * earlier ones they grew out of, so an open work order beats the survey
     * that produced it.
     */
    public function resolve(MrStatusContext $ctx): MrStatus
    {
        [$code, $lane, $since] = $this->ladder($ctx);

        $otherLanes = array_values(array_diff($ctx->activeLanes(), [$lane]));

        return new MrStatus(
            code:        $code,
            lane:        $lane,
            since:       $since ? Carbon::parse($since) : null,
            modifiers:   $this->modifiersFor($ctx, $code, $since),
            exportReady: $this->isExportReady($ctx, $code),
            otherLanes:  $otherLanes,
            expiresAt:   $this->expiresAt($ctx),
        );
    }

    /**
     * The date this verdict stops being true on its own.
     *
     * Every other rung of the ladder moves because a row was saved, so an
     * observer catches it. A reefer's PTI is the exception: it lapses because a
     * date passed, and nothing saves. Recording the boundary lets a query ask
     * "is this still true?" at read time — which is exact — instead of a
     * nightly job re-deciding it, which is stale for up to a day.
     *
     * Null means the stored verdict cannot age out by itself.
     */
    private function expiresAt(MrStatusContext $ctx): ?Carbon
    {
        if (! $ctx->isReefer() || ! $ctx->ptiValidUntil) {
            return null;
        }

        // Normalised to the start of the day so an unchanged PTI never looks
        // like a change and churns the row on every refresh.
        return $ctx->ptiValidUntil->copy()->startOfDay();
    }

    /**
     * The ladder. Returns [code, lane, since].
     *
     * @return array{0:string,1:?string,2:mixed}
     */
    private function ladder(MrStatusContext $ctx): array
    {
        // 1 — The cycle is over. Whatever happened during it is now history.
        if ($ctx->isClosed()) {
            return [Cat::GATED_OUT, null, $ctx->gateOut?->gate_out_time];
        }

        // 2 — Written off. Beats any repair still nominally in flight, because
        //     a scrapped box must never read as work-in-progress.
        if ($ctx->surveyRecommends('scrap')) {
            $inq = $ctx->latestInquiry();

            return [Cat::CONDEMNED, null, $inq?->inspection_date ?? $inq?->created_at];
        }

        // 3 — Out on hire: committed to a customer, not the yard's to work on.
        if ($ctx->isOnHire()) {
            return [Cat::ON_HIRE, null, $ctx->activeHire?->on_hire_date];
        }

        // 4 — QC rejected it. The one state where a container looks finished and
        //     is not, so it outranks every completed-work branch below.
        if ($wo = $ctx->workOrderWithStatus('rejected')) {
            return [Cat::QC_FAILED, $ctx->laneForWorkOrder($wo), $wo->qc_at];
        }

        // 5 — Work started then stopped.
        if ($wo = $ctx->workOrderWithStatus('on_hold')) {
            return [Cat::REPAIR_ON_HOLD, $ctx->laneForWorkOrder($wo), $wo->started_date ?? $wo->updated_at];
        }

        // 6 — Work happening right now.
        if ($wo = $ctx->workOrderWithStatus('in_progress')) {
            $lane = $ctx->laneForWorkOrder($wo);

            return [
                $lane === Cat::LANE_WASH ? Cat::WASH_IN_PROGRESS : Cat::REPAIR_IN_PROGRESS,
                $lane,
                $wo->started_date ?? $wo->created_at,
            ];
        }

        // 7 — Work finished, waiting on the supervisor to sign it off.
        if ($wo = $ctx->workOrderWithStatus('completed')) {
            return [Cat::AWAITING_QC, $ctx->laneForWorkOrder($wo), $wo->completed_date ?? $wo->updated_at];
        }

        // 8 — Raised but not started.
        if ($wo = $ctx->workOrderWithStatus('pending')) {
            $lane = $ctx->laneForWorkOrder($wo);

            return [
                $lane === Cat::LANE_WASH ? Cat::WASH_SCHEDULED : Cat::REPAIR_SCHEDULED,
                $lane,
                $wo->created_at,
            ];
        }

        // 9 — Approved, but no work order has actually run. This is the gap
        //     where jobs quietly stall, so it gets its own status.
        //
        //     The guard is "nothing has run", not "nothing is live": a closed
        //     work order means the repair finished, and that belongs to rung 14
        //     below. Testing only for a live work order would leave every
        //     repaired container reading "awaiting work order" forever. Work
        //     orders that were all cancelled still land here — the work was
        //     approved and still needs raising.
        if (! $ctx->hasOpenWorkOrder()
            && $ctx->closedWorkOrders()->isEmpty()
            && ($est = $ctx->estimateWithStatus('approved', 'partially_approved'))) {
            return [Cat::ESTIMATE_APPROVED, Cat::LANE_REPAIR, $est->approved_date ?? $est->estimate_date];
        }

        // 10 — The customer said no.
        if ($ctx->latestEstimate()?->status === 'rejected') {
            $est = $ctx->latestEstimate();

            return [Cat::ESTIMATE_REJECTED, Cat::LANE_REPAIR, $est->updated_at ?? $est->estimate_date];
        }

        // 11 — With the customer, awaiting a decision.
        if ($est = $ctx->estimateWithStatus('sent', 'under_review')) {
            return [Cat::ESTIMATE_SENT, Cat::LANE_REPAIR, $est->sent_at ?? $est->estimate_date];
        }
        if ($ctx->latestInquiry()?->status === 'estimate_sent') {
            return [Cat::ESTIMATE_SENT, Cat::LANE_REPAIR, $ctx->latestInquiry()?->updated_at];
        }

        // 12 — Being priced, or needing to be.
        if ($est = $ctx->estimateWithStatus('draft')) {
            return [Cat::ESTIMATE_PENDING, Cat::LANE_REPAIR, $est->estimate_date ?? $est->created_at];
        }
        if ($ctx->surveyRecommends('repair') && $ctx->estimates->isEmpty()) {
            $inq = $ctx->latestInquiry();

            return [Cat::ESTIMATE_PENDING, Cat::LANE_REPAIR, $inq?->inspection_date ?? $inq?->created_at];
        }

        // 13 — Survey underway.
        if ($ctx->hasOpenInquiry()) {
            $inq = $ctx->inquiries->first(
                fn ($i) => in_array($i->status, ['open', 'in_progress'], true)
            );

            return [Cat::SURVEY_IN_PROGRESS, Cat::LANE_REPAIR, $inq?->inspection_date ?? $inq?->created_at];
        }

        // 14 — Everything raised this cycle ran to a QC pass.
        $closed = $ctx->closedWorkOrders();
        if ($closed->isNotEmpty() && ! $ctx->hasOpenWorkOrder()) {
            $last = $closed->first();
            $lane = $ctx->laneForWorkOrder($last);

            return [
                $lane === Cat::LANE_WASH ? Cat::WASHED : Cat::REPAIRED_AVAILABLE,
                $lane,
                $last->qc_at ?? $last->completed_date,
            ];
        }

        // 15 — Surveyed and found not to need work.
        if ($ctx->surveyRecommends('no_action', 'monitor') && $ctx->workOrders->isEmpty()) {
            $inq = $ctx->latestInquiry();

            return [Cat::SOUND_AVAILABLE, null, $inq?->inspection_date ?? $inq?->created_at];
        }

        // 16 — Allocated to a booking. Committed stock, not free stock.
        if ($ctx->container->status === 'reserved') {
            return [Cat::RESERVED, null, $ctx->container->reserved_at ?? $ctx->container->status_changed_at];
        }

        // 17 — Standing in for another box while its cargo is cross-stuffed.
        if ($ctx->activeTransfer !== null) {
            return [Cat::TRANSFER_IN_PROGRESS, Cat::LANE_TRANSFER, $ctx->activeTransfer->transfer_date];
        }

        // 18 — Reefer with no live PTI. Ranked below repair deliberately: a
        //      reefer mid-repair should read "repair in progress", not "PTI due".
        //      An expired PTI still suppresses export readiness and still shows
        //      as a chip, so ranking it low loses nothing.
        if (($ctx->isReefer() || $ctx->jobTypeAllows('reefer_applicable')) && ! $ctx->ptiValid) {
            return $ctx->container->pti_status === 'failed'
                ? [Cat::PTI_FAILED, Cat::LANE_REEFER, $ctx->container->pti_at]
                : [Cat::PTI_DUE, Cat::LANE_REEFER, $ctx->container->pti_at ?? $ctx->gateIn?->gate_in_time];
        }

        // 19 — The job type says it should be surveyed and nothing has been.
        if ($ctx->jobTypeAllows('survey_applicable') && ! $ctx->hasInquiry()) {
            return [Cat::AWAITING_SURVEY, Cat::LANE_REPAIR, $ctx->gateIn?->gate_in_time];
        }

        // 20 — Here purely to sit.
        if ($ctx->isStorageOnly()) {
            return [Cat::IN_STORAGE, Cat::LANE_STORAGE, $ctx->gateIn?->gate_in_time];
        }

        // 21 — In the yard with no chain attached to it. Never a confident
        //      wrong answer: an incomplete history lands here by design.
        return [Cat::AWAITING_DISPOSITION, Cat::LANE_HANDLING, $ctx->gateIn?->gate_in_time];
    }

    /**
     * Facts that are true alongside the status rather than instead of it.
     *
     * A container can be under repair *and* under customs hold; collapsing the
     * two into one value loses the half that matters at the gate.
     */
    private function modifiersFor(MrStatusContext $ctx, string $code, mixed $since): array
    {
        $modifiers = [];

        if ($ctx->isHeld()) {
            $modifiers[] = Cat::MODIFIER_HELD;
            foreach ($ctx->holdTypes() as $type) {
                $modifiers[] = 'hold_' . $type;
            }
        }

        if ($ctx->isReefer() && ! $ctx->ptiValid && ! in_array($code, [Cat::PTI_DUE, Cat::PTI_FAILED], true)) {
            $modifiers[] = Cat::MODIFIER_PTI_EXPIRED;
        }

        $threshold = Cat::AGE_THRESHOLD_DAYS[$code] ?? null;
        if ($threshold !== null && $since) {
            if ((int) Carbon::parse($since)->diffInDays(Carbon::now()) > $threshold) {
                $modifiers[] = Cat::MODIFIER_OVERDUE;
            }
        }

        return $modifiers;
    }

    /**
     * Free to leave on an export booking.
     *
     * 'reserved' is deliberately excluded — allocated stock is committed, not
     * free. Screens that want *allocatable* stock want export-ready plus their
     * own reservations, which stays a screen-level concern.
     */
    private function isExportReady(MrStatusContext $ctx, string $code): bool
    {
        if (Cat::group($code) !== Cat::GROUP_READY) {
            return false;
        }

        if ($ctx->isHeld() || $ctx->isOnHire()) {
            return false;
        }

        if (! in_array($ctx->container->status, ['available', 'in_yard'], true)) {
            return false;
        }

        return ! $ctx->isReefer() || $ctx->ptiValid;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Projection — the ONLY writer of the mr_status columns
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Recompute a container's status and write both projections.
     *
     * Idempotent: writes nothing when the stored values already match, so hooks
     * can call it freely and the reconcile can run daily without churn.
     *
     * Saves quietly on purpose. These columns are derived, not user actions —
     * auditing them would bury the real edits, and firing model events here
     * would have the projection observer re-enter this method.
     *
     * @return bool true when something actually changed.
     */
    public function refresh(Container $container): bool
    {
        $ctx    = $this->contextForContainer($container);
        $status = $this->resolve($ctx);

        $changed = $this->writeContainerProjection($container, $status);

        // While a cycle is open the two projections agree by construction: the
        // open gate-in row IS the current cycle.
        if ($ctx->gateIn && $this->writeGateInProjection($ctx->gateIn, $status)) {
            $changed = true;
        }

        return $changed;
    }

    /**
     * Write the cycle projection for a batch of gate-in rows, history included.
     *
     * refresh() only covers a container's *current* cycle. Container Inquiry
     * lists closed cycles too, and each must show what that visit ended as — so
     * the backfill and the reconcile drive historical rows through here.
     *
     * @param  Collection<int,GateMovement> $gateIns
     * @return int number of rows changed
     */
    public function refreshGateIns(Collection $gateIns): int
    {
        $resolved = $this->forGateIns($gateIns);
        $changed  = 0;

        foreach ($gateIns as $gateIn) {
            $status = $resolved[$gateIn->id] ?? null;

            if ($status && $this->writeGateInProjection($gateIn, $status)) {
                $changed++;
            }
        }

        return $changed;
    }

    private function writeContainerProjection(Container $container, MrStatus $status): bool
    {
        $new = $status->toProjection();

        if (! $this->differs($container, $new)) {
            return false;
        }

        $container->forceFill($new)->saveQuietly();

        return true;
    }

    private function writeGateInProjection(GateMovement $gateIn, MrStatus $status): bool
    {
        $new = [
            'mr_status'       => $status->code,
            'mr_status_group' => $status->group(),
            'mr_status_at'    => $status->since,
        ];

        if (! $this->differs($gateIn, $new)) {
            return false;
        }

        $gateIn->forceFill($new)->saveQuietly();

        return true;
    }

    /** Would writing $new change anything? Keeps refresh() idempotent. */
    private function differs($model, array $new): bool
    {
        foreach ($new as $key => $value) {
            $current = $model->getAttribute($key);

            if ($value instanceof Carbon || $current instanceof Carbon) {
                $a = $current instanceof Carbon ? $current->toDateTimeString() : (string) $current;
                $b = $value instanceof Carbon ? $value->toDateTimeString() : (string) $value;

                if ($a !== $b) {
                    return true;
                }

                continue;
            }

            if (is_bool($value)) {
                if ((bool) $current !== $value) {
                    return true;
                }

                continue;
            }

            if ($current !== $value) {
                return true;
            }
        }

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Query predicates — so filters and the resolver never drift apart
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Filter by status code. Works on either projection, because both columns
     * are named the same — pass a containers query or a gate_movements one.
     */
    public function scopeFor($query, string $code)
    {
        return $query->where('mr_status', $code);
    }

    /** Filter by status group (pending / in_progress / ready / blocked / …). */
    public function scopeForGroup($query, string $group)
    {
        return $query->where('mr_status_group', $group);
    }

    /**
     * Containers free to leave on an export booking. Containers query only.
     *
     * Delegates to Container::scopeExportReady rather than restating the
     * predicate: two copies of "what counts as ready" is precisely the drift
     * this projection exists to avoid.
     */
    public function scopeExportReady($query)
    {
        return $query->exportReady();
    }

    /**
     * Containers whose stored status rested on something dated that has since
     * passed — today, a reefer with a lapsed PTI. Containers query only.
     */
    public function scopeStatusExpired($query)
    {
        return $query->statusExpired();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Loading
    // ─────────────────────────────────────────────────────────────────────────

    /** Current status for one container, from its open cycle. */
    public function forContainer(Container $container): MrStatus
    {
        return $this->resolve($this->contextForContainer($container));
    }

    /**
     * Assemble the context for a container's current (open) cycle.
     *
     * The open cycle is the latest gate-in with no matching gate-out. A
     * container that is not in the yard has no open cycle, and resolves against
     * its last gate-out.
     */
    public function contextForContainer(Container $container): MrStatusContext
    {
        $movements = GateMovement::where('container_id', $container->id)
            ->with('yardJob.jobType')
            ->get();

        $gateIns  = $movements->where('movement_type', 'in')->values();
        $gateOuts = $movements->where('movement_type', 'out')->values();

        // The current cycle is the newest gate-in; pairing needs all of them,
        // because which gate-out belongs to which visit depends on the ones
        // around it.
        $gateIn = $gateIns->sortByDesc(fn ($g) => [$g->gate_in_time?->timestamp ?? 0, $g->id])->first();

        $gateOut = $gateIn
            ? ($this->pairGateOuts($gateIns, $gateOuts)[$gateIn->id] ?? null)
            : null;

        $from  = $gateIn?->gate_in_time;
        $until = $gateOut?->gate_out_time;

        // Both PTI facts from one lookup — the same path the batch loader uses,
        // so a single container and a whole page cannot disagree.
        $pti = $this->ptiStateFor(collect([$container->id => $container]))[$container->id] ?? null;

        return new MrStatusContext(
            container:       $container,
            gateIn:          $gateIn,
            gateOut:         $gateOut,
            jobType:         $this->jobTypeFor($gateIn),
            inquiries:       $this->cycleScoped(Inquiry::where('container_id', $container->id), 'created_at', $from, $until)->get(),
            estimates:       $this->cycleScoped(Estimate::where('container_id', $container->id), 'estimate_date', $from, $until)->get(),
            workOrders:      $this->cycleScoped(WorkOrder::where('container_id', $container->id), 'created_at', $from, $until)->get(),
            activeHolds:     ContainerHold::where('container_id', $container->id)->whereNull('cleared_at')->get(),
            activeHire:      ContainerHire::where('container_id', $container->id)->where('status', 'active')->first(),
            activeTransfer:  $this->activeTransferFor($container),
            ptiValid:        $pti['valid'] ?? false,
            washCategoryIds: $this->washCategoryIds(),
            ptiValidUntil:   $pti['until'] ?? null,
        );
    }

    /**
     * Resolve a whole page of gate-in movements at once.
     *
     * One query per chain for the entire page rather than one per row — the
     * inquiry list paginates gate-in movements, so a per-row derivation would
     * be four or five extra queries every page.
     *
     * @param  Collection<int,GateMovement> $gateIns
     * @return array<int,MrStatus>          keyed by gate-in id
     */
    public function forGateIns(Collection $gateIns): array
    {
        if ($gateIns->isEmpty()) {
            return [];
        }

        $containerIds = $gateIns->pluck('container_id')->filter()->unique()->values();
        $containerNos = $gateIns->pluck('container_no')->filter()->unique()->values();

        $containers = Container::whereIn('id', $containerIds)->get()->keyBy('id');

        // Every movement for these containers, not just the page's gate-ins:
        // which gate-out closed a visit depends on the visits around it, so a
        // page-local view would mis-pair the first and last rows on the page.
        $movements = GateMovement::whereIn('container_no', $containerNos)
            ->get()
            ->groupBy('container_no');

        $gateOutMap = [];
        foreach ($movements as $perContainer) {
            $gateOutMap += $this->pairGateOuts(
                $perContainer->where('movement_type', 'in')->values(),
                $perContainer->where('movement_type', 'out')->values(),
            );
        }

        $inquiries  = Inquiry::whereIn('container_no', $containerNos)->get()->groupBy('container_no');
        $estimates  = Estimate::whereIn('container_no', $containerNos)->get()->groupBy('container_no');
        $workOrders = WorkOrder::whereIn('container_no', $containerNos)->get()->groupBy('container_no');

        $holds = ContainerHold::whereIn('container_id', $containerIds)
            ->whereNull('cleared_at')->get()->groupBy('container_id');

        $hires = ContainerHire::whereIn('container_id', $containerIds)
            ->where('status', 'active')->get()->keyBy('container_id');

        $transfers = CargoTransfer::where('status', 'active')
            ->where(fn ($q) => $q->whereIn('source_container_id', $containerIds)
                                 ->orWhereIn('substitute_container_id', $containerIds))
            ->get();

        $ptiState = $this->ptiStateFor($containers);
        $washIds  = $this->washCategoryIds();

        $out = [];

        foreach ($gateIns as $gateIn) {
            $container = $containers->get($gateIn->container_id);
            if (! $container) {
                continue;
            }

            $cno     = $gateIn->container_no;
            $from    = $gateIn->gate_in_time;
            $gateOut = $gateOutMap[$gateIn->id] ?? null;
            $until   = $gateOut?->gate_out_time;

            $out[$gateIn->id] = $this->resolve(new MrStatusContext(
                container:       $container,
                gateIn:          $gateIn,
                gateOut:         $gateOut,
                jobType:         $this->jobTypeFor($gateIn),
                inquiries:       $this->within($inquiries->get($cno), 'created_at', $from, $until),
                estimates:       $this->within($estimates->get($cno), 'estimate_date', $from, $until),
                workOrders:      $this->within($workOrders->get($cno), 'created_at', $from, $until),
                activeHolds:     $holds->get($container->id) ?? collect(),
                activeHire:      $hires->get($container->id),
                activeTransfer:  $transfers->first(fn ($t) => (int) $t->source_container_id === (int) $container->id
                                                          || (int) $t->substitute_container_id === (int) $container->id),
                ptiValid:        $ptiState[$container->id]['valid'] ?? false,
                washCategoryIds: $washIds,
                ptiValidUntil:   $ptiState[$container->id]['until'] ?? null,
            ));
        }

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Pair each gate-in with the gate-out that closed its visit.
     *
     * Mirrors ContainerInquiryService::buildGateOutMap() on purpose, including
     * its precedence: an explicit shared yard_job_id wins, and only unpaired
     * gate-outs fall back to the time window running up to the next gate-in.
     *
     * The naive "first gate-out after this gate-in" is wrong here, and wrong in
     * a way this yard actually sees: a box that goes out and back in on the
     * same day gives two visits whose time windows collapse, and the job link
     * is the only thing that still separates them. Getting this different from
     * the inquiry screen would put a cycle's status on the wrong row.
     *
     * @param  Collection<int,GateMovement> $gateIns
     * @param  Collection<int,GateMovement> $gateOuts
     * @return array<int,GateMovement>      keyed by gate-in id
     */
    private function pairGateOuts(Collection $gateIns, Collection $gateOuts): array
    {
        $map     = [];
        $usedIds = [];

        $byJobId = $gateOuts
            ->filter(fn ($go) => ! is_null($go->yard_job_id))
            ->keyBy('yard_job_id');

        $orphans = $gateOuts
            ->filter(fn ($go) => is_null($go->yard_job_id))
            ->sortBy('gate_out_time')
            ->values();

        $sorted = $gateIns->sortBy('gate_in_time')->values();

        foreach ($sorted as $i => $gateIn) {
            if (! is_null($gateIn->yard_job_id) && $byJobId->has($gateIn->yard_job_id)) {
                $go = $byJobId->get($gateIn->yard_job_id);
                $map[$gateIn->id] = $go;
                $usedIds[$go->id] = true;

                continue;
            }

            $from  = $gateIn->gate_in_time?->timestamp ?? 0;
            $until = $sorted->get($i + 1)?->gate_in_time?->timestamp ?? PHP_INT_MAX;

            foreach ($orphans as $go) {
                if (isset($usedIds[$go->id])) {
                    continue;
                }

                $ts = $go->gate_out_time?->timestamp ?? 0;

                if ($ts >= $from && $ts < $until) {
                    $map[$gateIn->id] = $go;
                    $usedIds[$go->id] = true;
                    break;
                }
            }
        }

        return $map;
    }

    /** The job type behind a gate-in, without lazy-loading during resolution. */
    private function jobTypeFor(?GateMovement $gateIn)
    {
        if (! $gateIn) {
            return null;
        }

        if ($gateIn->relationLoaded('yardJob') && $gateIn->yardJob?->relationLoaded('jobType')) {
            return $gateIn->yardJob->jobType;
        }

        return $gateIn->job_type_id
            ? \App\Models\YardJobType::find($gateIn->job_type_id)
            : $gateIn->yardJob?->jobType;
    }

    /** Window a query to one gate-in cycle. */
    private function cycleScoped($query, string $column, $from, $until)
    {
        return $query
            ->when($from, fn ($q) => $q->where($column, '>=', $from))
            ->when($until, fn ($q) => $q->where($column, '<=', $until));
    }

    /** Window an already-loaded collection to one gate-in cycle. */
    private function within(?Collection $records, string $column, $from, $until): Collection
    {
        if (! $records || ! $from) {
            return collect();
        }

        $fromTs  = $from instanceof Carbon ? $from->timestamp : Carbon::parse($from)->timestamp;
        $untilTs = $until
            ? ($until instanceof Carbon ? $until->timestamp : Carbon::parse($until)->timestamp)
            : PHP_INT_MAX;

        return $records->filter(function ($r) use ($column, $fromTs, $untilTs) {
            $value = $r->{$column};
            if (! $value) {
                return false;
            }
            $ts = $value instanceof Carbon ? $value->timestamp : Carbon::parse($value)->timestamp;

            return $ts >= $fromTs && $ts <= $untilTs;
        })->values();
    }

    /**
     * PTI state for a set of containers, in one query rather than one each.
     *
     * Returns both facts together because they are read together: whether the
     * PTI is live *now*, and the date it lapses. The second is what gets stored
     * so a query can re-decide the first at read time.
     *
     * @return array<int,array{valid:bool,until:?Carbon}> keyed by container id
     */
    private function ptiStateFor(Collection $containers): array
    {
        $reefers = $containers->filter(fn (Container $c) => $c->isReefer());

        if ($reefers->isEmpty()) {
            return [];
        }

        $latest = \App\Models\ReeferPtiInspection::whereIn('container_id', $reefers->keys())
            ->where('result', 'pass')
            ->orderByDesc('inspected_at')
            ->get()
            ->groupBy('container_id');

        $out = [];
        foreach ($reefers as $id => $container) {
            $pti = $latest->get($id)?->first();

            // valid_until is inclusive — the PTI stays valid through that whole
            // day, matching Container::hasValidPti().
            $out[$id] = [
                'valid' => $container->pti_status === 'passed'
                    && $pti
                    && (! $pti->valid_until || ! $pti->valid_until->lt(Carbon::today())),
                'until' => $pti?->valid_until,
            ];
        }

        return $out;
    }

    private function activeTransferFor(Container $container): ?CargoTransfer
    {
        return CargoTransfer::where('status', 'active')
            ->where(fn ($q) => $q->where('source_container_id', $container->id)
                                 ->orWhere('substitute_container_id', $container->id))
            ->first();
    }

    /** Repair categories that mean wash rather than structural repair. */
    public function washCategoryIds(): array
    {
        return $this->washCategoryIds ??= RepairCategory::whereIn('code', Cat::WASH_CATEGORY_CODES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
