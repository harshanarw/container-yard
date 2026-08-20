<?php

namespace App\Support;

/**
 * The M&R status vocabulary: every code a container can hold, the group that
 * drives its badge colour and report roll-up, and the lane it belongs to.
 *
 * Deliberately a class of constants with static maps rather than a native enum,
 * matching the idiom already in use (YardJob::statusBadgeClass/statusLabel) and
 * the fact that these values are persisted as plain strings — the catalogue is
 * expected to grow, and the codebase already carries the scar of ALTER TABLE
 * migrations widening enum columns.
 */
final class MrStatusCatalogue
{
    // ── Groups (badge colour + report roll-up) ────────────────────────────────
    public const GROUP_PENDING     = 'pending';
    public const GROUP_IN_PROGRESS = 'in_progress';
    public const GROUP_READY       = 'ready';
    public const GROUP_BLOCKED     = 'blocked';
    public const GROUP_COMMITTED   = 'committed';
    public const GROUP_IDLE        = 'idle';
    public const GROUP_CLOSED      = 'closed';

    // ── Lanes (the job-type classification axis) ──────────────────────────────
    public const LANE_REPAIR   = 'repair';
    public const LANE_WASH     = 'wash';
    public const LANE_REEFER   = 'reefer';
    public const LANE_TRANSFER = 'transfer';
    public const LANE_STORAGE  = 'storage';
    public const LANE_HANDLING = 'handling';

    /** Headline lane precedence when a container occupies several at once. */
    public const LANE_PRIORITY = [
        self::LANE_REPAIR,
        self::LANE_WASH,
        self::LANE_REEFER,
        self::LANE_TRANSFER,
        self::LANE_STORAGE,
        self::LANE_HANDLING,
    ];

    // ── Status codes ──────────────────────────────────────────────────────────
    public const AWAITING_SURVEY      = 'awaiting_survey';
    public const SURVEY_IN_PROGRESS   = 'survey_in_progress';
    public const ESTIMATE_PENDING     = 'estimate_pending';
    public const ESTIMATE_SENT        = 'estimate_sent';
    public const ESTIMATE_REJECTED    = 'estimate_rejected';
    public const ESTIMATE_APPROVED    = 'estimate_approved';
    public const REPAIR_SCHEDULED     = 'repair_scheduled';
    public const REPAIR_IN_PROGRESS   = 'repair_in_progress';
    public const REPAIR_ON_HOLD       = 'repair_on_hold';
    public const AWAITING_QC          = 'awaiting_qc';
    public const QC_FAILED            = 'qc_failed';
    public const REPAIRED_AVAILABLE   = 'repaired_available';
    public const WASH_SCHEDULED       = 'wash_scheduled';
    public const WASH_IN_PROGRESS     = 'wash_in_progress';
    public const WASHED               = 'washed';
    public const PTI_DUE              = 'pti_due';
    public const PTI_FAILED           = 'pti_failed';
    public const CONDEMNED            = 'condemned';
    public const SOUND_AVAILABLE      = 'sound_available';
    public const IN_STORAGE           = 'in_storage';
    public const TRANSFER_IN_PROGRESS = 'transfer_in_progress';
    public const ON_HIRE              = 'on_hire';
    public const RESERVED             = 'reserved';
    public const AWAITING_DISPOSITION = 'awaiting_disposition';
    public const GATED_OUT            = 'gated_out';

