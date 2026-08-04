@php
    /**
     * Overtime receipt printout. Follows the shared document standard:
     * partials.pdf-letterhead for the header (logo, company block, bordered title,
     * verify QR) and partials.pdf-footer for the running footer (generated stamp,
     * software-provider copyright, Page X of Y) — both repeated on every page.
     */
    $company = $company ?? \App\Models\CompanySetting::current();
    $accent  = '#0d6efd';

    $cur   = strtoupper($receipt->currency ?: 'LKR');
    $words = \App\Helpers\NumberToWords::convert((float) $receipt->total_amount, $cur);

    // An unpaid receipt must never read as proof of payment, so it is stamped.
    $watermark = match ($receipt->status) {
        'generated' => 'UNPAID',
        'cancelled' => 'CANCELLED',
        'void'      => 'VOID',
        default     => null,
    };

    $statusLabel = strtoupper(str_replace('_', ' ', $receipt->status));
    $isPaid      = in_array($receipt->status, ['paid', 'partially_used', 'fully_used'], true);
    $remaining   = max(0, (int) $receipt->expected_container_count - (int) $receipt->used_container_count);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Overtime Receipt {{ $receipt->receipt_no }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Courier New', Courier, monospace; font-size: 10px; color: #222; background: #fff; }

    /* Header/footer are position:fixed so dompdf repeats them on every page;
       the empty thead/tfoot spacers reserve their height in the flow. */
    @page { margin: 0; }
    .pdf-fixed-header { position: fixed; top: 0; left: 0; right: 0; padding: 14px 24px 0; background: #fff; }
    .doc-layout     { width: 100%; border-collapse: collapse; }
    .doc-body-cell  { padding: 0 24px; border: none; vertical-align: top; }
    .doc-spacer-head { height: 132px; }
    .doc-spacer-foot { height: 44px; }

    .watermark {
        position: fixed; top: 40%; left: 0; right: 0; text-align: center;
        font-size: 92px; color: rgba(220,53,69,0.10); font-weight: bold;
        transform: rotate(-22deg); letter-spacing: 6px;
    }

    table { width: 100%; border-collapse: collapse; }

    .info-box { border: 1px solid #dee2e6; border-radius: 5px; padding: 8px 10px; text-transform: uppercase; }
    .info-box h3 {
        font-size: 8px; text-transform: uppercase; letter-spacing: .04em;
        color: #666; margin-bottom: 6px; border-bottom: 1px solid #eee; padding-bottom: 3px;
    }
    .lbl { color: #666; width: 44%; vertical-align: top; padding: 1px 4px 1px 0; }
    .val { font-weight: bold; text-align: right; vertical-align: top; padding: 1px 0; }

    /* Service window — the substance of an OT receipt: what was bought, and until when. */
    .window-box {
        border: 1px solid #cdd6e4; background: #f5f8ff; border-radius: 5px;
        padding: 8px 10px; margin-top: 8px; text-transform: uppercase;
    }
    .window-box h3 {
        font-size: 8px; letter-spacing: .04em; color: #666;
        margin-bottom: 6px; border-bottom: 1px solid #dbe4f5; padding-bottom: 3px;
    }
    .win-period { font-size: 13px; font-weight: bold; color: #0b3d91; letter-spacing: .5px; }
    .win-rule   { font-size: 9px; color: #444; margin-top: 2px; }

    .amount-box {
        border: 1px solid #cdd6e4; background: #f3f7ff; border-radius: 5px;
        padding: 10px 14px; margin-top: 10px;
    }
    .amount-cap   { font-size: 8px; color: #666; text-transform: uppercase; letter-spacing: .06em; }
    .amount-big   { font-size: 22px; font-weight: bold; color: #0b3d91; }
    .amount-words { font-style: italic; color: #444; font-size: 9px; margin-top: 3px; }

    .badge-status {
        display: inline-block; padding: 2px 8px; border-radius: 10px;
        font-size: 8px; font-weight: bold; text-transform: uppercase;
        border: 1px solid #999;
    }
    .badge-paid   { background: #d1e7dd; color: #0a3622; border-color: #0a3622; }
    .badge-unpaid { background: #fff3cd; color: #664d03; border-color: #664d03; }

    .notes {
        margin-top: 10px; border: 1px solid #eee; border-left: 3px solid {{ $accent }};
        padding: 6px 9px; font-size: 8.5px; color: #444; line-height: 1.55;
    }
    .sign td   { vertical-align: bottom; border: none; padding-top: 40px; }
    .sigline   { border-top: 1px solid #888; }
    .siglabel  { padding-top: 3px; color: #555; font-size: 9px; text-align: center; text-transform: uppercase; }
</style>
</head>
<body>

@if($watermark)<div class="watermark">{{ $watermark }}</div>@endif

<div class="pdf-fixed-header">
    @include('partials.pdf-letterhead', [
        'title'     => 'OVERTIME RECEIPT',
        'accent'    => $accent,
        'company'   => $company,
        'verifyUrl' => \Illuminate\Support\Facades\URL::signedRoute(
            'documents.verify', ['type' => 'ot-receipt', 'id' => $receipt->id]
        ),
    ])
</div>
@include('partials.pdf-footer', ['company' => $company])

<table class="doc-layout">
<thead><tr><td class="doc-spacer-head"></td></tr></thead>
<tfoot><tr><td class="doc-spacer-foot"></td></tr></tfoot>
<tbody><tr><td class="doc-body-cell">

    {{-- ── Receipt & customer ─────────────────────────────────────────────── --}}
    <table><tr>
        <td style="width:50%; padding-right:6px; border:none; vertical-align:top;">
            <div class="info-box">
                <h3>Receipt Details</h3>
                <table>
                    <tr><td class="lbl">Receipt No</td><td class="val">{{ $receipt->receipt_no }}</td></tr>
                    <tr><td class="lbl">Issued</td><td class="val">{{ $receipt->created_at?->format('d M Y H:i') ?? '—' }}</td></tr>
                    <tr><td class="lbl">Operational Date</td><td class="val">{{ $receipt->operational_date?->format('d M Y') ?? '—' }}</td></tr>
                    <tr>
                        <td class="lbl">Status</td>
                        <td class="val">
                            <span class="badge-status {{ $isPaid ? 'badge-paid' : 'badge-unpaid' }}">{{ $statusLabel }}</span>
                        </td>
                    </tr>
                    @if($receipt->extensionOf)
                    <tr><td class="lbl">Extension Of</td><td class="val">{{ $receipt->extensionOf->receipt_no }}</td></tr>
                    @endif
                </table>
            </div>
        </td>
        <td style="width:50%; padding-left:6px; border:none; vertical-align:top;">
            <div class="info-box">
                <h3>Billed To</h3>
                <table>
                    <tr><td class="lbl">Customer</td><td class="val">{{ $receipt->customer->name ?? '—' }}</td></tr>
                    <tr><td class="lbl">BL Number</td><td class="val">{{ $receipt->bl_number }}</td></tr>
                    <tr><td class="lbl">Containers Paid</td><td class="val">{{ $receipt->expected_container_count }}</td></tr>
                    <tr><td class="lbl">Used / Remaining</td><td class="val">{{ $receipt->used_container_count }} / {{ $remaining }}</td></tr>
                </table>
            </div>
        </td>
    </tr></table>

    {{-- ── Service window ─────────────────────────────────────────────────── --}}
    <div class="window-box">
        <h3>Overtime Service Window</h3>
        <div class="win-period">
            {{ $receipt->valid_from?->format('d M Y H:i') ?? '—' }}
            &nbsp;&rarr;&nbsp;
            {{ $receipt->valid_to?->format('d M Y H:i') ?? '—' }}
        </div>
        <div class="win-rule">
            {{ $receipt->rule->display_name ?? '—' }}
            @if($receipt->rule?->rule_code) &middot; {{ $receipt->rule->rule_code }} @endif
            @if($receipt->rule?->version?->version_code) &middot; Tariff {{ $receipt->rule->version->version_code }} @endif
        </div>
    </div>

    {{-- ── Amount ─────────────────────────────────────────────────────────── --}}
    <div class="amount-box">
        <table><tr>
            <td style="border:none; padding:0; vertical-align:top;">
                <div class="amount-cap">Total Overtime Charge</div>
                <div class="amount-big">{{ $cur }} {{ number_format($receipt->total_amount, 2) }}</div>
                <div class="amount-words">{{ $words }}</div>
            </td>
            <td style="border:none; padding:0; width:42%; vertical-align:top; text-align:right;">
                <table>
                    <tr><td class="lbl" style="text-align:right;">Payment Method</td>
                        <td class="val">{{ $receipt->payment_method ? strtoupper($receipt->payment_method) : '—' }}</td></tr>
                    <tr><td class="lbl" style="text-align:right;">Received To</td>
                        <td class="val">{{ $receipt->bankAccount->bank_name ?? ($receipt->payment_method === 'cash' ? 'CASH' : '—') }}</td></tr>
                    <tr><td class="lbl" style="text-align:right;">Paid On</td>
                        <td class="val">{{ $receipt->paid_at?->format('d M Y H:i') ?? '—' }}</td></tr>
                </table>
            </td>
        </tr></table>
    </div>

    @if($receipt->remarks)
    <div class="notes"><strong>Remarks:</strong> {{ $receipt->remarks }}</div>
    @endif

    {{-- ── Conditions ─────────────────────────────────────────────────────── --}}
    <div class="notes">
        This receipt is valid <strong>only</strong> for the BL number and service window shown above, and for the
        number of containers paid for. Movements outside the window require an extension or a new receipt.
        The overtime service charge is <strong>exempt from VAT / SSCL</strong>.
        @unless($isPaid)
        <br><strong>This receipt is not yet paid and is not valid for gate use.</strong>
        @endunless
    </div>

    {{-- ── Signatures ─────────────────────────────────────────────────────── --}}
    <table class="sign"><tr>
        <td style="width:45%;">
            <div class="sigline"></div>
            <div class="siglabel">Issued by{{ $receipt->createdBy ? ' — ' . $receipt->createdBy->name : '' }}</div>
        </td>
        <td style="width:10%;">&nbsp;</td>
        <td style="width:45%;">
            <div class="sigline"></div>
            <div class="siglabel">Received by (Customer / Agent)</div>
        </td>
    </tr></table>

</td></tr></tbody>
</table>

</body>
</html>
