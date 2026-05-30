<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Repair Estimate {{ $estimate->estimate_no }}</title>
</head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.10);">

  {{-- ── HEADER ── --}}
  <tr>
    <td style="background:linear-gradient(135deg,#1a56db 0%,#0d6efd 100%);padding:32px 40px;text-align:center;">
      @if(!empty($company->logo_url))
        <img src="{{ $company->logo_url }}" alt="{{ $company->company_name }}" style="max-height:56px;max-width:200px;display:block;margin:0 auto 12px;">
      @else
        <div style="color:#ffffff;font-size:1.25rem;font-weight:700;letter-spacing:.5px;margin-bottom:10px;">{{ $company->company_name }}</div>
      @endif
      <div style="display:inline-block;background:rgba(255,255,255,0.18);border-radius:20px;padding:4px 16px;margin-bottom:8px;">
        <span style="color:#e0eaff;font-size:.78rem;font-weight:600;letter-spacing:1px;text-transform:uppercase;">Repair Estimate</span>
      </div>
      <div style="color:#ffffff;font-size:1.5rem;font-weight:700;margin:4px 0 0;">{{ $estimate->estimate_no }}</div>
    </td>
  </tr>

  {{-- ── BODY ── --}}
  <tr>
    <td style="padding:36px 40px;color:#212529;">

      <p style="margin:0 0 6px;font-size:.95rem;color:#495057;">Dear Owner / Principal,</p>

      @if($customMessage)
        <p style="margin:12px 0 0;font-size:.95rem;line-height:1.65;color:#212529;">{{ $customMessage }}</p>
      @else
        <p style="margin:12px 0 0;font-size:.95rem;line-height:1.65;color:#212529;">
          Please find below your repair estimate for container <strong>{{ $estimate->container_no }}</strong>.
          Kindly review and respond at your earliest convenience.
        </p>
      @endif

      {{-- ── Info card ── --}}
      <table width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;background:#f8f9fa;border-radius:8px;border:1px solid #e9ecef;overflow:hidden;">
        <tr>
          <td style="padding:10px 18px;border-bottom:1px solid #e9ecef;width:48%;">
            <div style="font-size:.78rem;color:#6c757d;margin-bottom:2px;">Estimate No.</div>
            <div style="font-size:.95rem;font-weight:700;color:#1a56db;font-family:monospace;">{{ $estimate->estimate_no }}</div>
          </td>
          <td style="padding:10px 18px;border-bottom:1px solid #e9ecef;">
            <div style="font-size:.78rem;color:#6c757d;margin-bottom:2px;">Container No.</div>
            <div style="font-size:.95rem;font-weight:700;">{{ $estimate->container_no }}</div>
          </td>
        </tr>
        <tr>
          <td style="padding:10px 18px;border-bottom:1px solid #e9ecef;">
            <div style="font-size:.78rem;color:#6c757d;margin-bottom:2px;">Issue Date</div>
            <div style="font-size:.95rem;font-weight:600;">{{ $estimate->estimate_date->format('d M Y') }}</div>
          </td>
          <td style="padding:10px 18px;border-bottom:1px solid #e9ecef;">
            <div style="font-size:.78rem;color:#6c757d;margin-bottom:2px;">Valid Until</div>
            <div style="font-size:.95rem;font-weight:600;color:#dc3545;">{{ $estimate->valid_until->format('d M Y') }}</div>
          </td>
        </tr>
        <tr>
          <td colspan="2" style="padding:12px 18px;background:#1a56db;border-radius:0 0 7px 7px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="color:#cfe2ff;font-size:.85rem;font-weight:600;">Grand Total</td>
                <td style="text-align:right;color:#ffffff;font-size:1.15rem;font-weight:700;">{{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}</td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      {{-- ── Line items ── --}}
      <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:.875rem;margin-bottom:4px;">
        <thead>
          <tr style="background:#e9ecef;">
            <th style="padding:8px 10px;text-align:left;color:#495057;font-size:.78rem;font-weight:700;border-bottom:2px solid #dee2e6;">#</th>
            <th style="padding:8px 10px;text-align:left;color:#495057;font-size:.78rem;font-weight:700;border-bottom:2px solid #dee2e6;">Description</th>
            <th style="padding:8px 10px;text-align:right;color:#495057;font-size:.78rem;font-weight:700;border-bottom:2px solid #dee2e6;">Qty</th>
            <th style="padding:8px 10px;text-align:right;color:#495057;font-size:.78rem;font-weight:700;border-bottom:2px solid #dee2e6;">Unit Price</th>
            <th style="padding:8px 10px;text-align:right;color:#495057;font-size:.78rem;font-weight:700;border-bottom:2px solid #dee2e6;">Amount</th>
          </tr>
        </thead>
        <tbody>
          @foreach($estimate->lineItems as $i => $line)
          <tr style="background:{{ $loop->even ? '#f8f9fa' : '#ffffff' }};">
            <td style="padding:7px 10px;color:#6c757d;border-bottom:1px solid #f1f3f5;">{{ $i + 1 }}</td>
            <td style="padding:7px 10px;border-bottom:1px solid #f1f3f5;">{{ $line->component }} — {{ $line->repair_type }}</td>
            <td style="padding:7px 10px;text-align:right;border-bottom:1px solid #f1f3f5;">{{ number_format($line->qty, 2) }}</td>
            <td style="padding:7px 10px;text-align:right;border-bottom:1px solid #f1f3f5;">{{ number_format($line->unit_price, 2) }}</td>
            <td style="padding:7px 10px;text-align:right;font-weight:600;border-bottom:1px solid #f1f3f5;">{{ number_format($line->line_amount, 2) }}</td>
          </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr>
            <td colspan="4" style="padding:8px 10px;text-align:right;color:#6c757d;font-size:.82rem;border-top:1px solid #dee2e6;">Subtotal</td>
            <td style="padding:8px 10px;text-align:right;border-top:1px solid #dee2e6;">{{ number_format($estimate->subtotal, 2) }}</td>
          </tr>
          @if($estimate->sscl_amount > 0)
          <tr>
            <td colspan="4" style="padding:4px 10px;text-align:right;color:#6c757d;font-size:.82rem;">SSCL</td>
            <td style="padding:4px 10px;text-align:right;">{{ number_format($estimate->sscl_amount, 2) }}</td>
          </tr>
          @endif
          @if($estimate->vat_amount > 0)
          <tr>
            <td colspan="4" style="padding:4px 10px;text-align:right;color:#6c757d;font-size:.82rem;">VAT</td>
            <td style="padding:4px 10px;text-align:right;">{{ number_format($estimate->vat_amount, 2) }}</td>
          </tr>
          @endif
          <tr style="background:#f0f4ff;">
            <td colspan="4" style="padding:10px 10px;text-align:right;font-weight:700;font-size:.95rem;border-top:2px solid #1a56db;color:#1a56db;">Grand Total</td>
            <td style="padding:10px 10px;text-align:right;font-weight:800;font-size:.95rem;border-top:2px solid #1a56db;color:#1a56db;">{{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}</td>
          </tr>
        </tfoot>
      </table>

      {{-- ── CTA button ── --}}
      @php $portalUrl = url('/portal/estimate/' . $portalToken->token); @endphp
      <table width="100%" cellpadding="0" cellspacing="0" style="margin:32px 0 20px;">
        <tr>
          <td align="center">
            <a href="{{ $portalUrl }}"
               style="display:inline-block;background:#1a56db;color:#ffffff;text-decoration:none;padding:14px 36px;border-radius:8px;font-weight:700;font-size:1rem;letter-spacing:.3px;border:none;">
              &#x1F4CB;&nbsp; Review &amp; Approve / Reject
            </a>
          </td>
        </tr>
        @if($portalToken->expires_at)
        <tr>
          <td align="center" style="padding-top:10px;font-size:.8rem;color:#dc3545;font-weight:500;">
            This link expires on {{ $portalToken->expires_at->format('d M Y, H:i') }} UTC
          </td>
        </tr>
        @endif
      </table>

      <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px;background:#f8f9fa;border-radius:6px;border:1px solid #e9ecef;">
        <tr>
          <td style="padding:12px 16px;">
            <div style="font-size:.78rem;color:#6c757d;margin-bottom:4px;">If the button above does not work, copy and paste this link into your browser:</div>
            <a href="{{ $portalUrl }}" style="font-size:.78rem;color:#1a56db;word-break:break-all;">{{ $portalUrl }}</a>
          </td>
        </tr>
      </table>

    </td>
  </tr>

  {{-- ── FOOTER ── --}}
  <tr>
    <td style="background:#f8f9fa;padding:20px 40px;border-top:1px solid #e9ecef;text-align:center;">
      <div style="color:#495057;font-size:.82rem;font-weight:600;margin-bottom:4px;">{{ $company->company_name }}</div>
      @if(!empty($company->address))
        <div style="color:#6c757d;font-size:.78rem;">{{ $company->address }}</div>
      @endif
      @if(!empty($company->email))
        <div style="color:#6c757d;font-size:.78rem;">{{ $company->email }}</div>
      @endif
      <div style="color:#adb5bd;font-size:.75rem;margin-top:10px;border-top:1px solid #e9ecef;padding-top:10px;">
        This email was sent automatically. Please do not reply directly.
      </div>
    </td>
  </tr>

</table>
</td></tr>
</table>

</body>
</html>
