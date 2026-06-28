<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify {{ $docLabel }} — {{ $company->company_name ?? 'Document' }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; background: #eef2f7; color: #222; padding: 24px 12px; }
        .card { max-width: 460px; margin: 24px auto; background: #fff; border-radius: 12px;
                box-shadow: 0 6px 24px rgba(0,0,0,.08); overflow: hidden; }
        .top { padding: 20px 22px; text-align: center; border-bottom: 1px solid #eef2f7; }
        .co { font-size: 18px; font-weight: bold; color: #1a56db; }
        .co-sub { color: #777; font-size: 12px; margin-top: 2px; }
        .body { padding: 20px 22px; }
        .verdict { text-align: center; margin-bottom: 16px; }
        .ok  { color: #16a34a; }
        .bad { color: #dc2626; }
        .verdict .icon { font-size: 40px; line-height: 1; }
        .verdict .msg  { font-size: 15px; font-weight: bold; margin-top: 6px; }
        .verdict .sub  { color: #777; font-size: 12px; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td { padding: 8px 4px; border-bottom: 1px solid #f0f2f5; font-size: 13px; vertical-align: top; }
        td.lbl { color: #777; width: 42%; }
        td.val { font-weight: 600; text-align: right; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px;
                 font-weight: bold; text-transform: capitalize; background: #e0f2fe; color: #0369a1; }
        .ftr { text-align: center; color: #9aa3af; font-size: 11px; padding: 14px; border-top: 1px solid #eef2f7; }
    </style>
</head>
<body>
    <div class="card">
        <div class="top">
            <div class="co">{{ $company->company_name ?? 'Document Verification' }}</div>
            <div class="co-sub">Document Verification</div>
        </div>

        <div class="body">
            @if($found)
                <div class="verdict ok">
                    <div class="icon">&#10004;</div>
                    <div class="msg">Authentic {{ $docLabel }}</div>
                    <div class="sub">This document was issued by {{ $company->company_name ?? 'us' }}.</div>
                </div>
                <table>
                    <tr><td class="lbl">Document</td><td class="val">{{ $docLabel }}</td></tr>
                    <tr><td class="lbl">Number</td><td class="val" style="font-family:monospace;">{{ $number }}</td></tr>
                    @if(!empty($date))<tr><td class="lbl">Date</td><td class="val">{{ $date }}</td></tr>@endif
                    @if(!empty($party))<tr><td class="lbl">Party</td><td class="val">{{ $party }}</td></tr>@endif
                    @if($amount !== null)<tr><td class="lbl">Amount</td><td class="val">{{ $currency }} {{ number_format($amount, 2) }}</td></tr>@endif
                    @if(!empty($status))<tr><td class="lbl">Status</td><td class="val"><span class="badge">{{ ucwords(str_replace('_',' ',$status)) }}</span></td></tr>@endif
                </table>
            @else
                <div class="verdict bad">
                    <div class="icon">&#10007;</div>
                    <div class="msg">Document not found</div>
                    <div class="sub">This {{ strtolower($docLabel) }} could not be located. It may have been removed, or the code is invalid.</div>
                </div>
            @endif
        </div>

        <div class="ftr">
            &copy; {{ date('Y') }} {{ $company->software_provider ?? 'CYM Software' }} &middot; Verified {{ now()->format('d M Y H:i') }}
        </div>
    </div>
</body>
</html>
