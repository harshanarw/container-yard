<?php

namespace App\Services\Overtime;

use App\Models\OtReceipt;
use App\Models\OtTariffRule;
use App\Services\NumberSequenceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Quote / generate / confirm / extend / utilize overtime receipts. Amounts are
 * snapshotted from the tariff rule at generation time; the receipt is paid via
 * OtReceiptPostingService (DR bank/cash, CR OT income).
 */
class OtReceiptService
{
    public function __construct(private OvertimeRuleResolver $resolver)
    {
    }

    /** Compute rate + validity window for a tariff rule on an operational date. */
    public function quote(string $operationalDate, int $ruleId, int $containerCount = 1): array
    {
        $rule   = OtTariffRule::with('version')->findOrFail($ruleId);
        $opDate = Carbon::parse($operationalDate)->startOfDay();
        $win    = $this->resolver->buildValidityWindow($rule, $opDate);

        $amount = (float) $rule->rate_amount;
        if ($rule->charge_basis === 'per_container') {
            $amount = round($amount * max(1, $containerCount), 2);
        }

        return [
            'rule'             => $rule,
            'operational_date' => $opDate,
            'valid_from'       => $win['from'],
            'valid_to'         => $win['to'],
            'rate_amount'      => (float) $rule->rate_amount,
            'amount'           => $amount,
            'tax_amount'       => 0.0,          // OT charge is tax-exempt
            'total'            => $amount,
            'currency'         => $rule->currency,
        ];
    }

    /**
     * Create a draft-priced (GENERATED) receipt. Payload: bl_number, customer_id,
     * operational_date, tariff_rule_id, expected_container_count, remarks,
     * extension_of_receipt_id.
     */
    public function generate(array $payload): OtReceipt
    {
        $count = (int) ($payload['expected_container_count'] ?? 1);
        $quote = $this->quote($payload['operational_date'], (int) $payload['tariff_rule_id'], $count);

        return DB::transaction(function () use ($payload, $quote, $count) {
            return OtReceipt::create([
                'receipt_no'               => app(NumberSequenceService::class)->generate('ot_receipt'),
                'bl_number'                => $payload['bl_number'],
                'customer_id'              => $payload['customer_id'],
                'ot_tariff_version_id'     => $quote['rule']->ot_tariff_version_id,
                'ot_tariff_rule_id'        => $quote['rule']->id,
                'operational_date'         => $quote['operational_date']->toDateString(),
                'valid_from'               => $quote['valid_from'],
                'valid_to'                 => $quote['valid_to'],
                'receipt_amount'           => $quote['amount'],
                'tax_amount'               => 0,
                'total_amount'             => $quote['total'],
                'currency'                 => $quote['currency'],
                'expected_container_count' => max(1, $count),
                'used_container_count'     => 0,
                'status'                   => 'generated',
                'extension_of_receipt_id'  => $payload['extension_of_receipt_id'] ?? null,
                'billing_mode'             => $quote['rule']->billing_mode_on_extension,
                'remarks'                  => $payload['remarks'] ?? null,
                'created_by'               => auth()->id(),
            ]);
        });
    }

    /** Collect payment and post to the GL. */
    public function confirm(OtReceipt $receipt, ?int $bankAccountId, string $paymentMethod = 'cash', ?int $userId = null): OtReceipt
    {
        return app(OtReceiptPostingService::class)->confirm($receipt, $bankAccountId, $paymentMethod, $userId ?? auth()->id() ?? 1);
    }

    /**
     * Generate an extension receipt for the same BL on the next slab (full new
     * charge). Used when the initial window expires with containers still pending.
     */
    public function generateExtension(OtReceipt $original, int $newRuleId, int $expectedCount): OtReceipt
    {
        return $this->generate([
            'bl_number'                => $original->bl_number,
            'customer_id'              => $original->customer_id,
            'operational_date'         => $original->operational_date->toDateString(),
            'tariff_rule_id'           => $newRuleId,
            'expected_container_count' => $expectedCount,
            'extension_of_receipt_id'  => $original->id,
            'remarks'                  => 'Extension of ' . $original->receipt_no,
        ]);
    }

    /** Record one container gated in against the receipt, updating utilization. */
    public function markUtilized(OtReceipt $receipt): OtReceipt
    {
        return DB::transaction(function () use ($receipt) {
            $receipt = OtReceipt::lockForUpdate()->findOrFail($receipt->id);
            $receipt->increment('used_container_count');
            $receipt->refresh();

            if ($receipt->used_container_count >= $receipt->expected_container_count) {
                $receipt->update(['status' => 'fully_used']);
            } elseif (in_array($receipt->status, ['paid', 'generated'], true)) {
                $receipt->update(['status' => 'partially_used']);
            }

            return $receipt->refresh();
        });
    }

    public function cancel(OtReceipt $receipt, string $reason): OtReceipt
    {
        $receipt->update([
            'status'  => 'cancelled',
            'remarks' => trim(($receipt->remarks ? $receipt->remarks . ' | ' : '') . 'CANCELLED: ' . $reason),
        ]);

        return $receipt->refresh();
    }
}
