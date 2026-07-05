@php
    $company = \App\Models\CompanySetting::current();
    $base    = strtoupper($company->default_currency_code ?: 'LKR');
    $cur     = strtoupper($cn->currency ?: $base);
    $rate    = (float) ($cn->exchange_rate ?: 1);
    $baseAmt = (float) ($cn->base_amount ?? ($cn->total_amount * $rate));
    $isForeign = $cur !== $base;
    $words   = \App\Helpers\NumberToWords::convert((float) $cn->total_amount, $cur);
    $half    = ($size ?? 'a4') === 'half';
    $title   = $title ?? 'CREDIT NOTE';
    $partyLabel = $partyLabel ?? 'Party';
    $partyName  = $partyName ?? '—';
    $taxLabel   = $taxLabel ?? 'Tax';

    $watermark = match ($cn->status) {
        'approved'  => null,
        'cancelled' => 'CANCELLED',
        default     => 'DRAFT',
    };

    $logoSrc = null;
    if (!empty($company->logo_path)) {
        try {
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            if ($disk->exists($company->logo_path)) {
                $ext  = strtolower(pathinfo($company->logo_path, PATHINFO_EXTENSION));
                $mime = $ext === 'jpg' ? 'image/jpeg' : 'image/' . ($ext ?: 'png');
                $logoSrc = 'data:' . $mime . ';base64,' . base64_encode($disk->get($company->logo_path));
            }
        } catch (\Throwable) { $logoSrc = null; }
    }

    $qr = \App\Support\Qr::svgDataUri($verifyUrl ?? null, ($half ?? false) ? 70 : 100);
@endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>{{ $title }} {{ $cn->credit_note_no }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Courier New', Courier, monospace; color: #222; font-size: {{ $half ? '9px' : '12px' }}; }
    .wrap { padding: {{ $half ? '6px 4px 42px' : '10px 6px 46px' }}; position: relative; }
    .watermark { position: fixed; top: 42%; left: 0; right: 0; text-align: center;
        font-size: {{ $half ? '50px' : '100px' }}; color: rgba(220,53,69,0.10); font-weight: bold;
        transform: rotate(-22deg); letter-spacing: 5px; z-index: 0; }
    table { width: 100%; border-collapse: collapse; }
    .hdr td { vertical-align: top; text-transform: uppercase; }
    .co-name { font-size: {{ $half ? '13px' : '18px' }}; font-weight: bold; color: #0b6e4f; }
    .co-sub { color: #666; font-size: {{ $half ? '8px' : '10px' }}; line-height: 1.5; margin-top: 2px; }
    .doc-title { text-align: right; }
    .doc-title h1 { color: #0b6e4f; font-size: {{ $half ? '15px' : '22px' }}; letter-spacing: 1px; }
    .doc-no { font-weight: bold; font-size: {{ $half ? '10px' : '13px' }}; }
    .rule { border-bottom: 2px solid #0b6e4f; margin: {{ $half ? '5px 0' : '10px 0' }}; }
    .meta td { padding: {{ $half ? '1px 4px' : '3px 6px' }}; font-size: {{ $half ? '8.5px' : '11px' }}; text-transform: uppercase; }
    .meta .lbl { color: #666; width: 18%; }
    .meta .val { font-weight: bold; width: 32%; }
    .amount-box { border: 1px solid #cde4d8; background: #f1f9f5; border-radius: 5px;
        padding: {{ $half ? '5px 8px' : '10px 14px' }}; margin: {{ $half ? '6px 0' : '12px 0' }}; }
    .amount-big { font-size: {{ $half ? '15px' : '22px' }}; font-weight: bold; color: #0b6e4f; }
    .amount-words { font-style: italic; color: #444; font-size: {{ $half ? '8px' : '11px' }}; margin-top: 2px; }
    .fx { color: #555; font-size: {{ $half ? '8px' : '10px' }}; margin-top: 3px; }
    .lines th { background: #0b6e4f; color: #fff; text-align: left; padding: {{ $half ? '2px 5px' : '5px 8px' }}; font-size: {{ $half ? '8px' : '10px' }}; }
    .lines td { padding: {{ $half ? '2px 5px' : '5px 8px' }}; border-bottom: 1px solid #eee; font-size: {{ $half ? '8px' : '11px' }}; }
    .lines td.r, .lines th.r { text-align: right; }
    .tot td { padding: {{ $half ? '2px 5px' : '4px 8px' }}; font-size: {{ $half ? '8.5px' : '11px' }}; }
    .tot .g { font-weight: bold; border-top: 1px solid #999; }
    .narr { margin-top: {{ $half ? '4px' : '8px' }}; font-size: {{ $half ? '8px' : '11px' }}; }
    .sign { margin-top: {{ $half ? '20px' : '48px' }}; }
    .sign td { vertical-align: bottom; }
    .sigline { border-top: 1px solid #888; }
    .siglabel { padding-top: 3px; color: #555; font-size: {{ $half ? '8px' : '11px' }}; text-align: center; }
    .ftr { margin-top: {{ $half ? '6px' : '16px' }}; color: #999; font-size: {{ $half ? '7px' : '9px' }}; }
    .muted { color: #777; }
</style>
</head>
<body>
@if($watermark)<div class="watermark">{{ $watermark }}</div>@endif
<div class="wrap">
    <table class="hdr"><tr>
        <td>
            <table><tr>
                @if($logoSrc)<td style="vertical-align:middle;width:1%;white-space:nowrap;padding-right:{{ $half ? '8px' : '12px' }};"><img src="{{ $logoSrc }}" style="max-height:{{ $half ? '34px' : '54px' }};max-width:{{ $half ? '110px' : '170px' }};display:block;"></td>@endif
                <td style="vertical-align:middle;">
                    <div class="co-name">{{ $company->company_name }}</div>
                    <div class="co-sub">{{ $company->address }}{{ $company->city ? ', '.$company->city : '' }}<br>
                        @if($company->telephone)Tel: {{ $company->telephone }} @endif @if($company->email) · {{ $company->email }}@endif<br>
                        @if($company->vat_number)VAT: {{ $company->vat_number }}@endif @if($company->tin_number) · TIN: {{ $company->tin_number }}@endif</div>
                </td>
            </tr></table>
        </td>
        <td class="doc-title">
            <h1>{{ $title }}</h1>
            <div class="doc-no">{{ $cn->credit_note_no }}</div>
            <div class="muted">{{ \Carbon\Carbon::parse($cn->credit_date)->format('d M Y') }}</div>
            @if($qr)
            <div style="margin-top:{{ ($half ?? false) ? '3px' : '5px' }};">
                <img src="{{ $qr }}" alt="Verify" style="width:{{ ($half ?? false) ? '46px' : '62px' }}; height:{{ ($half ?? false) ? '46px' : '62px' }}; display:inline-block;">
                <div class="muted" style="font-size:{{ ($half ?? false) ? '6px' : '7px' }};">Scan to verify</div>
            </div>
            @endif
        </td>
    </tr></table>
    <div class="rule"></div>

    <table class="meta"><tr>
        <td class="lbl">{{ $partyLabel }}</td><td class="val">{{ $partyName }}</td>
        <td class="lbl">Currency</td><td class="val">{{ $cur }} @ {{ rtrim(rtrim(number_format($rate,6,'.',''),'0'),'.') }}</td>
    </tr></table>

    <table class="lines" style="margin-top:{{ $half ? '5px' : '10px' }}">
        <thead><tr><th>Description</th><th class="r">Amount ({{ $cur }})</th></tr></thead>
        <tbody>
            @foreach($cn->lines as $line)
            <tr><td>{{ $line->description }}</td><td class="r">{{ number_format($line->amount, 2) }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <table class="tot"><tr><td></td>
        <td style="width:40%">
            <table style="width:100%">
                <tr><td class="muted">Subtotal</td><td class="r" style="text-align:right">{{ number_format($cn->subtotal, 2) }}</td></tr>
                @if((float) ($cn->sscl_amount ?? 0) > 0)
                <tr><td class="muted">SSCL</td><td class="r" style="text-align:right">{{ number_format($cn->sscl_amount, 2) }}</td></tr>
                @endif
                <tr><td class="muted">{{ $taxLabel }}</td><td class="r" style="text-align:right">{{ number_format($cn->tax_amount, 2) }}</td></tr>
                <tr class="g"><td>Total ({{ $cur }})</td><td style="text-align:right">{{ number_format($cn->total_amount, 2) }}</td></tr>
            </table>
        </td></tr></table>

    <div class="amount-box">
        <div class="amount-big">{{ $cur }} {{ number_format($cn->total_amount, 2) }}</div>
        <div class="amount-words">{{ $words }}</div>
        @if($isForeign)<div class="fx">Equivalent: {{ $base }} {{ number_format($baseAmt, 2) }}</div>@endif
    </div>

    @if($cn->reason)<div class="narr"><span class="muted">Reason:</span> {{ $cn->reason }}</div>@endif

    @if($showSignature ?? true)
    <table class="sign"><tr>
        <td style="width:45%;"><div class="sigline"></div><div class="siglabel">Prepared by{{ $cn->createdBy ? ' — '.$cn->createdBy->name : '' }}</div></td>
        <td style="width:10%;">&nbsp;</td>
        <td style="width:45%;"><div class="sigline"></div><div class="siglabel">Authorized Signatory</div></td>
    </tr></table>
    @else
    <div style="margin-top:{{ $half ? '12px' : '28px' }};text-align:center;color:#777;font-style:italic;font-size:{{ $half ? '8px' : '10px' }};">This is a computer-generated credit note and does not require a signature.</div>
    @endif

</div>
@include('partials.pdf-footer', ['company' => $company])
</body>
</html>
