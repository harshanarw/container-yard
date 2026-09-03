{{--
    CSV / Excel buttons for a report screen.

    Params:
      route  (required) the export route name, e.g. 'finance.gl.trial-balance.export'
      query  (optional) query to carry; defaults to the current request's, so the
             file matches whatever the operator has the screen filtered to

    Excel appears only when a spreadsheet writer is installed — asking
    TabularExport rather than assuming, so the button is never offered where it
    would fall back to CSV and surprise someone.

    No `@php` here on purpose. Blade lifts raw PHP out before it compiles
    anything else and pairs the first opening token with the first closing one,
    so a one-line directive in a partial that also carried a block would fold the
    file in half. Expressions inline instead.
--}}
<div class="d-inline-flex gap-2 no-print">
    <a href="{{ route($route, $query ?? request()->query()) }}"
       class="btn btn-outline-success btn-sm" title="Download as CSV">
        <i class="bi bi-filetype-csv me-1"></i>CSV
    </a>
    @if(\App\Support\Export\TabularExport::supports('xlsx'))
    <a href="{{ route($route, array_merge($query ?? request()->query(), ['format' => 'xlsx'])) }}"
       class="btn btn-outline-success btn-sm" title="Download as Excel">
        <i class="bi bi-file-earmark-excel me-1"></i>Excel
    </a>
    @endif
</div>
