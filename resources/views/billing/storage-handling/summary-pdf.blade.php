<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_no }}</title>
    <style>
        /* The reset must NOT use `*`. DomPDF applies the universal selector to
           the @page box as well, so `* { margin: 0 }` silently zeroes the page
           margins — which is what made an earlier version conclude @page was
           unsupported and reach for a spacer-table instead. It is supported;
           it was being overridden. Reset by element name and leave @page alone. */
        * { box-sizing: border-box; }
        body, div, table, thead, tbody, tfoot, tr, th, td,
        p, h1, h2, h3, h4, ul, ol, li { margin: 0; padding: 0; }
        body { font-family: 'Courier New', Courier, monospace; font-size: 10px; color: #222; background: #fff; }

        /* Same page scaffolding as the detailed PDF. */
        /* position:fixed makes DomPDF repeat the letterhead and footer on every
           page; these margins reserve the band each one sits in. The content
           must not be wrapped in a table: DomPDF splits a table between rows
           but never inside a single cell, so a body taller than one page
           produced blank pages with everything crammed onto the last. */
        @page { margin: 148px 24px 44px; }
        /* DomPDF positions a fixed element against the content box, which the
           @page margins have already inset — so `top: 0` would park the
           letterhead 148px down the page. The negative offset lifts it back
           into the band those margins reserved for it. */
        .pdf-fixed-header { position: fixed; top: -148px; left: 0; right: 0; padding: 14px 24px 0; background: #fff; }
        /* Same correction for the shared footer partial, which sets bottom:0
           inline for the templates that keep zero @page margins. */
        .pdf-running-footer { bottom: -44px !important; }

        .info-box  { border: 1px solid #dee2e6; border-radius: 5px; padding: 8px 10px; text-transform: uppercase; }
        .info-box h3 {
            font-size: 8px; text-transform: uppercase; letter-spacing: .04em;
            color: #888; margin-bottom: 6px; border-bottom: 1px solid #eee; padding-bottom: 3px;
        }
        .lbl { color: #666; width: 44%; vertical-align: top; padding: 1px 4px 1px 0; }
        .val { font-weight: bold; text-align: right; vertical-align: top; padding: 1px 0; }

        table.t { width: 100%; border-collapse: collapse; font-size: 9px; }
        table.t thead th {
            background: #f1f3f5; font-weight: 700; padding: 5px 6px;
            border: 1px solid #dee2e6; font-size: 8px; text-transform: uppercase; letter-spacing: .03em;
        }
        table.t tbody td { padding: 4px 6px; border: 1px solid #dee2e6; }
        .r { text-align: right; }
        .c { text-align: center; }

        /* One line, one number. Every other row the detailed format carries is
           deliberately absent — the breakdown is the thing this format exists
           not to print, and a CSS comment is rendered output, so it must not
           name those rows either. */
        table.totals { width: 300px; margin-left: auto; margin-top: 14px; border-collapse: collapse; font-size: 10px; }
        table.totals td { padding: 5px 8px; border: none; }
        table.totals .grand td {
            font-weight: bold; font-size: 13px; color: #0d6efd;
            border-top: 2px solid #0d6efd; border-bottom: 2px solid #0d6efd;
        }

        .note {
            margin-top: 14px; padding: 7px 10px; border: 1px solid #dee2e6; border-radius: 4px;
            font-size: 8.5px; color: #555; line-height: 1.6;
        }
    </style>
</head>
<body>

@php
    $baseCur  = \App\Services\CurrencyService::defaultCurrency();
    $dispCur  = $invoice->invoice_currency ?? $baseCur;
    $dispRate = (float) ($invoice->exchange_rate ?? 1.0);
    $disp     = fn ($lkr) => $dispCur === $baseCur ? $lkr : round($lkr / $dispRate, 2);
    $fmtDisp  = fn ($lkr) => $dispCur . ' ' . number_format($disp($lkr), 2);

    // Anything the customer was actually charged for, or handled at no charge —
    // a deliberate zero-rate line still belongs on the document. Only rows with
    // nothing at all on them drop out, and those contribute nothing to the total,
    // so the column still adds up to it.
    $billable = $invoice->lines->filter(fn ($l) =>
        ($l->storage_chargeable_days ?? 0) > 0
        || $l->has_lift_off
        || $l->has_lift_on
        || ($l->line_grand_total ?? 0) > 0
    )->values();

    /**
     * What was charged for, and over what period — never how much per unit and
     * never a quantity. A day count printed beside an amount would hand the
     * daily rate straight back, which is exactly what this format exists to
     * avoid.
     */
    $describe = function ($l) {
        $hasStorage  = ($l->storage_chargeable_days ?? 0) > 0 || ($l->storage_subtotal ?? 0) > 0;
        $hasHandling = (bool) $l->has_lift_off || (bool) $l->has_lift_on;

        // Emitted unescaped (the date range carries entities), so the ampersand
        // is written as one rather than left bare.
        $what = match (true) {
            $hasStorage && $hasHandling => 'Storage &amp; handling',
            $hasStorage                 => 'Storage',
            $hasHandling                => 'Handling',
            default                     => 'Services rendered',
        };

        if (! $hasStorage || ! $l->storage_from || ! $l->storage_to) {
            return $what;
        }

        $from = \Carbon\Carbon::parse($l->storage_from);
        $to   = \Carbon\Carbon::parse($l->storage_to);

        // "01–31 Mar 2026" inside one month, "25 Feb – 03 Mar 2026" across two.
        $range = $from->isSameMonth($to)
            ? $from->format('d') . '&ndash;' . $to->format('d M Y')
            : $from->format('d M') . ' &ndash; ' . $to->format('d M Y');

        return $what . ', ' . $range;
    };
@endphp

<div class="pdf-fixed-header">
    {{--
        Always "INVOICE", never "TAX INVOICE", whatever the invoice type says.
        A tax invoice must show the tax charged — that is what lets the customer
        reclaim it — so a document that hides VAT must not carry that title. The
        IRD print remains the statutory document; this supplements it.
    --}}
    @include('partials.pdf-letterhead', [
        'title'     => 'INVOICE',
        'accent'    => '#0d6efd',
        'verifyUrl' => \Illuminate\Support\Facades\URL::signedRoute('documents.verify', ['type' => 'storage-handling', 'id' => $invoice->id]),
    ])
</div>
@include('partials.pdf-footer')


{{-- ── Bill To (left) + Invoice details (right) ── --}}
<table style="width:100%; margin-bottom:14px;">
    <tr>
        <td style="width:50%; vertical-align:top; padding-right:8px;">
            <div class="info-box">
                <h3>Bill To</h3>
                <div style="font-weight:bold; font-size:11px; margin-bottom:4px;">
                    {{ $invoice->billingParty->name ?? $invoice->shippingLine->name ?? '—' }}
                </div>
                @php $party = $invoice->billingParty ?? $invoice->shippingLine; @endphp
                @if($party)
                    @if($party->address)
                    <div style="color:#555; margin-top:2px;">{{ $party->address }}</div>
                    @endif
                    @if($party->email)
                    <div style="color:#555;">{{ $party->email }}</div>
                    @endif
                @endif
            </div>
        </td>
        <td style="width:50%; vertical-align:top; padding-left:8px;">
            <div class="info-box">
                <h3>Invoice Details</h3>
                {{-- No IRD number here, deliberately: it belongs to the statutory
                     document, and printing it on a copy that hides the tax would
                     make this look like that document. --}}
                <table style="width:100%;">
                    <tr><td class="lbl">Invoice No</td><td class="val" style="font-family:monospace;">{{ $invoice->invoice_no }}</td></tr>
                    <tr><td class="lbl">Invoice Date</td><td class="val">{{ $invoice->invoice_date->format('d M Y') }}</td></tr>
                    @if($invoice->due_date)
                    <tr><td class="lbl">Payment Due</td><td class="val">{{ $invoice->due_date->format('d M Y') }}</td></tr>
                    @endif
                    <tr>
                        <td class="lbl">Billing Period</td>
                        <td class="val">{{ $invoice->billing_period_from->format('d M Y') }} &ndash; {{ $invoice->billing_period_to->format('d M Y') }}</td>
                    </tr>
                    <tr><td class="lbl">Currency</td><td class="val" style="color:#0d6efd;">{{ $dispCur }}</td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

{{-- ── Charges: one row per container, one tax-inclusive amount ── --}}
<table class="t">
    <thead>
        <tr>
            <th style="width:5%">#</th>
            <th style="width:20%">Container No.</th>
            <th style="width:8%" class="c">Size</th>
            <th style="width:44%">Description</th>
            <th style="width:23%" class="r">Amount ({{ $dispCur }})</th>
        </tr>
    </thead>
    <tbody>
        @forelse($billable as $i => $line)
        <tr>
            <td class="c">{{ $i + 1 }}</td>
            <td style="font-family:monospace; font-weight:bold;">{{ $line->container_no }}</td>
            <td class="c">{{ $line->container_size ? $line->container_size . "'" : '—' }}</td>
            <td>{!! $describe($line) !!}</td>
            <td class="r" style="font-weight:bold;">{{ $fmtDisp($line->line_grand_total) }}</td>
        </tr>
        @empty
        <tr><td colspan="5" class="c" style="color:#888; padding:10px;">No chargeable items for this period.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- ── Total: the single figure, with nothing broken out of it ── --}}
<table class="totals">
    <tr class="grand">
        <td>TOTAL</td>
        <td class="r">{{ $fmtDisp($invoice->total_amount) }}</td>
    </tr>
</table>

@if($invoice->notes)
<div class="note"><strong>Notes:</strong> {{ $invoice->notes }}</div>
@endif

<div class="note">
    All amounts shown are inclusive of applicable taxes.<br>
    A tax invoice is available on request.
</div>

</body>
</html>
