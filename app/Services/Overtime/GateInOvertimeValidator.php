<?php

namespace App\Services\Overtime;

use App\Models\CompanySetting;
use App\Models\OtReceipt;
use Illuminate\Support\Carbon;

/**
 * Decides whether an out-of-hours gate-in needs an overtime receipt and validates
 * the selected one (BL match, time coverage, paid status, remaining quantity).
 * Gated by the require_ot_receipt company setting; authorized users may override.
 */
class GateInOvertimeValidator
{
    public function __construct(private OvertimeRuleResolver $resolver)
    {
    }

    /**
     * @return array{is_overtime:bool, required:bool, error:?string, receipt:?OtReceipt, override:bool, unconfigured:bool}
     */
    public function evaluate(
        Carbon $gateInAt,
        ?string $blNumber,
        ?string $receiptNo,
        bool $canOverride = false,
        ?string $overrideReason = null
    ): array {
        $base = ['is_overtime' => false, 'required' => false, 'error' => null, 'receipt' => null, 'override' => false, 'unconfigured' => false];

        // Policy off → never required.
        if (! CompanySetting::current()->require_ot_receipt) {
            return $base;
        }

        $summary = $this->resolver->resolve($gateInAt);
        if (! $summary['is_overtime']) {
            return $base; // within normal working hours
        }

        $base['is_overtime']  = true;
        $base['required']     = true;
        $base['unconfigured'] = (bool) $summary['unconfigured'];

        // Authorized override short-circuits (reason mandatory).
        if ($canOverride && filled($overrideReason)) {
            return array_merge($base, ['override' => true]);
        }

        if ($summary['unconfigured']) {
            return array_merge($base, ['error' =>
                'This gate-in time falls into an unconfigured OT period. Supervisor approval or a custom tariff rule is required.']);
        }

        if (blank($receiptNo)) {
            return array_merge($base, ['error' =>
                'Overtime receipt is required for this gate-in time. Please generate/select a valid OT receipt.']);
        }

        $receipt = OtReceipt::where('receipt_no', $receiptNo)->first();
        if (! $receipt) {
            return array_merge($base, ['error' => 'The overtime receipt number was not found.']);
        }
        if (filled($blNumber) && strcasecmp((string) $receipt->bl_number, (string) $blNumber) !== 0) {
            return array_merge($base, ['error' => 'Selected OT receipt does not belong to this BL.']);
        }
        if (! in_array($receipt->status, ['paid', 'partially_used'], true)) {
            return array_merge($base, ['error' => 'Selected OT receipt is not paid/active.']);
        }
        if (! ($gateInAt->gte($receipt->valid_from) && $gateInAt->lte($receipt->valid_to))) {
            return array_merge($base, ['error' =>
                'Selected OT receipt is outside its valid time range. Please generate an extension/new receipt.']);
        }
        if ($receipt->remainingCount() <= 0) {
            return array_merge($base, ['error' => 'Selected OT receipt is already fully utilized.']);
        }

        return array_merge($base, ['receipt' => $receipt]);
    }
}
