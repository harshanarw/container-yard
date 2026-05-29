<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reminder: Estimate {{ $estimate->estimate_no }}</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f6f8; margin: 0; padding: 0; }
  .wrapper { max-width: 620px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
  .header { background: #fd7e14; padding: 28px 36px; text-align: center; }
  .header img { max-height: 52px; }
  .header h1 { color: #fff; margin: 12px 0 0; font-size: 1.3rem; font-weight: 600; }
  .body { padding: 32px 36px; color: #212529; font-size: .95rem; line-height: 1.6; }
  .btn { display: inline-block; background: #fd7e14; color: #fff !important; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: 600; margin: 20px 0; font-size: 1rem; }
  .expiry { color: #dc3545; font-size: .85rem; margin-top: 8px; }
  .footer { background: #f8f9fa; padding: 20px 36px; text-align: center; color: #6c757d; font-size: .8rem; border-top: 1px solid #e9ecef; }
  .info-box { background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 16px 20px; margin: 20px 0; }
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
    <h1>Reminder: Action Required</h1>
  </div>

  <div class="body">
    <p>Dear Owner / Principal,</p>

    <div class="info-box">
      <strong>This is a reminder</strong> — Estimate <strong>{{ $estimate->estimate_no }}</strong> for container
      <strong>{{ $estimate->container_no }}</strong> is still awaiting your approval.
    </div>

    <p>Please review and respond as soon as possible. The estimate is valid until
       <strong>{{ $estimate->valid_until->format('d M Y') }}</strong>.</p>

    <p><strong>Grand Total:</strong> {{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}</p>

    <div style="text-align: center; margin-top: 28px;">
      @php $portalUrl = url('/portal/estimate/' . $portalToken->token); @endphp
      <a href="{{ $portalUrl }}" class="btn">Review &amp; Respond Now</a>
      @if($portalToken->expires_at)
      <p class="expiry">Link expires on {{ $portalToken->expires_at->format('d M Y, H:i') }} UTC</p>
      @endif
    </div>
  </div>

  <div class="footer">
    <p>{{ $company->company_name }}
    @if($company->email) · {{ $company->email }} @endif</p>
  </div>
</div>
</body>
</html>
