<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer Decision Record — {{ $estimate->estimate_no }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #222; background: #fff; }
  .page { max-width: 820px; margin: 0 auto; padding: 36px 40px; }

  /* Header */
  .header { display: flex; justify-content: space-between; align-items: flex-start;
            border-bottom: 3px solid #1a56db; padding-bottom: 14px; margin-bottom: 20px; }
  .company-name { font-size: 17px; font-weight: bold; color: #1a56db; }
  .company-sub  { font-size: 10px; color: #666; margin-top: 3px; }
  .doc-title    { text-align: right; }
  .doc-title h1 { font-size: 14px; font-weight: bold; text-transform: uppercase;
                  letter-spacing: .5px; color: #1a56db; }
  .doc-title .ref { font-size: 11px; color: #444; margin-top: 4px; }

  /* Info table */
  .info-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  .info-table td { padding: 5px 10px; border: 1px solid #ddd; font-size: 11px; }
  .info-table td.lbl { background: #f4f6fb; font-weight: bold; color: #555; width: 22%; }
  .info-table td.val { font-weight: 600; }

  /* Decision summary pills */
  .summary-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; padding: 10px 12px;
                 background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; }
  .pill { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px;
          border-radius: 20px; font-size: 10px; font-weight: 700; }
  .pill-approved  { background: #d1fae5; color: #065f46; }
  .pill-rejected  { background: #fee2e2; color: #991b1b; }
  .pill-amended   { background: #fef3c7; color: #92400e; }
  .pill-pending   { background: #e5e7eb; color: #374151; }

  /* Line items */
  .items-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 11px; }
  .items-table thead th { background: #1a56db; color: #fff; padding: 6px 8px; text-align: left; }
  .items-table thead th.r { text-align: right; }
  .items-table tbody td { padding: 5px 8px; border-bottom: 1px solid #eee; vertical-align: top; }
  .items-table tbody td.r { text-align: right; }

  /* Row status colours */
  .row-approved  { background: #f0fdf4; }
  .row-rejected  { background: #fff5f5; }
  .row-amended   { background: #fffbeb; }
  .row-pending   { background: #f9fafb; }

  .strike { text-decoration: line-through; color: #9ca3af; }

  /* Decision badge in table */
  .badge { display: inline-block; padding: 2px 7px; border-radius: 10px;
           font-size: 10px; font-weight: 700; white-space: nowrap; }
  .badge-approved { background: #d1fae5; color: #065f46; }
  .badge-rejected { background: #fee2e2; color: #991b1b; }
  .badge-amended  { background: #fef3c7; color: #92400e; }
  .badge-pending  { background: #e5e7eb; color: #374151; }

  .line-note { font-size: 9.5px; color: #6b7280; font-style: italic; margin-top: 3px; }

  /* Footer totals */
  .items-table tfoot td { padding: 5px 8px; font-size: 11px; }
  .items-table tfoot tr.subtotal-row td { color: #555; }
  .items-table tfoot tr.rejected-row td { color: #b91c1c; font-style: italic; }
  .items-table tfoot tr.amended-row td  { color: #92400e; font-style: italic; }
  .items-table tfoot tr.agreed-row td  { font-weight: bold; font-size: 12px;
            background: #d1fae5; border-top: 2px solid #059669; color: #065f46; }
  .items-table tfoot tr.pending-row td { color: #6b7280; font-style: italic; }

  /* Pending warning */
  .pending-notice { border: 1px solid #f59e0b; border-radius: 4px; padding: 10px 14px;
                    background: #fffbeb; margin-bottom: 16px; font-size: 11px; color: #92400e; }

  /* Declaration */
  .declaration { border: 1px solid #bbb; border-radius: 4px; padding: 12px 14px;
                 margin-bottom: 22px; font-size: 11px; line-height: 1.7; background: #fffdf0; }
  .declaration strong.title { display: block; margin-bottom: 4px; font-size: 12px; }

  /* Signature block */
  .sig-block { border: 1px solid #ccc; border-radius: 4px; padding: 16px;
               margin-bottom: 20px; page-break-inside: avoid; }
  .sig-block h3 { font-size: 11px; text-transform: uppercase; letter-spacing: .5px;
                  color: #1a56db; margin-bottom: 12px; border-bottom: 1px solid #ddd; padding-bottom: 6px; }
  .sig-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
  .sig-field { margin-bottom: 14px; }
  .sig-field label { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: .4px;
                     display: block; margin-bottom: 4px; }
  .sig-line { border-bottom: 1px solid #333; height: 28px; width: 100%; position: relative; }
  .sig-line.prefilled { border-bottom: 1px solid #9ca3af; }
  .sig-prefill { position: absolute; bottom: 4px; left: 0; font-size: 11px;
                 font-weight: 600; color: #1e40af; }
  .sig-large { height: 60px; }

  /* Footer */
  .footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid #ddd;
            display: flex; justify-content: space-between; font-size: 9px; color: #999; }

  /* Print */
  @media print {
    body { background: #fff; }
    .no-print { display: none !important; }
    .page { padding: 20px; }
  }
  .print-btn {
    position: fixed; top: 16px; right: 16px;
    background: #1a56db; color: #fff; border: none;
    padding: 9px 20px; border-radius: 6px; cursor: pointer;
    font-size: 12px; font-weight: bold; z-index: 999; text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
  }
  .back-btn {
    position: fixed; top: 16px; right: 150px;
    background: #6c757d; color: #fff; border: none;
    padding: 9px 16px; border-radius: 6px; cursor: pointer;
    font-size: 12px; font-weight: bold; z-index: 999; text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
  }
</style>
</head>
<body>

<a href="javascript:history.back()" class="back-btn no-print">&#8592; Back</a>
<button class="print-btn no-print" onclick="window.print()">&#128438; Print / Save PDF</button>

@php
  $statusMap = [
    'approved' => ['label' => 'Approved',            'badge' => 'badge-approved', 'row' => 'row-approved'],
    'rejected' => ['label' => 'Rejected',            'badge' => 'badge-rejected', 'row' => 'row-rejected'],
    'amended'  => ['label' => 'Amendment Requested', 'badge' => 'badge-amended',  'row' => 'row-amended'],
    'pending'  => ['label' => 'Pending',             'badge' => 'badge-pending',  'row' => 'row-pending'],
  ];

  $counts = $estimate->lineItems->groupBy('approval_status')->map->count();

  $approvedTotal = $estimate->lineItems
    ->where('approval_status', 'approved')
    ->sum('line_amount');

  $rejectedTotal = $estimate->lineItems
    ->where('approval_status', 'rejected')
    ->sum('line_amount');

  $amendedTotal = $estimate->lineItems
    ->where('approval_status', 'amended')
    ->sum('line_amount');

  $pendingTotal = $estimate->lineItems
    ->where('approval_status', 'pending')
    ->sum('line_amount');

  $hasPending  = ($counts['pending'] ?? 0) > 0;
  $hasRejected = ($counts['rejected'] ?? 0) > 0;
  $hasAmended  = ($counts['amended'] ?? 0) > 0;

  // Tax ratio to scale agreed total
  $grossTotal = (float) $estimate->grand_total;
  $netTotal   = (float) $estimate->subtotal;
  $taxRatio   = $netTotal > 0 ? $grossTotal / $netTotal : 1;
  $agreedGross = round($approvedTotal * $taxRatio, 2);
@endphp

<div class="page">

  {{-- Header --}}
  <div class="header">
    <div>
      <div class="company-name">{{ $company->company_name }}</div>
      @if(!empty($company->address))
        <div class="company-sub">{{ $company->address }}</div>
      @endif
    </div>
    <div class="doc-title">
      <h1>Repair Estimate<br>Customer Decision Record</h1>
      <div class="ref">Ref: {{ $estimate->estimate_no }}</div>
      <div class="ref" style="color:#888;">{{ now()->format('d M Y') }}</div>
    </div>
  </div>

  {{-- Estimate Details --}}
  <table class="info-table">
    <tr>
      <td class="lbl">Estimate No.</td>
      <td class="val" style="font-family:monospace">{{ $estimate->estimate_no }}</td>
      <td class="lbl">Issue Date</td>
      <td class="val">{{ $estimate->estimate_date->format('d M Y') }}</td>
    </tr>
    <tr>
      <td class="lbl">Container No.</td>
      <td class="val" style="font-family:monospace">{{ $estimate->container_no }}</td>
      <td class="lbl">Valid Until</td>
      <td class="val">{{ $estimate->valid_until->format('d M Y') }}</td>
    </tr>
    <tr>
      <td class="lbl">Customer</td>
      <td class="val">{{ $estimate->customer->name ?? '—' }}</td>
      <td class="lbl">Currency</td>
      <td class="val">{{ $estimate->currency }}</td>
    </tr>
  </table>

  {{-- Decision Summary --}}
  <div class="summary-bar">
    @if(($counts['approved'] ?? 0) > 0)
      <span class="pill pill-approved">&#10003; {{ $counts['approved'] }} Approved</span>
    @endif
    @if(($counts['rejected'] ?? 0) > 0)
      <span class="pill pill-rejected">&#10007; {{ $counts['rejected'] }} Rejected</span>
    @endif
    @if(($counts['amended'] ?? 0) > 0)
      <span class="pill pill-amended">&#9654; {{ $counts['amended'] }} Amendment Requested</span>
    @endif
    @if(($counts['pending'] ?? 0) > 0)
      <span class="pill pill-pending">&#8230; {{ $counts['pending'] }} Pending Decision</span>
    @endif
  </div>

  {{-- Pending warning --}}
  @if($hasPending)
  <div class="pending-notice no-print">
    &#9888; Some line items have not yet received a decision. This document may be incomplete.
  </div>
  @endif

  {{-- Line Items --}}
  <table class="items-table">
    <thead>
      <tr>
        <th style="width:4%">#</th>
        <th style="width:30%">Description</th>
        <th style="width:16%">Repair Type</th>
        <th class="r" style="width:7%">Qty</th>
        <th class="r" style="width:13%">Unit Price</th>
        <th class="r" style="width:14%">Amount</th>
        <th style="width:16%">Decision</th>
      </tr>
    </thead>
    <tbody>
      @foreach($estimate->lineItems as $i => $line)
      @php
        $status  = $line->approval_status ?? 'pending';
        $meta    = $statusMap[$status] ?? $statusMap['pending'];
        $action  = $lineActions[$line->id] ?? null;
      @endphp
      <tr class="{{ $meta['row'] }}">
        <td>{{ $i + 1 }}</td>
        <td>
          <span class="{{ $status === 'rejected' ? 'strike' : '' }}">{{ $line->component }}</span>
          @if($action && $action->notes)
            <div class="line-note">Note: {{ $action->notes }}</div>
          @endif
        </td>
        <td class="{{ $status === 'rejected' ? 'strike' : '' }}">
          {{ ucfirst(str_replace('_', ' ', $line->repair_type ?? '')) }}
        </td>
        <td class="r {{ $status === 'rejected' ? 'strike' : '' }}">{{ number_format($line->qty, 2) }}</td>
        <td class="r {{ $status === 'rejected' ? 'strike' : '' }}">{{ number_format($line->unit_price, 2) }}</td>
        <td class="r {{ $status === 'rejected' ? 'strike' : '' }}">
          {{ $estimate->currency }} {{ number_format($line->line_amount, 2) }}
        </td>
        <td>
          <span class="badge {{ $meta['badge'] }}">{{ $meta['label'] }}</span>
        </td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      {{-- Approved sub-total --}}
      @if($approvedTotal > 0)
      <tr class="subtotal-row">
        <td colspan="5" style="text-align:right;">Approved Lines Subtotal</td>
        <td class="r">{{ $estimate->currency }} {{ number_format($approvedTotal, 2) }}</td>
        <td></td>
      </tr>
      @endif

      {{-- Tax rows (proportional to approved lines) --}}
      @if($approvedTotal > 0 && $estimate->sscl_amount > 0)
      @php $ssclApproved = $netTotal > 0 ? round(($approvedTotal / $netTotal) * $estimate->sscl_amount, 2) : 0; @endphp
      <tr class="subtotal-row">
        <td colspan="5" style="text-align:right;">SSCL</td>
        <td class="r">{{ $estimate->currency }} {{ number_format($ssclApproved, 2) }}</td>
        <td></td>
      </tr>
      @endif
      @if($approvedTotal > 0 && $estimate->vat_amount > 0)
      @php $vatApproved = $netTotal > 0 ? round(($approvedTotal / $netTotal) * $estimate->vat_amount, 2) : 0; @endphp
      <tr class="subtotal-row">
        <td colspan="5" style="text-align:right;">VAT</td>
        <td class="r">{{ $estimate->currency }} {{ number_format($vatApproved, 2) }}</td>
        <td></td>
      </tr>
      @endif

      {{-- Agreed total (approved only) --}}
      <tr class="agreed-row">
        <td colspan="5" style="text-align:right;">AGREED TOTAL (Approved Lines)</td>
        <td class="r">{{ $estimate->currency }} {{ number_format($agreedGross, 2) }}</td>
        <td></td>
      </tr>

      {{-- Rejected --}}
      @if($rejectedTotal > 0)
      <tr class="rejected-row">
        <td colspan="5" style="text-align:right;">Rejected Lines (excluded)</td>
        <td class="r">&#8212; {{ $estimate->currency }} {{ number_format($rejectedTotal, 2) }}</td>
        <td></td>
      </tr>
      @endif

      {{-- Amendment Requested --}}
      @if($amendedTotal > 0)
      <tr class="amended-row">
        <td colspan="5" style="text-align:right;">Amendment Requested (pending resolution)</td>
        <td class="r">{{ $estimate->currency }} {{ number_format($amendedTotal, 2) }}</td>
        <td></td>
      </tr>
      @endif

      {{-- Pending --}}
      @if($pendingTotal > 0)
      <tr class="pending-row">
        <td colspan="5" style="text-align:right;">Pending Decision</td>
        <td class="r">{{ $estimate->currency }} {{ number_format($pendingTotal, 2) }}</td>
        <td></td>
      </tr>
      @endif
    </tfoot>
  </table>

  {{-- Declaration --}}
  <div class="declaration">
    <strong class="title">Declaration</strong>
    I, the undersigned, being duly authorised on behalf of
    <strong>{{ $estimate->customer->name ?? '____________________________' }}</strong>,
    hereby confirm that I have reviewed the repair estimate
    (Ref: <strong>{{ $estimate->estimate_no }}</strong>) for container
    <strong>{{ $estimate->container_no }}</strong>
    and that the decisions recorded above against each line item accurately reflect my authorised instructions.
    @if(!$hasRejected && !$hasAmended && !$hasPending)
      I approve all repair works as listed, with an agreed total of
      <strong>{{ $estimate->currency }} {{ number_format($agreedGross, 2) }}</strong>.
    @else
      The agreed amount for approved line items is
      <strong>{{ $estimate->currency }} {{ number_format($agreedGross, 2) }}</strong>.
      @if($hasRejected) Rejected line items are not authorised for repair. @endif
      @if($hasAmended) Lines marked as "Amendment Requested" are subject to further discussion with the depot before work proceeds. @endif
    @endif
    I confirm I am authorised to make this decision on behalf of my organisation.
  </div>

  {{-- Signature Block --}}
  <div class="sig-block">
    <h3>Authorised Signatory</h3>
    <div class="sig-grid">
      <div>
        <div class="sig-field">
          <label>Full Name</label>
          <div class="sig-line{{ $latestApprover?->approver_name ? ' prefilled' : '' }}">
            @if($latestApprover?->approver_name)
              <span class="sig-prefill">{{ $latestApprover->approver_name }}</span>
            @endif
          </div>
        </div>
        <div class="sig-field">
          <label>Designation / Title</label>
          <div class="sig-line{{ $latestApprover?->approver_designation ? ' prefilled' : '' }}">
            @if($latestApprover?->approver_designation)
              <span class="sig-prefill">{{ $latestApprover->approver_designation }}</span>
            @endif
          </div>
        </div>
        <div class="sig-field">
          <label>Date</label>
          <div class="sig-line"></div>
        </div>
      </div>
      <div>
        <div class="sig-field">
          <label>Signature</label>
          <div class="sig-line" style="height:60px;"></div>
        </div>
        <div class="sig-field">
          <label>Company Stamp (if applicable)</label>
          <div class="sig-line" style="height:60px;"></div>
        </div>
      </div>
    </div>
  </div>

  {{-- Footer --}}
  <div class="footer">
    <span>{{ $company->company_name }}@if(!empty($company->email)) &nbsp;·&nbsp; {{ $company->email }}@endif</span>
    <span>Generated {{ now()->format('d M Y H:i') }} &nbsp;·&nbsp; {{ $estimate->estimate_no }}</span>
  </div>

</div>

<script>
  window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 400);
  });
</script>

</body>
</html>
