<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Repair Estimate {{ $estimate->estimate_no }}</title>
</head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;">

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
        <span style="color:#e0eaff;font-size:.78rem;font-weight:600;letter-spacing:1px;text-transform:uppercase;">Repair Estimate</span>
      </div>
      <div style="color:#ffffff;font-size:1.5rem;font-weight:700;margin:4px 0 0;">{{ $estimate->estimate_no }}</div>
    </td>
  </tr>

  {{-- ── BODY ── --}}
  <tr>
    <td style="padding:24px 20px;color:#212529;">

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
                <td style="text-align:right;color:#ffffff;font-size:1.15rem;font-weight:700;white-space:nowrap;">{{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}</td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      {{-- ── Exchange rate notice ── --}}
      @if($estimate->exchange_rate && $estimate->exchange_rate != 1 && $estimate->currency !== 'USD')
      <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">
        <tr>
          <td style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:10px 14px;font-size:.8rem;color:#1e40af;">
            <strong>&#x1F4B1; Currency Notice:</strong>
            All amounts are in <strong>{{ $estimate->currency }}</strong>.
            Rates converted from USD at <strong>1 USD = {{ number_format((float)$estimate->exchange_rate, 4) }} {{ $estimate->currency }}</strong>
            as at {{ $estimate->estimate_date->format('d M Y') }}.
          </td>
        </tr>
      </table>
      @endif

      {{-- ── Line items ── --}}
      {{-- columns: # | Component | Repair Type | Qty/Size | Labour | Materials | Line Total --}}
      <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:.875rem;margin-bottom:4px;">
        <thead>
          <tr style="background:#e9ecef;">
            <th style="padding:8px 6px;text-align:left;color:#495057;font-size:.75rem;font-weight:700;border-bottom:2px solid #dee2e6;width:4%;">#</th>
            <th style="padding:8px 8px;text-align:left;color:#495057;font-size:.75rem;font-weight:700;border-bottom:2px solid #dee2e6;">Component</th>
            <th style="padding:8px 8px;text-align:left;color:#495057;font-size:.75rem;font-weight:700;border-bottom:2px solid #dee2e6;width:13%;">Repair Type</th>
            <th style="padding:8px 8px;text-align:right;color:#495057;font-size:.75rem;font-weight:700;border-bottom:2px solid #dee2e6;width:11%;">Qty / Size</th>
            <th style="padding:8px 8px;text-align:right;color:#1a56db;font-size:.75rem;font-weight:700;border-bottom:2px solid #dee2e6;width:14%;">&#x1F527; Labour</th>
            <th style="padding:8px 8px;text-align:right;color:#166534;font-size:.75rem;font-weight:700;border-bottom:2px solid #dee2e6;width:13%;">&#x1F4E6; Materials</th>
            <th style="padding:8px 8px;text-align:right;color:#495057;font-size:.75rem;font-weight:700;border-bottom:2px solid #dee2e6;width:13%;">Line Total</th>
          </tr>
        </thead>
        <tbody>
          @foreach($estimate->lineItems as $i => $line)
          <tr style="background:{{ $loop->even ? '#f8f9fa' : '#ffffff' }};">
            <td style="padding:9px 6px;color:#6c757d;border-bottom:1px solid #f1f3f5;vertical-align:top;">{{ $i + 1 }}</td>

            {{-- Component --}}
            <td style="padding:9px 8px;border-bottom:1px solid #f1f3f5;vertical-align:top;">
              <div style="font-weight:600;color:#212529;">{{ $line->component }}</div>
              @if($line->componentCode || $line->chargeCode)
              <div style="margin-top:3px;">
                @if($line->componentCode)
                  <span style="display:inline-block;font-size:.68rem;font-weight:700;padding:1px 6px;border-radius:20px;background:#dbeafe;color:#1d4ed8;font-family:monospace;">{{ $line->componentCode->code }}</span>
                @endif
                @if($line->chargeCode)
                  <span style="display:inline-block;font-size:.68rem;font-weight:700;padding:1px 6px;border-radius:20px;background:#d1fae5;color:#065f46;font-family:monospace;margin-left:3px;">{{ $line->chargeCode->code }}</span>
                @endif
              </div>
              @endif
            </td>

            {{-- Repair Type --}}
            <td style="padding:9px 8px;border-bottom:1px solid #f1f3f5;vertical-align:top;color:#6c757d;">
              {{ $line->repair_type ? ucfirst(str_replace('_', ' ', $line->repair_type)) : '—' }}
            </td>

            {{-- Qty / Size --}}
            @php
              $eL  = (float)($line->dim_length ?? 0);
              $eW  = (float)($line->dim_width  ?? 0);
              $eUom = $line->dim_uom ?? 'ft_in';
              $eDimStr = null;
              if ($eL > 0) {
                if ($eUom === 'ft_in') {
                  $eFtL = (int)floor($eL / 12); $eInL = round(fmod($eL, 12), 2);
                  $eDimStr = ($eFtL > 0 ? $eFtL.' ft ' : '').$eInL.' in';
                  if ($eW > 0) {
                    $eFtW = (int)floor($eW / 12); $eInW = round(fmod($eW, 12), 2);
                    $eDimStr .= ' × '.($eFtW > 0 ? $eFtW.' ft ' : '').$eInW.' in';
                  }
                } else {
                  $eU = $eUom === 'm' ? 'm' : 'cm';
                  $eDimStr = number_format($eL, 1).' '.$eU;
                  if ($eW > 0) $eDimStr .= ' × '.number_format($eW, 1).' '.$eU;
                }
              }
              $eQtyUnit = '';
              if ($eL > 0 && $eW > 0)  $eQtyUnit = 'sqft';
              elseif ($eL > 0)          $eQtyUnit = $eUom === 'ft_in' ? 'in' : $eUom;
            @endphp
            <td style="padding:9px 8px;text-align:right;border-bottom:1px solid #f1f3f5;vertical-align:top;white-space:nowrap;">
              @if($line->qty > 0)
                <div style="font-weight:700;font-size:.88rem;">{{ number_format($line->qty, 2) }}@if($eQtyUnit)<span style="font-size:.72rem;font-weight:400;color:#6c757d;"> {{ $eQtyUnit }}</span>@endif</div>
              @endif
              @if($eDimStr)
                <div style="font-size:.72rem;color:#6c757d;">📏 {{ $eDimStr }}</div>
              @elseif(!($line->qty > 0))
                <span style="color:#adb5bd;">—</span>
              @endif
            </td>

            {{-- Labour: hrs + cost stacked --}}
            <td style="padding:9px 8px;text-align:right;border-bottom:1px solid #f1f3f5;vertical-align:top;white-space:nowrap;">
              @if($line->std_labor_hours > 0 || $line->labor_amount > 0)
                @if($line->std_labor_hours > 0)
                  <div style="font-weight:700;color:#1a56db;font-size:.88rem;">{{ number_format($line->std_labor_hours, 2) }} <span style="font-size:.72rem;font-weight:500;">hrs</span></div>
                @endif
                <div style="color:#6c757d;font-size:.76rem;">{{ $estimate->currency }} {{ number_format($line->labor_amount, 2) }}</div>
              @else
                <span style="color:#adb5bd;">—</span>
              @endif
            </td>

            {{-- Materials cost --}}
            <td style="padding:9px 8px;text-align:right;border-bottom:1px solid #f1f3f5;vertical-align:top;white-space:nowrap;">
              @if($line->material_amount > 0)
                <div style="font-weight:600;color:#166534;">{{ $estimate->currency }} {{ number_format($line->material_amount, 2) }}</div>
                @if($line->ancillary_amount > 0)
                  <div style="color:#6c757d;font-size:.76rem;" title="Ancillary">+ {{ $estimate->currency }} {{ number_format($line->ancillary_amount, 2) }}</div>
                @endif
              @elseif($line->ancillary_amount > 0)
                <div style="color:#6c757d;font-size:.82rem;">{{ $estimate->currency }} {{ number_format($line->ancillary_amount, 2) }}</div>
              @else
                <span style="color:#adb5bd;">—</span>
              @endif
            </td>

            {{-- Line total --}}
            <td style="padding:9px 8px;text-align:right;font-weight:700;border-bottom:1px solid #f1f3f5;white-space:nowrap;">{{ $estimate->currency }} {{ number_format($line->line_amount, 2) }}</td>
          </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr>
            <td colspan="6" style="padding:8px 8px;text-align:right;color:#6c757d;font-size:.82rem;border-top:1px solid #dee2e6;">Subtotal</td>
            <td style="padding:8px 8px;text-align:right;border-top:1px solid #dee2e6;white-space:nowrap;">{{ number_format($estimate->subtotal, 2) }}</td>
          </tr>
          @if($estimate->sscl_amount > 0)
          <tr>
            <td colspan="6" style="padding:4px 8px;text-align:right;color:#6c757d;font-size:.82rem;">SSCL</td>
            <td style="padding:4px 8px;text-align:right;white-space:nowrap;">{{ number_format($estimate->sscl_amount, 2) }}</td>
          </tr>
          @endif
          @if($estimate->vat_amount > 0)
          <tr>
            <td colspan="6" style="padding:4px 8px;text-align:right;color:#6c757d;font-size:.82rem;">VAT</td>
            <td style="padding:4px 8px;text-align:right;white-space:nowrap;">{{ number_format($estimate->vat_amount, 2) }}</td>
          </tr>
          @endif
          <tr style="background:#f0f4ff;">
            <td colspan="6" style="padding:10px 8px;text-align:right;font-weight:700;font-size:.95rem;border-top:2px solid #1a56db;color:#1a56db;">Grand Total</td>
            <td style="padding:10px 8px;text-align:right;font-weight:800;font-size:.95rem;border-top:2px solid #1a56db;color:#1a56db;white-space:nowrap;">{{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}</td>
          </tr>
        </tfoot>
      </table>

      {{-- ── Cost Breakdown Summary ── --}}
      @php
        $emailLaborHrs  = $estimate->lineItems->sum('std_labor_hours');
        $emailLaborCost = $estimate->lineItems->sum('labor_amount');
        $emailMaterial  = $estimate->lineItems->sum('material_amount');
        $emailAncillary = $estimate->lineItems->sum('ancillary_amount');
      @endphp
      @if($emailLaborHrs > 0 || $emailLaborCost > 0 || $emailMaterial > 0)
      <table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border-collapse:collapse;font-size:.875rem;">
        <thead>
          <tr style="background:#e9ecef;">
            <th colspan="3" style="padding:8px 10px;text-align:left;color:#495057;font-size:.78rem;font-weight:700;border-bottom:2px solid #dee2e6;letter-spacing:.3px;">
              &#x1F4CA;&nbsp; Cost Breakdown Summary
            </th>
          </tr>
          <tr style="background:#f8f9fa;">
            <th style="padding:6px 10px;text-align:left;color:#6c757d;font-size:.75rem;font-weight:600;border-bottom:1px solid #dee2e6;">Component</th>
            <th style="padding:6px 10px;text-align:right;color:#6c757d;font-size:.75rem;font-weight:600;border-bottom:1px solid #dee2e6;">Hours / Qty</th>
            <th style="padding:6px 10px;text-align:right;color:#6c757d;font-size:.75rem;font-weight:600;border-bottom:1px solid #dee2e6;">Total Cost</th>
          </tr>
        </thead>
        <tbody>
          @if($emailLaborHrs > 0 || $emailLaborCost > 0)
          <tr style="background:#ffffff;">
            <td style="padding:7px 10px;border-bottom:1px solid #f1f3f5;color:#1a56db;font-weight:600;">&#x1F527;&nbsp;Labour</td>
            <td style="padding:7px 10px;text-align:right;border-bottom:1px solid #f1f3f5;color:#1a56db;font-weight:600;white-space:nowrap;">
              @if($emailLaborHrs > 0){{ number_format($emailLaborHrs, 2) }} hrs @else — @endif
            </td>
            <td style="padding:7px 10px;text-align:right;border-bottom:1px solid #f1f3f5;font-weight:600;white-space:nowrap;">{{ $estimate->currency }} {{ number_format($emailLaborCost, 2) }}</td>
          </tr>
          @endif
          @if($emailMaterial > 0)
          <tr style="background:#f8f9fa;">
            <td style="padding:7px 10px;border-bottom:1px solid #f1f3f5;color:#166534;font-weight:600;">&#x1F4E6;&nbsp;Materials</td>
            <td style="padding:7px 10px;text-align:right;border-bottom:1px solid #f1f3f5;color:#6c757d;">—</td>
            <td style="padding:7px 10px;text-align:right;border-bottom:1px solid #f1f3f5;font-weight:600;white-space:nowrap;">{{ $estimate->currency }} {{ number_format($emailMaterial, 2) }}</td>
          </tr>
          @endif
          @if($emailAncillary > 0)
          <tr style="background:#ffffff;">
            <td style="padding:7px 10px;border-bottom:1px solid #f1f3f5;color:#495057;font-weight:600;">Ancillary / Overhead</td>
            <td style="padding:7px 10px;text-align:right;border-bottom:1px solid #f1f3f5;color:#6c757d;">—</td>
            <td style="padding:7px 10px;text-align:right;border-bottom:1px solid #f1f3f5;font-weight:600;white-space:nowrap;">{{ $estimate->currency }} {{ number_format($emailAncillary, 2) }}</td>
          </tr>
          @endif
        </tbody>
        <tfoot>
          <tr style="background:#f0f4ff;">
            <td colspan="2" style="padding:9px 10px;font-weight:700;font-size:.9rem;border-top:2px solid #1a56db;color:#1a56db;">Grand Total</td>
            <td style="padding:9px 10px;text-align:right;font-weight:800;font-size:.9rem;border-top:2px solid #1a56db;color:#1a56db;white-space:nowrap;">{{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}</td>
          </tr>
        </tfoot>
      </table>
      @endif

      {{-- ── Primary CTA: Review & Approve ── --}}
      @php
        $portalUrl  = url('/portal/estimate/' . $portalToken->token);
        $photosUrl  = url('/portal/estimate/' . $portalToken->token . '/photos');
      @endphp
      <table width="100%" cellpadding="0" cellspacing="0" style="margin:32px 0 0;">
        <tr>
          <td align="center">
            {{-- Table-cell button is the only reliable way to keep white text in Outlook --}}
            <table cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td align="center" style="border-radius:8px;background:#16a34a;mso-padding-alt:0;">
                  <a href="{{ $portalUrl }}" target="_blank"
                     style="display:inline-block;padding:14px 36px;font-family:-apple-system,BlinkMacSystemFont,Arial,sans-serif;font-size:1rem;font-weight:700;letter-spacing:.3px;text-decoration:none;border-radius:8px;color:#ffffff;">
                    &#x2705;&nbsp; Review &amp; Approve / Reject
                  </a>
                </td>
              </tr>
            </table>
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

      {{-- ── Survey Photos link (shown only when photos exist) ── --}}
      @if($photoCount > 0)
      <table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0 0;">
        <tr>
          <td style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:14px 20px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="vertical-align:middle;">
                  <div style="font-size:.9rem;font-weight:600;color:#0c4a6e;margin-bottom:2px;">
                    &#x1F4F7;&nbsp; Survey Photos Available
                  </div>
                  <div style="font-size:.8rem;color:#0369a1;">
                    {{ $photoCount }} photo{{ $photoCount !== 1 ? 's' : '' }} from the damage survey are available for your review.
                  </div>
                </td>
                <td align="right" style="vertical-align:middle;padding-left:16px;white-space:nowrap;">
                  <table cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td align="center" style="border-radius:6px;background:#0284c7;mso-padding-alt:0;">
                        <a href="{{ $photosUrl }}" target="_blank"
                           style="display:inline-block;padding:8px 18px;font-family:-apple-system,BlinkMacSystemFont,Arial,sans-serif;font-size:.82rem;font-weight:700;text-decoration:none;border-radius:6px;color:#ffffff;">
                          View Photos
                        </a>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
      @endif

      {{-- ── Fallback URL ── --}}
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;background:#f8f9fa;border-radius:6px;border:1px solid #e9ecef;">
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
    <td style="background:#f8f9fa;padding:20px;border-top:1px solid #e9ecef;text-align:center;">
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
      <div style="color:#ced4da;font-size:.72rem;margin-top:6px;">
        &copy; {{ date('Y') }} {{ $company->software_provider ?? 'CYM Software' }}
      </div>
    </td>
  </tr>

</table>
</td></tr>
</table>

</body>
</html>
