<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Estimate Response Received</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f6f8; margin: 0; padding: 0; }
  .wrapper { max-width: 620px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
  .header-approved  { background: #198754; padding: 24px 36px; text-align: center; }
  .header-rejected  { background: #dc3545; padding: 24px 36px; text-align: center; }
  .header-partial   { background: #0d6efd; padding: 24px 36px; text-align: center; }
  .header-default   { background: #6c757d; padding: 24px 36px; text-align: center; }
  .header-approved h1, .header-rejected h1, .header-partial h1, .header-default h1 { color: #fff; margin: 0; font-size: 1.2rem; }
  .body { padding: 32px 36px; color: #212529; font-size: .95rem; line-height: 1.6; }
  .footer { background: #f8f9fa; padding: 20px 36px; text-align: center; color: #6c757d; font-size: .8rem; border-top: 1px solid #e9ecef; }
  .info-box { background: #f8f9fa; border-radius: 6px; padding: 16px 20px; margin: 16px 0; }
</style>
</head>
<body>
<div class="wrapper">
  @php
    $headerClass = match($action) {
      'approved'           => 'header-approved',
      'rejected'           => 'header-rejected',
      'partially_approved' => 'header-partial',
      default              => 'header-default',
    };
    $label = match($action) {
      'approved'           => 'Approved',
      'rejected'           => 'Rejected',
      'partially_approved' => 'Partially Approved',
      default              => ucfirst($action),
    };
  @endphp
  <div class="{{ $headerClass }}">
    <h1>Estimate {{ $label }} by Owner</h1>
  </div>

  <div class="body">
    <p>The owner/principal has responded to Estimate <strong>{{ $estimate->estimate_no }}</strong>.</p>

    <div class="info-box">
      <div><strong>Estimate:</strong> {{ $estimate->estimate_no }}</div>
      <div><strong>Container:</strong> {{ $estimate->container_no }}</div>
      <div><strong>Decision:</strong> {{ $label }}</div>
      @if($ownerNotes)
      <div style="margin-top:10px"><strong>Owner Notes:</strong><br>{{ $ownerNotes }}</div>
      @endif
    </div>

    <p>Please log in to the system to review the decision and proceed accordingly.</p>
  </div>

  <div class="footer">
    <p>{{ $company->company_name }} — Internal Notification</p>
  </div>
</div>
</body>
</html>