    /**
     * code => [label, group, lane]
     *
     * A null lane means the status is lane-independent — it describes the box
     * itself rather than a stage of work on it.
     */
    public const CATALOGUE = [
        self::AWAITING_SURVEY      => ['Awaiting survey',                'pending',     self::LANE_REPAIR],
        self::SURVEY_IN_PROGRESS   => ['Survey in progress',             'in_progress', self::LANE_REPAIR],
        self::ESTIMATE_PENDING     => ['Estimate in preparation',        'pending',     self::LANE_REPAIR],
        self::ESTIMATE_SENT        => ['Estimate sent — awaiting approval', 'pending',  self::LANE_REPAIR],
        self::ESTIMATE_REJECTED    => ['Estimate rejected',              'blocked',     self::LANE_REPAIR],
        self::ESTIMATE_APPROVED    => ['Approved — awaiting work order', 'pending',     self::LANE_REPAIR],
        self::REPAIR_SCHEDULED     => ['Repair scheduled',               'pending',     self::LANE_REPAIR],
        self::REPAIR_IN_PROGRESS   => ['Repair in progress',             'in_progress', self::LANE_REPAIR],
        self::REPAIR_ON_HOLD       => ['Repair on hold',                 'blocked',     self::LANE_REPAIR],
        self::AWAITING_QC          => ['Awaiting QC',                    'pending',     self::LANE_REPAIR],
        self::QC_FAILED            => ['QC failed — rework',             'blocked',     self::LANE_REPAIR],
        self::REPAIRED_AVAILABLE   => ['Repaired — available',           'ready',       self::LANE_REPAIR],
        self::WASH_SCHEDULED       => ['Wash scheduled',                 'pending',     self::LANE_WASH],
        self::WASH_IN_PROGRESS     => ['Washing',                        'in_progress', self::LANE_WASH],
        self::WASHED               => ['Washed — available',             'ready',       self::LANE_WASH],
        self::PTI_DUE              => ['PTI due',                        'pending',     self::LANE_REEFER],
        self::PTI_FAILED           => ['PTI failed',                     'blocked',     self::LANE_REEFER],
        self::CONDEMNED            => ['Condemned / scrap',              'blocked',     null],
        self::SOUND_AVAILABLE      => ['Sound — available',              'ready',       null],
        self::IN_STORAGE           => ['In storage',                     'idle',        self::LANE_STORAGE],
        self::TRANSFER_IN_PROGRESS => ['Cargo transfer in progress',     'in_progress', self::LANE_TRANSFER],
        self::ON_HIRE              => ['On hire',                        'committed',   null],
        self::RESERVED             => ['Reserved to booking',            'committed',   null],
        self::AWAITING_DISPOSITION => ['In yard — awaiting disposition', 'idle',        self::LANE_HANDLING],
        self::GATED_OUT            => ['Gated out',                      'closed',      null],
    ];

    // ── Modifiers (independent of the status, rendered as chips) ──────────────
    public const MODIFIER_HELD        = 'held';
    public const MODIFIER_PTI_EXPIRED = 'pti_expired';
    public const MODIFIER_OVERDUE     = 'overdue';

    /**
     * Repair-category codes that mean "wash / cleaning" rather than repair.
     *
     * Repair categories are editable master data, so this is the one seam where
     * the distinction lives — a new cleaning category needs adding here, not
     * hunting through the resolver.
     */
    public const WASH_CATEGORY_CODES = ['CLN'];

    /**
     * Days in a stage before it is flagged overdue — the shipped defaults.
     *
     * These are the baseline, not the last word: the effective values come from
     * Company Settings, which merges over this map
     * (ContainerMrStatusService::ageThresholds). A yard that has never touched
     * the setting runs on exactly these numbers.
     *
     * The clock is per *stage*, not per visit. A container can be in the yard
     * forty days without being overdue if it only entered its current stage
     * yesterday — the flag measures the stall, not the stay.
     *
     * Stages absent from this map are never flagged, deliberately: sitting in
     * storage, on hire, reserved or available is not a stall. The two that look
     * like stalls but have no natural owner — a rejected estimate nobody
     * actioned, and a container in the yard with no chain attached — do carry
     * one.
     */
    public const AGE_THRESHOLD_DAYS = [
        self::AWAITING_SURVEY      => 2,
        self::SURVEY_IN_PROGRESS   => 3,
        self::ESTIMATE_PENDING     => 3,
        self::ESTIMATE_SENT        => 7,
        self::ESTIMATE_REJECTED    => 5,
        self::ESTIMATE_APPROVED    => 3,
        self::REPAIR_SCHEDULED     => 5,
        self::REPAIR_IN_PROGRESS   => 10,
        self::REPAIR_ON_HOLD       => 5,
        self::AWAITING_QC          => 3,
        self::QC_FAILED            => 5,
        self::WASH_SCHEDULED       => 3,
        self::WASH_IN_PROGRESS     => 3,
        self::PTI_DUE              => 7,
        self::PTI_FAILED           => 5,
        self::AWAITING_DISPOSITION => 14,
    ];

