<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Receipt {{ $receipt->receipt_no }}</title></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif;">
@php
    $cur  = strtoupper($receipt->currency ?: 'LKR');
@endphp
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:20px 8px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.08);">
    <tr><td style="background:linear-gradient(135deg,#1a56db,#0d6efd);padding:28px 40px;text-align:center;">
        <div style="color:#fff;font-size:20px;font-weight:bold;">{{ $company->company_name }}</div>
        <div style="color:#dbe7ff;font-size:13px;margin-top:4px;">Payment Receipt</div>
    </td></tr>
    <tr><td style="padding:30px 40px;color:#333;font-size:14px;line-height:1.6;">
        <p style="margin:0 0 14px;">Dear {{ $receipt->customer->name ?? 'Customer' }},</p>
        <p style="margin:0 0 14px;">Thank you for your payment. Please find your receipt
            <strong>{{ $receipt->receipt_no }}</strong> attached.</p>
        @if($customMessage)
        <p style="margin:0 0 14px;padding:12px 14px;background:#f3f7ff;border-left:3px solid #0d6efd;border-radius:4px;">{{ $customMessage }}</p>
        @endif
        <table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border:1px solid #e5e9f0;border-radius:6px;">
            <tr><td style="padding:10px 14px;color:#666;border-bottom:1px solid #eee;">Receipt No</td>
                <td style="padding:10px 14px;text-align:right;font-weight:bold;border-bottom:1px solid #eee;">{{ $receipt->receipt_no }}</td></tr>
            <tr><td style="padding:10px 14px;color:#666;border-bottom:1px solid #eee;">Date</td>
                <td style="padding:10px 14px;text-align:right;font-weight:bold;border-bottom:1px solid #eee;">{{ \Carbon\Carbon::parse($receipt->receipt_date)->format('d M Y') }}</td></tr>
            <tr><td style="padding:10px 14px;color:#666;">Amount Received</td>
                <td style="padding:10px 14px;text-align:right;font-weight:bold;color:#198754;">{{ $cur }} {{ number_format($receipt->amount, 2) }}</td></tr>
        </table>
        <p style="margin:18px 0 0;color:#888;font-size:12px;">This is a computer-generated email from {{ $company->company_name }}.</p>
        <p style="margin:6px 0 0;color:#adb5bd;font-size:11px;">&copy; {{ date('Y') }} {{ $company->software_provider ?? 'CYM Software' }}</p>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
