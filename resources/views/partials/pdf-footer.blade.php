{{--
    Shared PDF footer, fixed to the bottom of every page (dompdf renders
    position:fixed elements on each page). Shows the company, generation time
    and the software-provider copyright.

    Give the page's content container a bottom padding of ~46px so the last
    page's content never overlaps this footer.

    Params:
      company (optional) CompanySetting instance; defaults to current()
--}}
@php $__co = $company ?? ($companySetting ?? \App\Models\CompanySetting::current()); @endphp
<div style="position:fixed; bottom:0; left:0; right:0; height:28px; padding:6px 30px;
            border-top:1px solid #e0e0e0; background:#fff; color:#999; font-size:8px;">
    <table style="width:100%; border-collapse:collapse;"><tr>
        <td style="border:none; text-align:left; color:#999;">
            Computer-generated document &middot; {{ $__co->company_name }} &middot; Generated {{ now()->format('d M Y H:i') }}
        </td>
        <td style="border:none; text-align:right; color:#999;">
            &copy; {{ date('Y') }} {{ $__co->software_provider ?? 'CYM Software' }}
        </td>
    </tr></table>
</div>
