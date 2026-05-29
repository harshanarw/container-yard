<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Repair Estimate {{ $estimate->estimate_no }}</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f6f8; margin: 0; padding: 0; }
  .wrapper { max-width: 620px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
  .header { background: #0d6efd; padding: 28px 36px; text-align: center; }
  .header img { max-height: 52px; }
  .header h1 { color: #fff; margin: 12px 0 0; font-size: 1.3rem; font-weight: 600; }
  .body { padding: 32px 36px; color: #212529; font-size: .95rem; line-height: 1.6; }
  .info-box { background: #f8f9fa; border-radius: 6px; padding: 18px 20px; margin: 20px 0; }
  .info-row { display: flex; gap: 8px; padding: 4px 0; border-bottom: 1px solid #e9ecef; }
  .info-row:last-child { border-bottom: none; }
  .info-label { color: #6c757d; font-size: .85rem; min-width: 130px; }
  .info-value { font-weight: 600; }
  .btn { display: inline-block; background: #0d6efd; color: #fff !important; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: 600; margin: 20px 0; font-size: 1rem; }
  .expiry { color: #dc3545; font-size: .85rem; margin-top: 8px; }
  .footer { background: #f8f9fa; padding: 20px 36px; text-align: center; color: #6c757d; font-size: .8rem; border-top: 1px solid #e9ecef; }
  table.lines { width: 100%; border-collapse: collapse; margin-top: 16px; }
  table.lines th { background: #e9ecef; padding: 8px 10px; text-align: left; font-size: .82rem; color: #495057; }
  table.lines td { padding: 7px 10px; font-size: .85rem; border-bottom: 1px solid #f1f3f5; }
  table.lines tfoot td { font-weight: 700; border-top: 2px solid #dee2e6; padding-top: 10px; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    @if($company->logo_url)
      <img src="{{ $company->logo_url }}" alt="{{ $company->company_name }}">
    @else
      <h1>{{ $company->company_name }}</h1>
    @endif
    <h1>Repair Estimate — Action Required</h1>
  </div>

  <div class="body">
    <p>Dear Owner / Principal,</p>

    @if($customMessage)
      <p>{{ $customMessage }}</p>
    @else
      <p>Please find below your repair estimate for container <strong>{{ $estimate->container_no }}</strong>.
      Kindly review and respond at your earliest convenience.</p>
    @endif

    <div class="info-box">
      <div class="info-row">
        <span class="info-label">Estimate No.</span>
        <span class="info-value font-monospace">{{ $estimate->estimate_no }}</span>
      </div>
      <div class="info-row">
        <span class="info-label">Container No.</span>
        <span class="info-value">{{ $estimate->container_no }}</span>
      </div>
      <div class="info-row">
        <span class="info-label">Issue Date</span>
        <span class="info-value">{{ $estimate->estimate_date->format('d M Y') }}</span>
      </div>
      <div class="info-row">
        <span class="info-label">Valid Until</span>
        <span class="info-value">{{ $estimate->valid_until->format('d M Y') }}</span>
      </div>
      <div class="info-row">
        <span class="info-label">Grand Total</span>
        <span class="info-value">{{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}</span>
      </div>
    </div>

    <table class="lines">
      <thead>
        <tr>
          <th>#</th>
          <th>Description</th>
          <th style="text-align:right">Qty</th>
          <th style="text-align:right">Unit Price</th>
          <th style="text-align:right">Amount</th>
        </tr>
      </thead>
      <tbody>
        @foreach($estimate->lineItems as $i => $line)
        <tr>
          <td>{{ $i + 1 }}</td>
          <td>{{ $line->component }} — {{ $line->repair_type }}</td>
          <td style="text-align:right">{{ $line->qty }}</td>
          <td style="text-align:right">{{ number_format($line->unit_price, 2) }}</td>
          <td style="text-align:right">{{ $estimate->currency }} {{ number_format($line->line_amount, 2) }}</td>
        </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4" style="text-align:right">Grand Total:</td>
          <td style="text-align:right">{{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}</td>
        </tr>
      </tfoot>
    </table>

    <div style="text-align: center; margin-top: 28px;">
      @php $portalUrl = url('/portal/estimate/' . $portalToken->token); @endphp
      <a href="{{ $portalUrl }}" class="btn">Review &amp; Approve / Reject</a>
      @if($portalToken->expires_at)
      <p class="expiry">This link expires on {{ $portalToken->expires_at->format('d M Y, H:i') }} UTC</p>
      @endif
    </div>

    <p style="color:#6c757d; font-size:.85rem; margin-top:24px;">
      If the button above does not work, copy and paste this link into your browser:<br>
      <a href="{{ $portalUrl }}">{{ $portalUrl }}</a>
    </p>
  </div>

  <div class="footer">
    <p>{{ $company->company_name }}
    @if($company->address) · {{ $company->address }} @endif
    @if($company->email) · {{ $company->email }} @endif</p>
    <p>This email was sent automatically. Please do not reply directly.</p>
  </div>
</div>
</body>
</html>
