<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approval Confirmation — {{ $estimate->estimate_no }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #222; background: #fff; }
  .page { max-width: 800px; margin: 0 auto; padding: 36px 40px; }

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
  .info-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
  .info-table td { padding: 5px 10px; border: 1px solid #ddd; font-size: 11px; }
  .info-table td.lbl { background: #f4f6fb; font-weight: bold; color: #555; width: 22%; }
  .info-table td.val { font-weight: 600; }

  /* Line items */
  .items-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 11px; }
  .items-table thead th { background: #1a56db; color: #fff; padding: 6px 8px; text-align: left; }
  .items-table thead th.r { text-align: right; }
  .items-table tbody tr:nth-child(even) { background: #f8f9fa; }
  .items-table tbody td { padding: 5px 8px; border-bottom: 1px solid #eee; }
  .items-table tbody td.r { text-align: right; }
  .items-table tfoot td { padding: 5px 8px; font-size: 11px; }
  .items-table tfoot .total-row td { font-weight: bold; font-size: 12px;
            background: #e8f0fe; border-top: 2px solid #1a56db; }

  /* Declaration */
  .declaration { border: 1px solid #bbb; border-radius: 4px; padding: 12px 14px;
                 margin-bottom: 22px; font-size: 11px; line-height: 1.7; background: #fffdf0; }
  .declaration strong { display: block; margin-bottom: 4px; font-size: 12px; }

  /* Signature block */
  .sig-block { border: 1px solid #ccc; border-radius: 4px; padding: 16px;
               margin-bottom: 20px; page-break-inside: avoid; }
  .sig-block h3 { font-size: 11px; text-transform: uppercase; letter-spacing: .5px;
                  color: #1a56db; margin-bottom: 12px; border-bottom: 1px solid #ddd; padding-bottom: 6px; }
  .sig-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
  .sig-field { margin-bottom: 14px; }
  .sig-field label { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: .4px;
                     display: block; margin-bottom: 4px; }
  .sig-line { border-bottom: 1px solid #333; height: 28px; width: 100%; }
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
      <h1>Repair Estimate<br>Approval Confirmation</h1>
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

  {{-- Line Items --}}
  <table class="items-table">
    <thead>
      <tr>
        <th style="width:4%">#</th>
        <th style="width:35%">Description</th>
        <th style="width:20%">Repair Type</th>
        <th class="r" style="width:8%">Qty</th>
        <th class="r" style="width:14%">Unit Price</th>
        <th class="r" style="width:19%">Amount</th>
      </tr>
    </thead>
    <tbody>
      @foreach($estimate->lineItems as $i => $line)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $line->component }}</td>
        <td>{{ ucfirst(str_replace('_', ' ', $line->repair_type ?? '')) }}</td>
        <td class="r">{{ number_format($line->qty, 2) }}</td>
        <td class="r">{{ number_format($line->unit_price, 2) }}</td>
        <td class="r">{{ $estimate->currency }} {{ number_format($line->line_amount, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr>
        <td colspan="5" style="text-align:right;color:#555;">Subtotal</td>
        <td class="r">{{ $estimate->currency }} {{ number_format($estimate->subtotal, 2) }}</td>
      </tr>
      @if($estimate->sscl_amount > 0)
      <tr>
        <td colspan="5" style="text-align:right;color:#555;">SSCL</td>
        <td class="r">{{ $estimate->currency }} {{ number_format($estimate->sscl_amount, 2) }}</td>
      </tr>
      @endif
      @if($estimate->vat_amount > 0)
      <tr>
        <td colspan="5" style="text-align:right;color:#555;">VAT</td>
        <td class="r">{{ $estimate->currency }} {{ number_format($estimate->vat_amount, 2) }}</td>
      </tr>
      @endif
      <tr class="total-row">
        <td colspan="5" style="text-align:right;">GRAND TOTAL</td>
        <td class="r">{{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}</td>
      </tr>
    </tfoot>
  </table>

  {{-- Declaration --}}
  <div class="declaration">
    <strong>Declaration of Approval</strong>
    I, the undersigned, being duly authorised on behalf of
    <strong>{{ $estimate->customer->name ?? '____________________________' }}</strong>,
    hereby confirm that I have reviewed the above repair estimate
    (Ref: <strong>{{ $estimate->estimate_no }}</strong>) for container
    <strong>{{ $estimate->container_no }}</strong>
    with a grand total of
    <strong>{{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}</strong>,
    and I approve the repair works as detailed above.
    I confirm I am authorised to make this approval on behalf of my organisation.
  </div>

  {{-- Signature Block --}}
  <div class="sig-block">
    <h3>Authorised Signatory</h3>
    <div class="sig-grid">
      <div>
        <div class="sig-field">
          <label>Full Name</label>
          <div class="sig-line"></div>
        </div>
        <div class="sig-field">
          <label>Designation / Title</label>
          <div class="sig-line"></div>
        </div>
        <div class="sig-field">
          <label>Date</label>
          <div class="sig-line"></div>
        </div>
      </div>
      <div>
        <div class="sig-field">
          <label>Signature</label>
          <div class="sig-line sig-large"></div>
        </div>
        <div class="sig-field">
          <label>Company Stamp (if applicable)</label>
          <div class="sig-line sig-large"></div>
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
