{{--
    Shared running PDF footer. Must be a DIRECT child of <body> and paired with a
    matching @page bottom margin so dompdf repeats it at the bottom of EVERY page
    without overlapping the flowing content. Shows company, generation time, the
    software-provider copyright and a "Page X of Y" counter.

    Params:
      company (optional) CompanySetting instance; defaults to current()
--}}
@php $__co = $company ?? ($companySetting ?? \App\Models\CompanySetting::current()); @endphp
<div class="pdf-running-footer"
     style="position:fixed; bottom:0; left:0; right:0; height:30px; padding:5px 32px 0;
            border-top:1px solid #e0e0e0; background:#fff; color:#999; font-size:8px;">
    <table style="width:100%; border-collapse:collapse;"><tr>
        <td style="border:none; padding:0; text-align:left; color:#999;">
            Computer-generated document &middot; {{ $__co->company_name }} &middot; Generated {{ now()->format('d M Y H:i') }}
        </td>
        <td style="border:none; padding:0; text-align:right; color:#999; white-space:nowrap;">
            &copy; {{ date('Y') }} {{ $__co->software_provider ?? 'CYM Software' }}
            &nbsp;&middot;&nbsp; <span class="pdf-pageof"></span>
        </td>
    </tr></table>
</div>
<style>.pdf-pageof:before { content: "Page " counter(page) " of " counter(pages); }</style>
