@php
    $company = \App\Models\CompanySetting::current();
    $base    = strtoupper($company->default_currency_code ?: 'LKR');
    $cur     = strtoupper($receipt->currency ?: $base);
    $rate    = (float) ($receipt->exchange_rate ?: 1);
    $baseAmt = (float) ($receipt->base_amount ?? ($receipt->amount * $rate));
    $isForeign = $cur !== $base;
    $words   = \App\Helpers\NumberToWords::convert((float) $receipt->amount, $cur);

    $ar = app(\App\Services\Finance\ArAllocationService::class);
    $allocRows = $receipt->allocations->map(function ($a) use ($ar) {
        try { $no = $ar->resolveInvoice($a->invoice_type, $a->invoice_id)->invoice_no; }
        catch (\Throwable) { $no = '#' . $a->invoice_id; }
        return ['no' => $no, 'type' => ucwords(str_replace('-', ' ', $a->invoice_type)), 'amount' => (float) $a->allocated_amount];
    });

    $watermark = match ($receipt->status) {
        'confirmed' => null,
        'voided'    => 'VOIDED',
        default     => 'DRAFT',
    };
    $half = ($size ?? 'a4') === 'half';

    // Embed the company logo as a base64 data URI (dompdf can't fetch URLs with
    // remote access disabled). Logos are stored on the 'public' disk.
    $logoSrc = null;
    if (!empty($company->logo_path)) {
        try {
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            if ($disk->exists($company->logo_path)) {
                $ext     = strtolower(pathinfo($company->logo_path, PATHINFO_EXTENSION));
                $mime    = $ext === 'jpg' ? 'image/jpeg' : 'image/' . ($ext ?: 'png');
                $logoSrc = 'data:' . $mime . ';base64,' . base64_encode($disk->get($company->logo_path));
            }
        } catch (\Throwable) {
            $logoSrc = null;
        }
    }

    $verifyUrl = \Illuminate\Support\Facades\URL::signedRoute('documents.verify', ['type' => 'receipt', 'id' => $receipt->id]);
    $qr = \App\Support\Qr::svgDataUri($verifyUrl, $half ? 70 : 100);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt {{ $receipt->receipt_no }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, Helvetica, sans-serif; color: #222; font-size: {{ $half ? '9px' : '12px' }}; }
    .wrap { padding: {{ $half ? '6px 4px' : '10px 6px' }}; position: relative; }
    .watermark { position: fixed; top: 42%; left: 0; right: 0; text-align: center;
        font-size: {{ $half ? '56px' : '110px' }}; color: rgba(220,53,69,0.10); font-weight: bold;
        transform: rotate(-22deg); letter-spacing: 6px; z-index: 0; }
    table { width: 100%; border-collapse: collapse; }
    .hdr td { vertical-align: top; }
    .co-name { font-size: {{ $half ? '13px' : '18px' }}; font-weight: bold; color: #1a56db; }
    .co-sub { color: #666; font-size: {{ $half ? '8px' : '10px' }}; line-height: 1.5; margin-top: 2px; }
    .doc-title { text-align: right; }
    .doc-title h1 { color: #1a56db; font-size: {{ $half ? '16px' : '24px' }}; letter-spacing: 1px; }
    .doc-no { font-weight: bold; font-size: {{ $half ? '10px' : '13px' }}; }
    .rule { border-bottom: 2px solid #1a56db; margin: {{ $half ? '5px 0' : '10px 0' }}; }
    .meta td { padding: {{ $half ? '1px 4px' : '3px 6px' }}; font-size: {{ $half ? '8.5px' : '11px' }}; }
    .meta .lbl { color: #666; width: 18%; }
    .meta .val { font-weight: bold; width: 32%; }
    .amount-box { border: 1px solid #cdd6e4; background: #f3f7ff; border-radius: 5px;
        padding: {{ $half ? '5px 8px' : '10px 14px' }}; margin: {{ $half ? '6px 0' : '12px 0' }}; }
    .amount-big { font-size: {{ $half ? '15px' : '22px' }}; font-weight: bold; color: #0b3d91; }
    .amount-words { font-style: italic; color: #444; font-size: {{ $half ? '8px' : '11px' }}; margin-top: 2px; }
    .fx { color: #555; font-size: {{ $half ? '8px' : '10px' }}; margin-top: 3px; }
    .alloc th { background: #1a56db; color: #fff; text-align: left; padding: {{ $half ? '2px 5px' : '5px 8px' }}; font-size: {{ $half ? '8px' : '10px' }}; }
    .alloc td { padding: {{ $half ? '2px 5px' : '5px 8px' }}; border-bottom: 1px solid #eee; font-size: {{ $half ? '8px' : '11px' }}; }
    .alloc td.r, .alloc th.r { text-align: right; }
    .narr { margin-top: {{ $half ? '4px' : '8px' }}; font-size: {{ $half ? '8px' : '11px' }}; }
    .sign { margin-top: {{ $half ? '20px' : '48px' }}; }
    .sign td { vertical-align: bottom; }
    .sigline { border-top: 1px solid #888; }
    .siglabel { padding-top: 3px; color: #555; font-size: {{ $half ? '8px' : '11px' }}; text-align: center; }
    .ftr { margin-top: {{ $half ? '6px' : '16px' }}; color: #999; font-size: {{ $half ? '7px' : '9px' }}; text-align: center; }
    .muted { color: #777; }
</style>
</head>
<body>
@if($watermark)<div class="watermark">{{ $watermark }}</div>@endif
<div class="wrap">

    {{-- Letterhead --}}
    <table class="hdr"><tr>
        <td>
            <table><tr>
                @if($logoSrc)
                <td style="vertical-align:middle;width:1%;white-space:nowrap;padding-right:{{ $half ? '8px' : '12px' }};">
                    <img src="{{ $logoSrc }}" alt="{{ $company->company_name }}" style="max-height:{{ $half ? '34px' : '54px' }};max-width:{{ $half ? '110px' : '170px' }};display:block;">
                </td>
                @endif
                <td style="vertical-align:middle;">
                    <div class="co-name">{{ $company->company_name }}</div>
                    <div class="co-sub">
                        {{ $company->address }}{{ $company->city ? ', '.$company->city : '' }}<br>
                        @if($company->telephone)Tel: {{ $company->telephone }} @endif @if($company->email) · {{ $company->email }}@endif<br>
                        @if($company->vat_number)VAT: {{ $company->vat_number }}@endif @if($company->tin_number) · TIN: {{ $company->tin_number }}@endif
                    </div>
                </td>
            </tr></table>
        </td>
        <td class="doc-title">
            <h1>RECEIPT</h1>
            <div class="doc-no">{{ $receipt->receipt_no }}</div>
            <div class="muted">{{ \Carbon\Carbon::parse($receipt->receipt_date)->format('d M Y') }}</div>
            @if($qr)
            <div style="margin-top:{{ $half ? '3px' : '5px' }};">
                <img src="{{ $qr }}" alt="Verify" style="width:{{ $half ? '46px' : '62px' }}; height:{{ $half ? '46px' : '62px' }}; display:inline-block;">
                <div class="muted" style="font-size:{{ $half ? '6px' : '7px' }};">Scan to verify</div>
            </div>
            @endif
        </td>
    </tr></table>
    <div class="rule"></div>

    {{-- Meta --}}
    <table class="meta">
        <tr>
            <td class="lbl">Received From</td><td class="val">{{ $receipt->customer->name ?? '—' }}</td>
            <td class="lbl">Payment Method</td><td class="val">{{ \App\Models\Receipt::paymentMethodLabel($receipt->payment_method) }}</td>
        </tr>
        <tr>
            <td class="lbl">Bank Account</td><td class="val">{{ $receipt->bankAccount->bank_name ?? '—' }}{{ $receipt->bankAccount->account_number ? ' · '.$receipt->bankAccount->account_number : '' }}</td>
            <td class="lbl">{{ $receipt->payment_method === 'cheque' ? 'Cheque No' : 'Reference' }}</td>
            <td class="val">{{ $receipt->payment_method === 'cheque' ? ($receipt->cheque_no ?: '—') : ($receipt->reference_no ?: '—') }}</td>
        </tr>
    </table>

    {{-- Amount --}}
    <div class="amount-box">
        <div class="amount-big">{{ $cur }} {{ number_format($receipt->amount, 2) }}</div>
        <div class="amount-words">{{ $words }}</div>
        @if($isForeign)
        <div class="fx">Exchange rate: {{ rtrim(rtrim(number_format($rate, 6, '.', ''), '0'), '.') }} · Equivalent: {{ $base }} {{ number_format($baseAmt, 2) }}</div>
        @endif
    </div>

    {{-- Allocations --}}
    @if($allocRows->isNotEmpty())
    <table class="alloc">
        <thead><tr><th>Settled Invoice</th><th>Type</th><th class="r">Amount ({{ $cur }})</th></tr></thead>
        <tbody>
            @foreach($allocRows as $r)
            <tr><td>{{ $r['no'] }}</td><td>{{ $r['type'] }}</td><td class="r">{{ number_format($r['amount'], 2) }}</td></tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if($receipt->narration)
    <div class="narr"><span class="muted">Narration:</span> {{ $receipt->narration }}</div>
    @endif

    {{-- Signatures (printed copy only) --}}
    @if($showSignature ?? true)
    <table class="sign"><tr>
        <td style="width:45%;">
            <div class="sigline"></div>
            <div class="siglabel">Received / Prepared by{{ $receipt->createdBy ? ' — '.$receipt->createdBy->name : '' }}</div>
        </td>
        <td style="width:10%;">&nbsp;</td>
        <td style="width:45%;">
            <div class="sigline"></div>
            <div class="siglabel">Authorized Signatory</div>
        </td>
    </tr></table>
    @else
    <div style="margin-top:{{ $half ? '12px' : '28px' }};text-align:center;color:#777;font-style:italic;font-size:{{ $half ? '8px' : '10px' }};">
        This is a computer-generated receipt and does not require a signature.
    </div>
    @endif

    <table class="ftr"><tr>
        <td style="text-align:left;">Computer-generated receipt · {{ $company->company_name }}</td>
        <td style="text-align:right;">&copy; {{ date('Y') }} {{ $company->software_provider ?? 'CYM Software' }}</td>
    </tr></table>
</div>
</body>
</html>
