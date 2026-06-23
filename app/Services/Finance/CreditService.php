<?php

namespace App\Services\Finance;

use App\Models\Customer;

/**
 * Credit-exposure engine for the unified Contact/Party master.
 *
 * A contact can be a debtor (AR — they owe us) and a creditor (AP — we owe
 * them) at the same time, so exposure is tracked on two independent axes,
 * each with its own limit on the customer profile:
 *
 *   AR  →  credit_limit      (how much we let them owe us)
 *   AP  →  ap_credit_limit   (how much we let ourselves owe them)
 *
 * Exposure is the live outstanding balance computed from the sub-ledgers, not
 * a cached column, so it always reflects the current allocation state.
 */
class CreditService
{
    public function __construct(
        private ArAllocationService $arAlloc,
        private ApAllocationService $apAlloc,
    ) {}

    /** Request-scoped memo so a single page load never re-runs the same exposure query. */
    private array $arExposureCache = [];
    private array $apExposureCache = [];

    // ── Accounts Receivable (contact as debtor) ──────────────────────────────

    /** Total outstanding AR across all four invoice types. */
    public function arExposure(int $customerId): float
    {
        return $this->arExposureCache[$customerId] ??= round(
            (float) $this->arAlloc->pendingForCustomer($customerId)->sum('outstanding'), 2
        );
    }

    /**
     * Remaining AR headroom, or null when no limit is set (limit 0 = unlimited).
     */
    public function arAvailable(Customer $customer): ?float
    {
        $limit = (float) $customer->credit_limit;
        if ($limit <= 0) {
            return null;
        }

        return round($limit - $this->arExposure((int) $customer->id), 2);
    }

    /** True when AR exposure has breached a non-zero credit limit. */
    public function isArOverLimit(Customer $customer): bool
    {
        $limit = (float) $customer->credit_limit;
        if ($limit <= 0) {
            return false;
        }

        return $this->arExposure((int) $customer->id) > round($limit + 0.005, 2);
    }

    // ── Accounts Payable (contact as creditor) ───────────────────────────────

    /** Total outstanding AP we owe this contact (open bills, net of settlements). */
    public function apExposure(Customer $customer): float
    {
        return $this->apExposureCache[$customer->id] ??= round(
            (float) $customer->supplierInvoices()
                ->whereIn('status', ['approved', 'partially_paid'])
                ->get()
                ->sum(fn ($inv) => $this->apAlloc->getOutstanding($inv)),
            2
        );
    }

    /** Remaining AP headroom, or null when no limit is set. */
    public function apAvailable(Customer $customer): ?float
    {
        $limit = (float) $customer->ap_credit_limit;
        if ($limit <= 0) {
            return null;
        }

        return round($limit - $this->apExposure($customer), 2);
    }

    /** True when AP exposure has breached a non-zero AP credit limit. */
    public function isApOverLimit(Customer $customer): bool
    {
        $limit = (float) $customer->ap_credit_limit;
        if ($limit <= 0) {
            return false;
        }

        return $this->apExposure($customer) > round($limit + 0.005, 2);
    }

    /**
     * Build the soft-warning message shown when issuing an AR invoice pushes a
     * contact over their credit limit, or null when they are within limit.
     */
    public function arOverLimitWarning(Customer $customer): ?string
    {
        $limit = (float) $customer->credit_limit;
        if ($limit <= 0) {
            return null;
        }

        $exposure = $this->arExposure((int) $customer->id);
        if ($exposure <= round($limit + 0.005, 2)) {
            return null;
        }

        return "Credit alert: {$customer->name} is now over their AR credit limit — "
            . "outstanding {$customer->currency} " . number_format($exposure, 2)
            . " exceeds the limit of {$customer->currency} " . number_format($limit, 2) . '.';
    }
}