    /**
     * Wash and repair share the whole work-order machinery, so the stages that
     * exist in both lanes are stored under one code — filters and reports stay
     * simple — and only the wording follows the lane.
     *
     * Only the stages whose repair wording would actually mislead an operator
     * are overridden; 'Repair scheduled' and 'Repair in progress' have their own
     * codes already (wash_scheduled / wash_in_progress).
     */
    private const WASH_LABEL_OVERRIDES = [
        self::REPAIR_ON_HOLD => 'Wash on hold',
        self::AWAITING_QC    => 'Awaiting wash check',
        self::QC_FAILED      => 'Wash check failed — rework',
    ];

    public static function label(string $code, ?string $lane = null): string
    {
        if ($lane === self::LANE_WASH && isset(self::WASH_LABEL_OVERRIDES[$code])) {
            return self::WASH_LABEL_OVERRIDES[$code];
        }

        return self::CATALOGUE[$code][0] ?? ucfirst(str_replace('_', ' ', $code));
    }

    public static function group(string $code): string
    {
        return self::CATALOGUE[$code][1] ?? self::GROUP_IDLE;
    }

    public static function lane(string $code): ?string
    {
        return self::CATALOGUE[$code][2] ?? null;
    }

    public static function codes(): array
    {
        return array_keys(self::CATALOGUE);
    }

    /** Codes grouped by lane, for the lane-grouped filter dropdown. */
    public static function codesByLane(): array
    {
        $out = [];
        foreach (self::CATALOGUE as $code => [$label, $group, $lane]) {
            $out[$lane ?? 'general'][$code] = $label;
        }

        return $out;
    }

    /** Display name for a lane — the optgroup headings in the status filter. */
    public static function laneLabel(?string $lane): string
    {
        return match ($lane) {
            self::LANE_REPAIR   => 'Repair',
            self::LANE_WASH     => 'Wash / Cleaning',
            self::LANE_REEFER   => 'Reefer',
            self::LANE_TRANSFER => 'Cargo Transfer',
            self::LANE_STORAGE  => 'Storage',
            self::LANE_HANDLING => 'Handling',
            default             => 'General',
        };
    }

    public static function groups(): array
    {
        return [
            self::GROUP_PENDING     => 'Pending',
            self::GROUP_IN_PROGRESS => 'In progress',
            self::GROUP_READY       => 'Ready',
            self::GROUP_BLOCKED     => 'Blocked',
            self::GROUP_COMMITTED   => 'Committed',
            self::GROUP_IDLE        => 'Idle',
            self::GROUP_CLOSED      => 'Closed',
        ];
    }

    /** Bootstrap badge classes, matching the YardJob::statusBadgeClass idiom. */
    public static function badgeClass(string $code): string
    {
        return match (self::group($code)) {
            self::GROUP_PENDING     => 'bg-warning text-dark',
            self::GROUP_IN_PROGRESS => 'bg-primary',
            self::GROUP_READY       => 'bg-success',
            self::GROUP_BLOCKED     => 'bg-danger',
            self::GROUP_COMMITTED   => 'bg-info text-dark',
            self::GROUP_IDLE        => 'bg-secondary',
            self::GROUP_CLOSED      => 'bg-light text-dark border',
            default                 => 'bg-light text-dark border',
        };
    }

    public static function modifierLabel(string $modifier): string
    {
        return match ($modifier) {
            self::MODIFIER_HELD        => 'On hold',
            self::MODIFIER_PTI_EXPIRED => 'PTI expired',
            self::MODIFIER_OVERDUE     => 'Overdue',
            default                    => ucfirst(str_replace('_', ' ', $modifier)),
        };
    }
}
