<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estimate {{ $estimate->estimate_no }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #333; background: #fff; }
        .page { max-width: 800px; margin: 0 auto; padding: 30px; }

        /* Header */
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; border-bottom: 2px solid #1a56db; padding-bottom: 16px; }
        .company-name { font-size: 20px; font-weight: bold; color: #1a56db; }
        .company-sub  { font-size: 11px; color: #666; margin-top: 4px; }
        .estimate-title { text-align: right; }
        .estimate-title h1 { font-size: 22px; font-weight: bold; color: #1a56db; }
        .estimate-title .est-no { font-size: 14px; font-weight: bold; color: #333; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-secondary { background: #6c757d; color: #fff; }
        .badge-info      { background: #0dcaf0; color: #000; }
        .badge-success   { background: #198754; color: #fff; }
        .badge-danger    { background: #dc3545; color: #fff; }
        .badge-dark      { background: #212529; color: #fff; }

        /* Info Grid */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
        .info-box { border: 1px solid #dee2e6; border-radius: 6px; padding: 12px; }
        .info-box h3 { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #666; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 4px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 4px; }
        .info-label { color: #666; }
        .info-value { font-weight: bold; text-align: right; }

        /* Table */
        table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        thead th { background: #1a56db; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; }
        thead th.text-right { text-align: right; }
        tbody tr:nth-child(even) { background: #f8f9fa; }
        tbody td { padding: 7px 10px; border-bottom: 1px solid #eee; font-size: 11px; }
        tbody td.text-right { text-align: right; }
        tfoot td { padding: 6px 10px; font-size: 12px; }
        tfoot .total-row td { font-weight: bold; font-size: 13px; background: #e8f0fe; border-top: 2px solid #1a56db; }
        tfoot .subtax-row td { color: #555; }

        /* Sections */
        .section { margin-bottom: 20px; }
        .section-title { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #1a56db; font-weight: bold; margin-bottom: 6px; }
        .section-body { border: 1px solid #dee2e6; border-radius: 6px; padding: 10px; font-size: 11px; line-height: 1.6; white-space: pre-line; }

        /* Footer */
        .footer { margin-top: 30px; padding-top: 12px; border-top: 1px solid #dee2e6; display: flex; justify-content: space-between; font-size: 10px; color: #888; }

        /* Print */
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .page { padding: 15px; }
        }
        .print-btn {
            position: fixed; top: 20px; right: 20px;
            background: #1a56db; color: #fff; border: none;
            padding: 10px 20px; border-radius: 6px; cursor: pointer;
            font-size: 13px; font-weight: bold; z-index: 999;
        }
    </style>
</head>
<body>

<button class="print-btn no-print" onclick="window.print()">
    &#128438; Print / Save PDF
</button>

<div class="page">

    <!-- Header -->
    <div class="header">
        <div>
            <div class="company-name">Container Yard Management</div>
            <div class="company-sub">Repair Estimate</div>
        </div>
        <div class="estimate-title">
            <h1>REPAIR ESTIMATE</h1>
            <div class="est-no">{{ $estimate->estimate_no }}</div>
            <div style="margin-top:4px">
                @php
                    $badgeMap = ['draft'=>'secondary','sent'=>'info','approved'=>'success','rejected'=>'danger','completed'=>'dark'];
                @endphp
                <span class="badge badge-{{ $badgeMap[$estimate->status] ?? 'secondary' }}">
                    {{ strtoupper($estimate->status) }}
                </span>
            </div>
        </div>
    </div>

    <!-- Info Grid -->
    <div class="info-grid">
        <!-- Estimate Info -->
        <div class="info-box">
            <h3>Estimate Info</h3>
            <div class="info-row">
                <span class="info-label">Estimate No.</span>
                <span class="info-value">{{ $estimate->estimate_no }}</span>
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
                <span class="info-label">Currency</span>
                <span class="info-value">{{ $estimate->currency }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Priority</span>
                <span class="info-value">{{ ucfirst($estimate->priority) }}</span>
            </div>
            @if($estimate->inquiry)
            <div class="info-row">
                <span class="info-label">Inquiry Ref.</span>
                <span class="info-value">{{ $estimate->inquiry->inquiry_no }}</span>
            </div>
            @endif
        </div>

        <!-- Container & Customer -->
        <div class="info-box">
            <h3>Container & Customer</h3>
            <div class="info-row">
                <span class="info-label">Container No.</span>
                <span class="info-value">{{ $estimate->container_no }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Size / Type</span>
                <span class="info-value">{{ $estimate->size }}' {{ $estimate->type_code }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Customer</span>
                <span class="info-value">{{ $estimate->customer->name ?? '—' }}</span>
            </div>
            @if($estimate->customer?->contact_person)
            <div class="info-row">
                <span class="info-label">Contact</span>
                <span class="info-value">{{ $estimate->customer->contact_person }}</span>
            </div>
            @endif
            @if($estimate->customer?->email)
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value">{{ $estimate->customer->email }}</span>
            </div>
            @endif
        </div>
    </div>

    <!-- Line Items -->
    <div class="section">
        <div class="section-title">Repair Line Items</div>
        <table>
            <thead>
                <tr>
                    <th style="width:5%">#</th>
                    <th style="width:28%">Component / Location</th>
                    <th style="width:18%">Repair Type</th>
                    <th class="text-right" style="width:8%">Qty</th>
                    <th class="text-right" style="width:14%">Unit Price</th>
                    <th class="text-right" style="width:8%">Tax %</th>
                    <th class="text-right" style="width:19%">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($estimate->lineItems as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->component }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $item->repair_type)) }}</td>
                    <td class="text-right">{{ $item->qty }}</td>
                    <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">{{ $item->tax_percentage }}%</td>
                    <td class="text-right">{{ $estimate->currency }} {{ number_format($item->line_amount, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="subtax-row">
                    <td colspan="6" style="text-align:right; padding-right:10px">Subtotal:</td>
                    <td class="text-right">{{ $estimate->currency }} {{ number_format($estimate->subtotal, 2) }}</td>
                </tr>
                <tr class="subtax-row">
                    <td colspan="6" style="text-align:right; padding-right:10px">Tax ({{ $estimate->tax_percentage }}%):</td>
                    <td class="text-right">{{ $estimate->currency }} {{ number_format($estimate->tax_amount, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td colspan="6" style="text-align:right; padding-right:10px">GRAND TOTAL:</td>
                    <td class="text-right">{{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if($estimate->scope_of_work)
    <div class="section">
        <div class="section-title">Scope of Work</div>
        <div class="section-body">{{ $estimate->scope_of_work }}</div>
    </div>
    @endif

    @if($estimate->terms)
    <div class="section">
        <div class="section-title">Terms & Conditions</div>
        <div class="section-body">{{ $estimate->terms }}</div>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <div>
            Generated by Container Yard Management System
            &nbsp;·&nbsp; {{ now()->format('d M Y H:i') }}
        </div>
        <div>
            Prepared by: {{ $estimate->createdBy->name ?? '—' }}
        </div>
    </div>

</div>

<script>
    // Auto-trigger print when opened via PDF button
    window.addEventListener('load', function() {
        // Small delay to allow styles to render
        setTimeout(function() { window.print(); }, 400);
    });
</script>

</body>
</html>
