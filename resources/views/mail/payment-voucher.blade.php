<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Payment Voucher {{ $voucher->voucher_no }}</title></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif;">
@php
    $cur = strtoupper($voucher->currency ?: 'LKR');
@endphp
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:20px 8px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.08);">
    <tr><td style="background:linear-gradient(135deg,#b02a37,#dc3545);padding:28px 40px;text-align:center;">
        <div style="color:#fff;font-size:20px;font-weight:bold;">{{ $company->company_name }}</div>
        <div style="color:#ffdfe2;font-size:13px;margin-top:4px;">Payment Voucher</div>
    </td></tr>
    <tr><td style="padding:30px 40px;color:#333;font-size:14px;line-height:1.6;">
        <p style="margin:0 0 14px;">Dear {{ $voucher->payee_name }},</p>
        <p style="margin:0 0 14px;">Please find payment voucher
            <strong>{{ $voucher->voucher_no }}</strong> attached.</p>
        @if($customMessage)
        <p style="margin:0 0 14px;padding:12px 14px;background:#fff5f6;border-left:3px solid #dc3545;border-radius:4px;">{{ $customMessage }}</p>
        @endif
        <table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border:1px solid #e5e9f0;border-radius:6px;">
            <tr><td style="padding:10px 14px;color:#666;border-bottom:1px solid #eee;">Voucher No</td>
                <td style="padding:10px 14px;text-align:right;font-weight:bold;border-bottom:1px solid #eee;">{{ $voucher->voucher_no }}</td></tr>
            <tr><td style="padding:10px 14px;color:#666;border-bottom:1px solid #eee;">Date</td>
                <td style="padding:10px 14px;text-align:right;font-weight:bold;border-bottom:1px solid #eee;">{{ \Carbon\Carbon::parse($voucher->voucher_date)->format('d M Y') }}</td></tr>
            <tr><td style="padding:10px 14px;color:#666;">Amount Paid</td>
                <td style="padding:10px 14px;text-align:right;font-weight:bold;color:#b02a37;">{{ $cur }} {{ number_format($voucher->amount, 2) }}</td></tr>
        </table>
        <p style="margin:18px 0 0;color:#888;font-size:12px;">This is a computer-generated email from {{ $company->company_name }}.</p>
        <p style="margin:6px 0 0;color:#adb5bd;font-size:11px;">&copy; {{ date('Y') }} {{ $company->software_provider ?? 'CYM Software' }}</p>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
