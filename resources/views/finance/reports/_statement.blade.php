@php
    // Expects: $title, $partyLabel, $routeName, $parties, $party, $data, $from, $to
    $base = $data['base'] ?? \App\Models\CompanySetting::baseCurrency();
    $money = fn ($n) => number_format((float) $n, 2);
@endphp

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i>{{ $title }}</h4>
        <p class="text-muted small mb-0">Statement of account — all amounts in {{ $base }} (base currency).</p>
    </div>
    @if($data)
    <div class="d-flex gap-2 flex-wrap d-print-none">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        {{-- Only once a party is chosen: the export requires one, so offering it
             on the empty filter screen would hand back a 404. --}}
        @include('partials.export-buttons', ['route' => $routeName . '.export'])
    </div>
    @endif
</div>

{{-- Filter --}}
<div class="card content-card mb-3 d-print-none">
    <div class="card-body py-2">
        <form method="GET" action="{{ route($routeName) }}" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small mb-1 fw-semibold">{{ $partyLabel }}</label>
                <select name="party_id" class="form-select form-select-sm s2-code" data-s2-sel="name" required>
                    <option value="">— Select {{ $partyLabel }} —</option>
                    @foreach($parties as $p)
                        <option value="{{ $p->id }}" data-code="{{ $p->code }}" data-name="{{ $p->name }}"
                            {{ (string) optional($party)->id === (string) $p->id ? 'selected' : '' }}>
                            {{ $p->code }} — {{ $p->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label small mb-1 fw-semibold">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}">
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label small mb-1 fw-semibold">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}">
            </div>
            <div class="col-md-1 col-12">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-search me-1"></i>Go</button>
            </div>
        </form>
    </div>
</div>

@if(!$party)
    <div class="card content-card"><div class="card-body text-center py-5 text-muted">
        <i class="bi bi-person-lines-fill fs-1 d-block mb-2 opacity-25"></i>
        Select a {{ strtolower($partyLabel) }} and a date range, then click <strong>Go</strong>.
    </div></div>
@elseif($data)
    {{-- Statement header --}}
    <div class="card content-card mb-3">
        <div class="card-body py-3 d-flex justify-content-between flex-wrap gap-2">
            <div>
                <div class="fw-bold fs-6">{{ $party->name }}</div>
                <div class="text-muted small">{{ $party->code }}</div>
            </div>
            <div class="text-md-end small text-muted">
                <div>Period: {{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</div>
                <div>Currency: {{ $base }} (base)</div>
            </div>
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-2 mb-3">
        <div class="col-md-3 col-6"><div class="card content-card text-center py-2">
            <div class="text-muted small">Opening Balance</div>
            <div class="fw-bold font-monospace">{{ $base }} {{ $money($data['opening']) }}</div>
        </div></div>
        <div class="col-md-3 col-6"><div class="card content-card text-center py-2">
            <div class="text-muted small">Charges</div>
            <div class="fw-bold font-monospace">{{ $base }} {{ $money($data['debit_total']) }}</div>
        </div></div>
        <div class="col-md-3 col-6"><div class="card content-card text-center py-2">
            <div class="text-muted small">Settlements</div>
            <div class="fw-bold font-monospace">{{ $base }} {{ $money($data['credit_total']) }}</div>
        </div></div>
        <div class="col-md-3 col-6"><div class="card content-card text-center py-2 {{ $data['closing'] > 0 ? 'border-primary' : '' }}">
            <div class="text-muted small">Closing Balance</div>
            <div class="fw-bold font-monospace {{ $data['closing'] > 0 ? 'text-primary' : ($data['closing'] < 0 ? 'text-danger' : 'text-success') }}">{{ $base }} {{ $money($data['closing']) }}</div>
        </div></div>
    </div>

    {{-- Ledger --}}
    <div class="card content-card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Ccy</th>
                        <th class="text-end">Doc Amount</th>
                        <th class="text-end">Debit ({{ $base }})</th>
                        <th class="text-end">Credit ({{ $base }})</th>
                        <th class="text-end pe-3">Balance ({{ $base }})</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-light">
                        <td colspan="7" class="fw-semibold">Opening Balance</td>
                        <td class="text-end pe-3 fw-semibold font-monospace">{{ $money($data['opening']) }}</td>
                    </tr>
                    @forelse($data['lines'] as $l)
                    <tr>
                        <td class="text-nowrap">{{ \Carbon\Carbon::parse($l['date'])->format('d M Y') }}</td>
                        <td>
                            <span class="badge {{ $l['debit'] > 0 ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success' }} border">{{ $l['type'] }}</span>
                            <span class="text-muted" style="font-size:.7rem;">{{ $l['sub'] }}</span>
                        </td>
                        <td class="font-monospace">
                            {{ $l['ref'] }}
                            @if(!empty($l['ird']))
                            <div class="text-muted" style="font-size:.68rem" title="IRD Tax Invoice No">
                                <i class="bi bi-receipt me-1"></i>{{ $l['ird'] }}
                            </div>
                            @endif
                        </td>
                        <td>{{ $l['currency'] }}</td>
                        <td class="text-end font-monospace text-muted">{{ $l['currency'] }} {{ $money($l['doc_amount']) }}</td>
                        <td class="text-end font-monospace">{{ $l['debit'] > 0 ? $money($l['debit']) : '' }}</td>
                        <td class="text-end font-monospace">{{ $l['credit'] > 0 ? $money($l['credit']) : '' }}</td>
                        <td class="text-end pe-3 font-monospace fw-semibold">{{ $money($l['balance']) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No transactions in this period.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td colspan="5" class="text-end">Period totals</td>
                        <td class="text-end font-monospace">{{ $money($data['debit_total']) }}</td>
                        <td class="text-end font-monospace">{{ $money($data['credit_total']) }}</td>
                        <td class="text-end pe-3 font-monospace">{{ $money($data['closing']) }}</td>
                    </tr>
                    <tr class="table-primary">
                        <td colspan="7" class="text-end">Closing Balance ({{ $base }})</td>
                        <td class="text-end pe-3 font-monospace fs-6">{{ $money($data['closing']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif
