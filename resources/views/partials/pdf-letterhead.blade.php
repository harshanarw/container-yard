{{--
    Shared invoice/document PDF letterhead: company logo + details, followed by a
    bordered, centred document-title box. Self-contained (inline styles) so it
    works inside any PDF template regardless of its own CSS.

    The document number and status are NOT rendered here — each template shows
    them as label/value pairs in its right-side meta section.

    ── How much room this needs ──────────────────────────────────────────────
    Rendered, this band measures **164px** (123.3pt): 14px of host padding, an
    87px letterhead row (driven by the 78px QR plus its caption, not the 54px
    logo), and a 52px title box including its 10px margins.

    A host that draws it with `position: fixed` must therefore reserve at least
    that much in `@page { margin-top }`, and the templates use **176px** for a
    ~12px gap. Reserve less and the band's white background paints over the top
    of the flowing content — at 148px it clipped the top border of the Bill To
    and Invoice Details boxes, and at 120px the title box printed straight
    through them.

    Measured by rendering, not estimated. If you change the QR size, the title
    box, or the address block, re-measure and raise the three templates
    together: they drifted apart once already, which is how the 120px was
    missed.

    Params:
      title      (required) e.g. 'STORAGE INVOICE'
      accent     (optional) theme colour, default blue #1a56db
      company    (optional) CompanySetting instance; defaults to current()
      verifyUrl  (optional) signed public URL — rendered as a "Scan to verify"
                 QR on the right (needs simplesoftwareio/simple-qrcode; if the
                 package is absent the QR is simply omitted)
--}}
@php
    $__co     = $company ?? ($companySetting ?? \App\Models\CompanySetting::current());
    $__accent = $accent ?? '#1a56db';

    $__logo = null;
    if (!empty($__co->logo_path)) {
        try {
            $__disk = \Illuminate\Support\Facades\Storage::disk('public');
            if ($__disk->exists($__co->logo_path)) {
                $__ext  = strtolower(pathinfo($__co->logo_path, PATHINFO_EXTENSION));
                $__mime = $__ext === 'jpg' ? 'image/jpeg' : 'image/' . ($__ext ?: 'png');
                $__logo = 'data:' . $__mime . ';base64,' . base64_encode($__disk->get($__co->logo_path));
            }
        } catch (\Throwable) {
            $__logo = null;
        }
    }

    $__qr = \App\Support\Qr::svgDataUri($verifyUrl ?? null, 120);
@endphp
<table style="width:100%; border-collapse:collapse; text-transform:uppercase;"><tr>
    @if($__logo)
    <td style="width:1%; white-space:nowrap; padding-right:12px; vertical-align:middle; border:none;">
        <img src="{{ $__logo }}" alt="{{ $__co->company_name }}" style="max-height:54px; max-width:170px; display:block;">
    </td>
    @endif
    <td style="vertical-align:middle; border:none; padding:0;">
        <div style="font-size:18px; font-weight:bold; color:{{ $__accent }};">{{ $__co->company_name }}</div>
        <div style="color:#666; font-size:10px; line-height:1.5; margin-top:2px;">
            {{ $__co->address }}{{ $__co->city ? ', '.$__co->city : '' }}<br>
            @if($__co->telephone)Tel: {{ $__co->telephone }} @endif @if($__co->email)· {{ $__co->email }}@endif<br>
            @if($__co->vat_number)VAT: {{ $__co->vat_number }}@endif @if($__co->tin_number) · TIN: {{ $__co->tin_number }}@endif
        </div>
    </td>
    @if($__qr)
    <td style="width:1%; white-space:nowrap; vertical-align:middle; text-align:right; padding-left:12px; border:none;">
        <img src="{{ $__qr }}" alt="Verify" style="width:78px; height:78px; display:block; margin-left:auto;">
        <div style="font-size:7px; color:#888; text-align:center; margin-top:1px;">Scan to verify</div>
    </td>
    @endif
</tr></table>

{{-- Bordered, centred document title --}}
<div style="border:2px solid {{ $__accent }}; border-radius:5px; padding:5px 14px; text-align:center; margin:10px 0; text-transform:uppercase;">
    <span style="color:{{ $__accent }}; font-size:15px; font-weight:bold; letter-spacing:1px;">{{ $title }}</span>
</div>
