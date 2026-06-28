{{--
    Shared invoice/document PDF letterhead: company logo + details, followed by a
    bordered, centred document-title box. Self-contained (inline styles) so it
    works inside any PDF template regardless of its own CSS.

    The document number and status are NOT rendered here — each template shows
    them as label/value pairs in its right-side meta section.

    Params:
      title    (required) e.g. 'STORAGE INVOICE'
      accent   (optional) theme colour, default blue #1a56db
      company  (optional) CompanySetting instance; defaults to current()
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
@endphp
<table style="width:100%; border-collapse:collapse;"><tr>
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
</tr></table>

{{-- Bordered, centred document title --}}
<div style="border:2px solid {{ $__accent }}; border-radius:6px; padding:8px 12px; text-align:center; margin:14px 0 12px;">
    <span style="color:{{ $__accent }}; font-size:20px; font-weight:bold; letter-spacing:1px;">{{ $title }}</span>
</div>
