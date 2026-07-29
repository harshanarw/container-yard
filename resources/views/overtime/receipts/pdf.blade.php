<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { font-family: sans-serif; }
    body { font-size: 12px; color: #1f2937; margin: 0; padding: 18px; }
    .title { text-align: center; font-size: 16px; font-weight: bold; letter-spacing: 1px; margin-bottom: 2px; }
    .sub { text-align: center; font-size: 10px; color: #6b7280; margin-bottom: 10px; }
    .company { text-align: center; font-size: 13px; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; }
    .kv td { padding: 3px 4px; vertical-align: top; }
    .kv td.k { color: #6b7280; width: 38%; }
    .amount { border: 1.5px solid #111; border-radius: 6px; padding: 8px; text-align: center; margin: 10px 0; }
    .amount .n { font-size: 18px; font-weight: bold; }
    .foot { margin-top: 12px; border-top: 1px solid #d1d5db; padding-top: 6px; font-size: 9px; color: #6b7280; }
    .qr { text-align: center; margin-top: 8px; }
    .qr img { width: 92px; height: 92px; }
    .status { display:inline-block; padding:1px 8px; border-radius:10px; font-size:10px; border:1px solid #999; }
</style>
</head>
<body>
    <div class="company">{{ $company->company_name ?? 'Container Yard' }}</div>
    <div class="sub">{{ $company->address ?? '' }}</div>
    <div class="title">OVERTIME RECEIPT</div>
    <div class="sub">Custom / Depot Overtime Service Charge</div>

    <table class="kv">
        <tr><td class="k">Receipt No</td><td><strong>{{ $receipt->receipt_no }}</strong></td></tr>
        <tr><td class="k">Date</td><td>{{ $receipt->created_at?->format('d M Y H:i') }}</td></tr>
        <tr><td class="k">BL Number</td><td><strong>{{ $receipt->bl_number }}</strong></td></tr>
        <tr><td class="k">Customer</td><td>{{ $receipt->customer->name ?? '—' }}</td></tr>
        <tr><td class="k">Service Window</td><td>{{ $receipt->rule->display_name ?? '' }} ({{ $receipt->rule->rule_code ?? '' }})</td></tr>
        <tr><td class="k">Valid Period</td><td>{{ $receipt->valid_from?->format('d M Y H:i') }} &nbsp;→&nbsp; {{ $receipt->valid_to?->format('d M Y H:i') }}</td></tr>
        <tr><td class="k">Containers Paid</td><td>{{ $receipt->expected_container_count }}</td></tr>
        <tr><td class="k">Status</td><td><span class="status">{{ strtoupper(str_replace('_',' ',$receipt->status)) }}</span></td></tr>
        @if($receipt->paid_at)
        <tr><td class="k">Payment</td><td>{{ ucfirst($receipt->payment_method) }} · {{ $receipt->paid_at->format('d M Y H:i') }}</td></tr>
        @endif
    </table>

    <div class="amount">
        <div style="font-size:10px;color:#6b7280;">TOTAL OVERTIME CHARGE</div>
        <div class="n">{{ $receipt->currency }} {{ number_format($receipt->total_amount, 2) }}</div>
    </div>

    @if($qr)
    <div class="qr">
        <img src="{{ $qr }}" alt="verify">
        <div style="font-size:8px;color:#6b7280;">Scan to verify this receipt</div>
    </div>
    @endif

    <div class="foot">
        This is a system-generated overtime service receipt. Overtime charge is exempt from VAT/SSCL.
        Receipt valid only for the BL and validity period shown above.
    </div>
</body>
</html>
