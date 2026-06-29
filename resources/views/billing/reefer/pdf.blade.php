@php
    $company = $companySetting ?? \App\Models\CompanySetting::current();
    $base    = strtoupper($company->default_currency_code ?: 'LKR');
    $cur     = strtoupper($reeferInvoice->invoice_currency ?: $base);
    $rate    = (float) ($reeferInvoice->exchange_rate ?: 1);
    $isForeign = $cur !== $base;

    $statusClass = [
        'draft'     => 'st-draft',
        'issued'    => 'st-issued',
        'paid'      => 'st-paid',
        'cancelled' => 'st-cancelled',
    ][$reeferInvoice->status] ?? 'st-draft';

    $watermark = match ($reeferInvoice->status) {
        'cancelled' => 'CANCELLED',
        'draft'     => 'DRAFT',
        default     => null,
    };

    // Embed the company logo as a base64 data URI (dompdf can't fetch URLs with
    // remote access disabled). Logos live on the 'public' disk.
    $logoSrc = null;
    if (!empty($company->logo_path)) {
        try {
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            if ($disk->exists($company->logo_path)) {
                $ext  = strtolower(pathinfo($company->logo_path, PATHINFO_EXTENSION));
                $mime = $ext === 'jpg' ? 'image/jpeg' : 'image/' . ($ext ?: 'png');
                $logoSrc = 'data:' . $mime . ';base64,' . base64_encode($disk->get($company->logo_path));
            }
        } catch (\Throwable) {
            $logoSrc = null;
        }
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Reefer Electricity Invoice {{ $reeferInvoice->invoice_no }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, Arial, sans-serif; color: #222; font-size: 11px; }
    /* Reserve space for the running header/footer on every page. */
    @page { margin: 170px 22px 54px 22px; }
    .pdf-running-header { position: fixed; top: -170px; left: 0; right: 0; height: 160px; padding: 16px 22px 0; background: #fff; }
    .wrap { padding: 0; position: relative; }
    .watermark { position: fixed; top: 42%; left: 0; right: 0; text-align: center;
        font-size: 110px; color: rgba(33,150,243,0.08); font-weight: bold;
        transform: rotate(-22deg); letter-spacing: 6px; z-index: 0; }

    table { width: 100%; border-collapse: collapse; }
    .hdr td { vertical-align: middle; }
    .co-name { font-size: 18px; font-weight: bold; color: #1a56db; }
    .co-sub  { color: #666; font-size: 10px; line-height: 1.5; margin-top: 2px; }
    .rule { border-bottom: 2px solid #1a56db; margin: 10px 0; }

    /* Centered title band below the header */
    .title-band { text-align: center; margin: 4px 0 10px; }
    .title-band h1 { color: #1a56db; font-size: 22px; letter-spacing: 1px; }
    .title-band .doc-no { font-weight: bold; font-size: 13px; color: #1a1a2e; margin-top: 2px; }
    .status-badge { display:inline-block; padding:2px 12px; border-radius:4px; font-size:9px; font-weight:bold; text-transform:uppercase; margin-top:4px; }
    .st-draft  { background:#f1f5f9; color:#64748b; }
    .st-issued { background:#e0f2fe; color:#0284c7; }
    .st-paid   { background:#dcfce7; color:#16a34a; }
    .st-cancelled { background:#fee2e2; color:#dc2626; }

    .meta td { padding: 2px 0; font-size: 10.5px; vertical-align: top; }
    .meta .lbl { color: #666; }
    .meta .val { font-weight: bold; }
    .billed strong { font-size: 11px; }

    .items th { background: #f0f4f8; padding: 5px 6px; text-align: left; font-size: 8.5px;
        text-transform: uppercase; letter-spacing: .3px; border-bottom: 1px solid #d8dfe7; }
    .items td { padding: 5px 6px; border-bottom: 1px solid #eef1f4; font-size: 9.5px; vertical-align: top; }
    .items td.r, .items th.r { text-align: right; }
    .mono { font-family: 'DejaVu Sans Mono', 'Courier New', monospace; }
    .badge-hourly { background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:3px; font-size:8.5px; }
    .badge-daily  { background:#ede9fe; color:#7c3aed; padding:1px 5px; border-radius:3px; font-size:8.5px; }

    .tot td { padding: 3px 0; font-size: 11px; }
    .tot .lbl { color: #555; }
    .tot .amt { text-align: right; }
    .tot .grand td { border-top: 2px solid #1a56db; padding-top: 5px; font-weight: bold; font-size: 12px; color: #1a56db; }
    .tot .valrow td { color: #777; font-size: 9.5px; }

    .ftr { margin-top: 18px; color: #999; font-size: 9px; border-top: 1px solid #e0e0e0; padding-top: 8px; }
    .muted { color: #777; }
</style>
</head>
<body>
@if($watermark)<div class="watermark">{{ $watermark }}</div>@endif
@php
    $verifyUrl = \Illuminate\Support\Facades\URL::signedRoute('documents.verify', ['type' => 'reefer', 'id' => $reeferInvoice->id]);
    $qr = \App\Support\Qr::svgDataUri($verifyUrl, 120);
@endphp

{{-- Running header (repeats on every page) --}}
<div class="pdf-running-header">
    {{-- Letterhead: logo + company details --}}
    <table class="hdr"><tr>
        @if($logoSrc)
        <td style="width:1%; white-space:nowrap; padding-right:12px;">
            <img src="{{ $logoSrc }}" alt="{{ $company->company_name }}" style="max-height:54px; max-width:170px; display:block;">
        </td>
        @endif
        <td>
            <div class="co-name">{{ $company->company_name }}</div>
            <div class="co-sub">
                {{ $company->address }}{{ $company->city ? ', '.$company->city : '' }}<br>
                @if($company->telephone)Tel: {{ $company->telephone }} @endif @if($company->email)· {{ $company->email }}@endif<br>
                @if($company->vat_number)VAT: {{ $company->vat_number }}@endif @if($company->tin_number) · TIN: {{ $company->tin_number }}@endif
            </div>
        </td>
        @if($qr)
        <td style="width:1%; white-space:nowrap; vertical-align:middle; text-align:right; padding-left:12px;">
            <img src="{{ $qr }}" alt="Verify" style="width:78px; height:78px; display:block; margin-left:auto;">
            <div style="font-size:7px; color:#888; text-align:center; margin-top:1px;">Scan to verify</div>
        </td>
        @endif
    </tr></table>
    {{-- Bordered, centred document title --}}
    <div style="border:2px solid #1a56db; border-radius:5px; padding:5px 14px; text-align:center; margin:10px 0;">
        <span style="color:#1a56db; font-size:15px; font-weight:bold; letter-spacing:1px;">REEFER ELECTRICITY INVOICE</span>
    </div>
</div>

{{-- Running footer (repeats on every page) --}}
@include('partials.pdf-footer', ['company' => $company])

<div class="wrap">

    {{-- Billed-To + meta (two real columns — no floats) --}}
    <table style="margin-bottom:14px;"><tr>
        <td style="width:52%; vertical-align:top;" class="billed">
            <strong>Billed To</strong><br>
            {{ $reeferInvoice->customer?->name ?? '—' }}<br>
            @if($reeferInvoice->billingParty && $reeferInvoice->billing_party_id !== $reeferInvoice->customer_id)
                <span class="muted">Billing party:</span> {{ $reeferInvoice->billingParty->name }}<br>
            @endif
            @if($reeferInvoice->customer?->address){{ $reeferInvoice->customer->address }}{{ $reeferInvoice->customer->city ? ', '.$reeferInvoice->customer->city : '' }}<br>@endif
            @if($reeferInvoice->customer?->registration_no)Reg No: {{ $reeferInvoice->customer->registration_no }}<br>@endif
            @if($reeferInvoice->customer?->tin_number)TIN: {{ $reeferInvoice->customer->tin_number }}@endif
        </td>
        <td style="width:48%; vertical-align:top;">
            <table class="meta">
                <tr><td class="lbl">Invoice No</td><td class="val mono">{{ $reeferInvoice->invoice_no }}</td></tr>
                <tr><td class="lbl">Status</td><td class="val">{{ ucfirst($reeferInvoice->status) }}</td></tr>
                <tr><td class="lbl">Invoice Type</td><td class="val">{{ ucwords(str_replace('_', ' ', $reeferInvoice->invoice_type ?? 'invoice')) }}</td></tr>
                <tr><td class="lbl">Bill Type</td><td class="val">{{ $reeferInvoice->service_type === 'pti' ? 'Short-Term PTI' : 'Long-Term Electricity' }}</td></tr>
                <tr><td class="lbl">Invoice Date</td><td class="val">{{ $reeferInvoice->invoice_date?->format('d M Y') }}</td></tr>
                @if($reeferInvoice->due_date)
                <tr><td class="lbl">Payment Due</td><td class="val">{{ $reeferInvoice->due_date?->format('d M Y') }}</td></tr>
                @endif
                <tr><td class="lbl">Billing Period</td><td class="val">{{ $reeferInvoice->billing_period_from?->format('d M Y') }} – {{ $reeferInvoice->billing_period_to?->format('d M Y') }}</td></tr>
                <tr><td class="lbl">Currency</td><td class="val">{{ $cur }}@if($isForeign) <span class="muted" style="font-weight:normal;">(1 {{ $cur }} = {{ rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.') }} {{ $base }})</span>@endif</td></tr>
            </table>
        </td>
    </tr></table>

    {{-- Line items --}}
    <table class="items">
        <thead>
            <tr>
                <th>Container</th>
                <th>Plug-In</th>
                <th>Plug-Out</th>
                <th>Mode</th>
                <th class="r">Chargeable</th>
                <th class="r">Rate</th>
                <th class="r">Subtotal</th>
                <th class="r">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reeferInvoice->lines as $line)
            <tr>
                <td class="mono" style="font-weight:bold;">{{ $line->container_no }}</td>
                <td>{{ $line->plug_in_at?->format('d M y H:i') ?? '—' }}</td>
                <td>{{ $line->plug_out_at?->format('d M y H:i') ?? '—' }}</td>
                <td><span class="badge-{{ $line->billing_mode }}">{{ ucfirst($line->billing_mode) }}</span></td>
                <td class="r">
                    @if($line->billing_mode === 'hourly') {{ rtrim(rtrim(number_format($line->chargeable_hours, 2), '0'), '.') }}h
                    @else {{ (int) $line->chargeable_days }}d @endif
                    @if(($line->billing_mode === 'hourly' && $line->free_hours > 0) || ($line->billing_mode === 'daily' && $line->free_days > 0))
                        <div class="muted" style="font-size:8px;">free {{ $line->billing_mode === 'hourly' ? rtrim(rtrim(number_format($line->free_hours,2),'0'),'.').'h' : (int)$line->free_days.'d' }}</div>
                    @endif
                </td>
                <td class="r mono">{{ $line->currency }} {{ number_format($line->rate, 2) }}</td>
                <td class="r mono">{{ number_format($line->subtotal, 2) }}</td>
                <td class="r mono" style="font-weight:bold;">{{ number_format($line->line_total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals (two real columns — no floats) --}}
    <table style="margin-top:10px;"><tr>
        <td style="width:55%;">&nbsp;</td>
        <td style="width:45%;">
            <table class="tot">
                <tr><td class="lbl">Subtotal</td><td class="amt mono">{{ $cur }} {{ number_format($reeferInvoice->subtotal, 2) }}</td></tr>
                @if($reeferInvoice->sscl_amount > 0)
                <tr><td class="lbl">SSCL ({{ rtrim(rtrim(number_format($reeferInvoice->sscl_percentage,2),'0'),'.') }}%)</td><td class="amt mono">{{ number_format($reeferInvoice->sscl_amount, 2) }}</td></tr>
                @endif
                @if($reeferInvoice->vat_amount > 0)
                <tr><td class="lbl">VAT ({{ rtrim(rtrim(number_format($reeferInvoice->vat_percentage,2),'0'),'.') }}%)</td><td class="amt mono">{{ number_format($reeferInvoice->vat_amount, 2) }}</td></tr>
                @endif
                <tr class="grand"><td>TOTAL DUE</td><td class="amt mono">{{ $cur }} {{ number_format($reeferInvoice->total_amount, 2) }}</td></tr>
                @if($isForeign)
                <tr class="valrow"><td>Total Value</td><td class="amt mono">{{ $base }} {{ number_format($reeferInvoice->total_value, 2) }}</td></tr>
                @endif
            </table>
        </td>
    </tr></table>

    @if($reeferInvoice->notes)
    <div style="clear:both; margin-top:14px; font-size:10px;"><span class="muted">Notes:</span> {{ $reeferInvoice->notes }}</div>
    @endif

</div>
</body>
</html>
