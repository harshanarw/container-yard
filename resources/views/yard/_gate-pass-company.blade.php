{{--
    Gate-pass letterhead company block: logo beside the company details, matching
    the arrangement partials.pdf-letterhead gives the invoices and receipts.

    Deliberately NOT that partial: the gate pass prints from the browser (it uses
    logo_url, not a base64 embed), renders in pure black with no accent colour or
    bordered title box, needs a slot for the LADEN/EMPTY badge, and comes in three
    sizes. Sharing the block across the six header sites here keeps the formats
    from drifting apart.

    Params:
      companySetting (required) CompanySetting instance
      compact        (optional) true for the half / custom-half formats
--}}
@php
    $__co      = $companySetting ?? \App\Models\CompanySetting::current();
    $__compact = $compact ?? false;
@endphp
<div class="gp-co-row">
    @if($__co?->logo_url)
    <img src="{{ $__co->logo_url }}" alt="Logo"
         class="gp-company-logo{{ $__compact ? ' gp-company-logo-sm' : '' }}">
    @endif
    <div class="gp-co-text">
        <div class="gp-company-name{{ $__compact ? ' gp-company-name-sm' : '' }}">
            {{ $__co?->company_name ?? 'Container Yard Management' }}
        </div>
        <div class="gp-address{{ $__compact ? ' gp-address-sm' : '' }}">
            <div class="gp-address-line">{{ $__co?->address }}{{ $__co?->city ? ', ' . $__co->city : '' }}</div>
            @if($__co?->telephone || $__co?->email)
            <div class="gp-address-line">
                @if($__co?->telephone)Tel: {{ $__co->telephone }}@endif
                @if($__co?->telephone && $__co?->email) &nbsp;&middot;&nbsp; @endif
                @if($__co?->email){{ $__co->email }}@endif
            </div>
            @endif
            @if($__co?->vat_number || $__co?->tin_number)
            <div class="gp-address-line">
                @if($__co?->vat_number)VAT: {{ $__co->vat_number }}@endif
                @if($__co?->vat_number && $__co?->tin_number) &nbsp;&middot;&nbsp; @endif
                @if($__co?->tin_number)TIN: {{ $__co->tin_number }}@endif
            </div>
            @endif
        </div>
    </div>
</div>
