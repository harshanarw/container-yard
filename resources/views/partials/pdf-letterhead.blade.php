{{--
    Shared invoice/document PDF letterhead: company logo + details, a coloured
    rule, and a centred document-title band. Self-contained (inline styles) so it
    works inside any PDF template regardless of its own CSS.

    Params:
      title       (required) e.g. 'STORAGE INVOICE'
      docNo       (optional) document number shown under the title
      statusLabel (optional) e.g. 'DRAFT' — rendered as a badge
      accent      (optional) theme colour, default blue #1a56db
      company     (optional) CompanySetting instance; defaults to current()
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

    $__statusMap = [
        'draft'          => ['#f1f5f9', '#64748b'],
        'issued'         => ['#e0f2fe', '#0284c7'],
        'sent'           => ['#e0f2fe', '#0284c7'],
        'paid'           => ['#dcfce7', '#16a34a'],
        'approved'       => ['#dcfce7', '#16a34a'],
        'completed'      => ['#e2e8f0', '#334155'],
        'partially_paid' => ['#fef9c3', '#a16207'],
        'overdue'        => ['#fee2e2', '#dc2626'],
        'cancelled'      => ['#fee2e2', '#dc2626'],
        'rejected'       => ['#fee2e2', '#dc2626'],
        'voided'         => ['#fee2e2', '#dc2626'],
    ];
    $__sk = strtolower(str_replace(' ', '_', $statusLabel ?? ''));
    [$__sbg, $__scol] = $__statusMap[$__sk] ?? ['#f1f5f9', '#64748b'];
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
<div style="border-bottom:2px solid {{ $__accent }}; margin:10px 0;"></div>
<div style="text-align:center; margin:4px 0 12px;">
    <div style="color:{{ $__accent }}; font-size:22px; font-weight:bold; letter-spacing:1px;">{{ $title }}</div>
    @if(!empty($docNo))
        <div style="font-weight:bold; font-size:13px; color:#1a1a2e; margin-top:2px; font-family:'DejaVu Sans Mono','Courier New',monospace;">{{ $docNo }}</div>
    @endif
    @if(!empty($statusLabel))
        <span style="display:inline-block; padding:2px 12px; border-radius:4px; font-size:9px; font-weight:bold; text-transform:uppercase; margin-top:4px; background:{{ $__sbg }}; color:{{ $__scol }};">{{ strtoupper($statusLabel) }}</span>
    @endif
</div>
