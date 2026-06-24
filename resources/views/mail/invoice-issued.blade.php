<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Storage Invoice {{ $invoice->invoice_no }}</title>
</head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;">

@php
    $dispCur  = $invoice->invoice_currency ?? 'LKR';
    $dispRate = (float) ($invoice->exchange_rate ?? 1.0);
    $disp     = fn($lkr) => $dispCur === 'LKR' ? $lkr : round($lkr / $dispRate, 2);
    $fmtAmt   = fn($lkr) => $dispCur . ' ' . number_format($disp($lkr), 2);

    $billingParty = $invoice->billingParty ?? $invoice->customer;
    $fromDate = $invoice->billing_period_from?->format('d M Y');
    $toDate   = $invoice->billing_period_to?->format('d M Y');
@endphp

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:20px 8px;">
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
        <span style="color:#e0eaff;font-size:.78rem;font-weight:600;letter-spacing:1px;text-transform:uppercase;">Storage Invoice</span>
      </div>
      <div style="color:#ffffff;font-size:1.5rem;font-weight:700;margin:4px 0 0;">{{ $invoice->invoice_no }}</div>
    </td>
  </tr>

  {{-- ── BODY ── --}}
  <tr>
    <td style="padding:24px 20px;color:#212529;">

      <p style="margin:0 0 6px;font-size:.95rem;color:#495057;">Dear {{ $billingParty->contact_person ?? $billingParty->name ?? 'Sir/Madam' }},</p>

      @if($customMessage)
        <p style="margin:12px 0 0;font-size:.95rem;line-height:1.65;color:#212529;">{{ $customMessage }}</p>
      @else
        <p style="margin:12px 0 0;font-size:.95rem;line-height:1.65;color:#212529;">
          Please find attached your storage invoice for the billing period
          <strong>{{ $fromDate }} &ndash; {{ $toDate }}</strong>.
          Kindly review and arrange payment at your earliest convenience.
        </p>
      @endif

      {{-- ── Info card ── --}}
      <table width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;background:#f8f9fa;border-radius:8px;border:1px solid #e9ecef;overflow:hidden;">
        <tr>
          <td style="padding:10px 18px;border-bottom:1px solid #e9ecef;width:48%;">
            <div style="font-size:.78rem;color:#6c757d;margin-bottom:2px;">Invoice No.</div>
            <div style="font-size:.95rem;font-weight:700;color:#1a56db;font-family:monospace;">{{ $invoice->invoice_no }}</div>
          </td>
          <td style="padding:10px 18px;border-bottom:1px solid #e9ecef;">
            <div style="font-size:.78rem;color:#6c757d;margin-bottom:2px;">Invoice Date</div>
            <div style="font-size:.95rem;font-weight:700;">{{ $invoice->invoice_date->format('d M Y') }}</div>
          </td>
        </tr>
        <tr>
          <td style="padding:10px 18px;border-bottom:1px solid #e9ecef;">
            <div style="font-size:.78rem;color:#6c757d;margin-bottom:2px;">Billing Period</div>
            <div style="font-size:.95rem;font-weight:600;">{{ $fromDate }} &ndash; {{ $toDate }}</div>
          </td>
          <td style="padding:10px 18px;border-bottom:1px solid #e9ecef;">
            <div style="font-size:.78rem;color:#6c757d;margin-bottom:2px;">Containers</div>
            <div style="font-size:.95rem;font-weight:600;">{{ $invoice->details->count() }}</div>
          </td>
        </tr>
        <tr>
          <td style="padding:10px 18px;" colspan="2">
            <div style="font-size:.78rem;color:#6c757d;margin-bottom:2px;">Total Amount Due</div>
            <div style="font-size:1.15rem;font-weight:700;color:#198754;">{{ $fmtAmt($invoice->total_amount) }}</div>
          </td>
        </tr>
      </table>

      {{-- ── Charge lines summary ── --}}
      <div style="font-size:.8rem;font-weight:700;color:#1a56db;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;margin-top:4px;">
        Container Storage Charges
      </div>
      <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:.82rem;">
        <thead>
          <tr style="background:#1a56db;color:#fff;">
            <th style="padding:6px 10px;text-align:left;">Container No.</th>
            <th style="padding:6px 10px;text-align:center;">From</th>
            <th style="padding:6px 10px;text-align:center;">To</th>
            <th style="padding:6px 10px;text-align:center;">Chargeable Days</th>
            <th style="padding:6px 10px;text-align:right;">Amount</th>
          </tr>
        </thead>
        <tbody>
          @foreach($invoice->details as $i => $line)
          <tr style="{{ $i % 2 === 0 ? 'background:#f8f9fa;' : '' }}">
            <td style="padding:5px 10px;font-family:monospace;font-weight:600;border-bottom:1px solid #e9ecef;">{{ $line->container_no }}</td>
            <td style="padding:5px 10px;text-align:center;border-bottom:1px solid #e9ecef;">{{ $line->from_date->format('d M Y') }}</td>
            <td style="padding:5px 10px;text-align:center;border-bottom:1px solid #e9ecef;">{{ $line->to_date->format('d M Y') }}</td>
            <td style="padding:5px 10px;text-align:center;border-bottom:1px solid #e9ecef;">{{ $line->chargeable_days }}</td>
            <td style="padding:5px 10px;text-align:right;border-bottom:1px solid #e9ecef;">{{ $fmtAmt($line->line_total) }}</td>
          </tr>
          @endforeach
        </tbody>
        <tfoot>
          @if(($invoice->sscl_amount ?? 0) > 0)
          <tr>
            <td colspan="4" style="padding:5px 10px;text-align:right;color:#6c757d;">Subtotal</td>
            <td style="padding:5px 10px;text-align:right;color:#6c757d;">{{ $fmtAmt($invoice->subtotal) }}</td>
          </tr>
          <tr>
            <td colspan="4" style="padding:5px 10px;text-align:right;color:#6c757d;">SSCL ({{ number_format($invoice->sscl_percentage, 2) }}%)</td>
            <td style="padding:5px 10px;text-align:right;color:#6c757d;">{{ $fmtAmt($invoice->sscl_amount) }}</td>
          </tr>
          @endif
          @if(($invoice->vat_amount ?? 0) > 0)
          <tr>
            <td colspan="4" style="padding:5px 10px;text-align:right;color:#6c757d;">VAT ({{ number_format($invoice->vat_percentage, 2) }}%)</td>
            <td style="padding:5px 10px;text-align:right;color:#6c757d;">{{ $fmtAmt($invoice->vat_amount) }}</td>
          </tr>
          @endif
          <tr style="background:#e8f0fe;">
            <td colspan="4" style="padding:7px 10px;text-align:right;font-weight:700;border-top:2px solid #1a56db;">Total Due</td>
            <td style="padding:7px 10px;text-align:right;font-weight:700;font-size:1rem;color:#198754;border-top:2px solid #1a56db;">{{ $fmtAmt($invoice->total_amount) }}</td>
          </tr>
        </tfoot>
      </table>

      <p style="margin:24px 0 0;font-size:.85rem;color:#6c757d;">
        The full invoice PDF is attached to this email. Please contact us if you have any questions regarding this invoice.
      </p>

    </td>
  </tr>

  {{-- ── FOOTER ── --}}
  <tr>
    <td style="background:#f8f9fa;border-top:1px solid #e9ecef;padding:16px 20px;text-align:center;">
      <p style="margin:0;font-size:.78rem;color:#6c757d;">
        {{ $company->company_name }}
        @if($company->phone_office) &nbsp;&middot;&nbsp; {{ $company->phone_office }} @endif
        @if($company->email) &nbsp;&middot;&nbsp; {{ $company->email }} @endif
      </p>
      @if($company->address)
      <p style="margin:4px 0 0;font-size:.75rem;color:#adb5bd;">{{ $company->address }}</p>
      @endif
      <p style="margin:6px 0 0;font-size:.72rem;color:#ced4da;">&copy; {{ date('Y') }} {{ $company->software_provider ?? 'CYM Software' }}</p>
    </td>
  </tr>

</table>
</td></tr>
</table>

</body>
</html>
