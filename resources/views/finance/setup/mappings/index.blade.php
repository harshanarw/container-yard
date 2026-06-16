@extends('layouts.app')

@section('title', 'Account Mappings')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item active">Account Mappings</li>
@endsection

@section('content')

<div class="page-header">
    <h4><i class="bi bi-arrow-left-right me-2 text-primary"></i>Account Mappings</h4>
    <p class="text-muted mb-0 small">Map charge codes, tax codes, and parties to their corresponding GL accounts for automatic posting</p>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@if($postingAccounts->isEmpty())
<div class="alert alert-warning d-flex gap-2 align-items-center">
    <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
    <div>No posting accounts found. <a href="{{ route('finance.setup.accounts.index') }}">Set up your Chart of Accounts</a> first.</div>
</div>
@endif

<div class="card content-card">
    <div class="card-header p-0">
        <ul class="nav nav-tabs card-header-tabs" id="mappingTabs">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-revenue">Revenue</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-expense">Expense</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-ar-ap">AR / AP Controls</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-tax">Tax Accounts</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-other">Advances & Other</button></li>
        </ul>
    </div>
    <div class="card-body tab-content p-0">

        {{-- REVENUE TAB --}}
        <div class="tab-pane fade show active p-3" id="tab-revenue">
            <p class="text-muted small mb-3">Map each charge code to the revenue (income) account to credit when invoiced.</p>
            @if($chargeCodes->isEmpty())
                <div class="text-muted small">No charge codes found.</div>
            @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th style="width:110px;">Code</th><th>Description</th><th style="width:120px;">Category</th><th style="width:300px;">Revenue Account</th><th class="text-end" style="width:80px;">Save</th></tr>
                    </thead>
                    <tbody>
                        @foreach($chargeCodes as $cc)
                        @php $currentAccId = $mapped['charge_revenue'][\App\Models\ChargeCode::class][$cc->id] ?? null; @endphp
                        <tr>
                            <td class="font-monospace fw-semibold small">{{ $cc->code }}</td>
                            <td class="small">{{ $cc->description }}</td>
                            <td><span class="badge bg-secondary-subtle text-secondary" style="font-size:.7rem;">{{ ucfirst($cc->category) }}</span></td>
                            <td>
                                <select class="form-select form-select-sm mapping-select"
                                        data-type="charge_revenue"
                                        data-source-type="{{ addslashes(\App\Models\ChargeCode::class) }}"
                                        data-source-id="{{ $cc->id }}">
                                    <option value="">— Not mapped —</option>
                                    @foreach($postingAccounts as $acc)
                                    <option value="{{ $acc->id }}" {{ $currentAccId == $acc->id ? 'selected' : '' }}>
                                        {{ $acc->code }} — {{ $acc->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 save-mapping-btn"
                                        data-type="charge_revenue"
                                        data-source-type="{{ addslashes(\App\Models\ChargeCode::class) }}"
                                        data-source-id="{{ $cc->id }}">
                                    <i class="bi bi-save2"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- EXPENSE TAB --}}
        <div class="tab-pane fade p-3" id="tab-expense">
            <p class="text-muted small mb-3">Map each charge code to the expense account for AP/cost postings.</p>
            @if($chargeCodes->isEmpty())
                <div class="text-muted small">No charge codes found.</div>
            @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th style="width:110px;">Code</th><th>Description</th><th style="width:120px;">Category</th><th style="width:300px;">Expense Account</th><th class="text-end" style="width:80px;">Save</th></tr>
                    </thead>
                    <tbody>
                        @foreach($chargeCodes as $cc)
                        @php $currentAccId = $mapped['charge_expense'][\App\Models\ChargeCode::class][$cc->id] ?? null; @endphp
                        <tr>
                            <td class="font-monospace fw-semibold small">{{ $cc->code }}</td>
                            <td class="small">{{ $cc->description }}</td>
                            <td><span class="badge bg-secondary-subtle text-secondary" style="font-size:.7rem;">{{ ucfirst($cc->category) }}</span></td>
                            <td>
                                <select class="form-select form-select-sm mapping-select"
                                        data-type="charge_expense"
                                        data-source-type="{{ addslashes(\App\Models\ChargeCode::class) }}"
                                        data-source-id="{{ $cc->id }}">
                                    <option value="">— Not mapped —</option>
                                    @foreach($postingAccounts as $acc)
                                    <option value="{{ $acc->id }}" {{ $currentAccId == $acc->id ? 'selected' : '' }}>
                                        {{ $acc->code }} — {{ $acc->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 save-mapping-btn"
                                        data-type="charge_expense"
                                        data-source-type="{{ addslashes(\App\Models\ChargeCode::class) }}"
                                        data-source-id="{{ $cc->id }}">
                                    <i class="bi bi-save2"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- AR/AP CONTROLS TAB --}}
        <div class="tab-pane fade p-3" id="tab-ar-ap">
            <p class="text-muted small mb-3">Define the default AR and AP control accounts. These are used when no customer-specific mapping exists.</p>
            @php
                $defaultArId = $mapped['customer_ar'][''][0] ?? null;
                $defaultApId = $mapped['supplier_ap'][''][0] ?? null;
            @endphp
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card border">
                        <div class="card-header bg-primary-subtle fw-semibold small">
                            <i class="bi bi-person-lines-fill me-2 text-primary"></i>Default AR Control Account
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">Accounts Receivable — debited when invoices are posted to customers.</p>
                            <select class="form-select form-select-sm mapping-select"
                                    data-type="customer_ar" data-source-type="" data-source-id="0">
                                <option value="">— Not mapped —</option>
                                @foreach($postingAccounts as $acc)
                                <option value="{{ $acc->id }}" {{ $defaultArId == $acc->id ? 'selected' : '' }}>
                                    {{ $acc->code }} — {{ $acc->name }}
                                </option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-sm btn-primary mt-2 save-mapping-btn"
                                    data-type="customer_ar" data-source-type="" data-source-id="0">
                                <i class="bi bi-save2 me-1"></i>Save AR Mapping
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border">
                        <div class="card-header bg-danger-subtle fw-semibold small">
                            <i class="bi bi-building me-2 text-danger"></i>Default AP Control Account
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">Accounts Payable — credited when supplier bills are posted.</p>
                            <select class="form-select form-select-sm mapping-select"
                                    data-type="supplier_ap" data-source-type="" data-source-id="0">
                                <option value="">— Not mapped —</option>
                                @foreach($postingAccounts as $acc)
                                <option value="{{ $acc->id }}" {{ $defaultApId == $acc->id ? 'selected' : '' }}>
                                    {{ $acc->code }} — {{ $acc->name }}
                                </option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-sm btn-danger mt-2 save-mapping-btn"
                                    data-type="supplier_ap" data-source-type="" data-source-id="0">
                                <i class="bi bi-save2 me-1"></i>Save AP Mapping
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- TAX TAB --}}
        <div class="tab-pane fade p-3" id="tab-tax">
            <p class="text-muted small mb-3">Map tax codes to their output (collected from customers) and input (paid to suppliers) liability/asset accounts.</p>
            @if($taxCodes->isEmpty())
                <div class="text-muted small">No tax codes found.</div>
            @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tax Code</th>
                            <th class="text-center" style="width:80px;">Rate</th>
                            <th style="width:280px;">Output Tax Payable (Sales)</th>
                            <th style="width:280px;">Input Tax Receivable (Purchase)</th>
                            <th class="text-end" style="width:80px;">Save</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($taxCodes as $tc)
                        @php
                            $outAccId = $mapped['tax_output'][\App\Models\TaxCode::class][$tc->id] ?? null;
                            $inAccId  = $mapped['tax_input'][\App\Models\TaxCode::class][$tc->id] ?? null;
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold small">{{ $tc->code }}</div>
                                <div class="text-muted" style="font-size:.75rem;">{{ $tc->description }}</div>
                            </td>
                            <td class="text-center small">
                                @if($tc->tax1_rate) {{ $tc->tax1_rate }}% @endif
                                @if($tc->tax2_rate) + {{ $tc->tax2_rate }}% @endif
                            </td>
                            <td>
                                <select class="form-select form-select-sm tax-select-output" data-tc-id="{{ $tc->id }}">
                                    <option value="">— Not mapped —</option>
                                    @foreach($postingAccounts as $acc)
                                    <option value="{{ $acc->id }}" {{ $outAccId == $acc->id ? 'selected' : '' }}>
                                        {{ $acc->code }} — {{ $acc->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm tax-select-input" data-tc-id="{{ $tc->id }}">
                                    <option value="">— Not mapped —</option>
                                    @foreach($postingAccounts as $acc)
                                    <option value="{{ $acc->id }}" {{ $inAccId == $acc->id ? 'selected' : '' }}>
                                        {{ $acc->code }} — {{ $acc->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 save-tax-btn" data-tc-id="{{ $tc->id }}"
                                        data-source-type="{{ addslashes(\App\Models\TaxCode::class) }}">
                                    <i class="bi bi-save2"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- ADVANCES & OTHER TAB --}}
        <div class="tab-pane fade p-3" id="tab-other">
            <p class="text-muted small mb-3">Configure accounts for advances, bank charges, discounts, and write-offs.</p>
            @php
                $otherTypes = [
                    'advance_customer' => ['label' => 'Customer Advance Receipts', 'icon' => 'bi-arrow-down-circle text-success',  'desc' => 'Liability account — money received from customers before invoice'],
                    'advance_supplier' => ['label' => 'Supplier Advance Payments', 'icon' => 'bi-arrow-up-circle text-danger',    'desc' => 'Asset account — money paid to suppliers before bill'],
                    'bank_charge'      => ['label' => 'Bank Charges',              'icon' => 'bi-bank text-secondary',            'desc' => 'Expense account for bank fees and transaction charges'],
                    'discount'         => ['label' => 'Discount Allowed',          'icon' => 'bi-tag text-info',                  'desc' => 'Expense account for discounts given to customers'],
                    'write_off'        => ['label' => 'Bad Debt Write-Off',        'icon' => 'bi-x-circle text-danger',           'desc' => 'Expense account for uncollectable receivables'],
                ];
            @endphp
            <div class="row g-3">
                @foreach($otherTypes as $type => $cfg)
                @php $curAccId = $mapped[$type][''][0] ?? null; @endphp
                <div class="col-md-6">
                    <div class="card border">
                        <div class="card-body">
                            <div class="fw-semibold small mb-1"><i class="bi {{ $cfg['icon'] }} me-2"></i>{{ $cfg['label'] }}</div>
                            <div class="text-muted mb-2" style="font-size:.75rem;">{{ $cfg['desc'] }}</div>
                            <div class="d-flex gap-2">
                                <select class="form-select form-select-sm flex-grow-1 mapping-select"
                                        data-type="{{ $type }}" data-source-type="" data-source-id="0">
                                    <option value="">— Not mapped —</option>
                                    @foreach($postingAccounts as $acc)
                                    <option value="{{ $acc->id }}" {{ $curAccId == $acc->id ? 'selected' : '' }}>
                                        {{ $acc->code }} — {{ $acc->name }}
                                    </option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 save-mapping-btn"
                                        data-type="{{ $type }}" data-source-type="" data-source-id="0">
                                    <i class="bi bi-save2"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

    </div>{{-- /tab-content --}}
</div>

@push('scripts')
<script>
(function () {
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    var saveUrl   = '{{ route("finance.setup.mappings.store") }}';

    function saveMappingAjax(type, sourceType, sourceId, accountId, btn) {
        btn.disabled = true;
        var orig = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch(saveUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                mapping_type: type,
                source_type:  sourceType || null,
                source_id:    parseInt(sourceId) || null,
                account_id:   parseInt(accountId),
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
            setTimeout(function () { btn.innerHTML = orig; btn.disabled = false; }, 1500);
        })
        .catch(function () {
            btn.innerHTML = '<i class="bi bi-x-lg text-danger"></i>';
            setTimeout(function () { btn.innerHTML = orig; btn.disabled = false; }, 1500);
        });
    }

    // Generic save buttons (revenue, expense, AR/AP, other)
    document.querySelectorAll('.save-mapping-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var type       = this.dataset.type;
            var sourceType = this.dataset.sourceType;
            var sourceId   = this.dataset.sourceId;
            // find the matching select
            var sel = document.querySelector('.mapping-select[data-type="' + type + '"][data-source-id="' + sourceId + '"]');
            if (!sel || !sel.value) {
                alert('Please select an account first.');
                return;
            }
            saveMappingAjax(type, sourceType, sourceId, sel.value, this);
        });
    });

    // Tax buttons
    document.querySelectorAll('.save-tax-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tcId       = this.dataset.tcId;
            var sourceType = this.dataset.sourceType;
            var outSel = document.querySelector('.tax-select-output[data-tc-id="' + tcId + '"]');
            var inSel  = document.querySelector('.tax-select-input[data-tc-id="' + tcId + '"]');
            var saved = 0;
            var total = (outSel && outSel.value ? 1 : 0) + (inSel && inSel.value ? 1 : 0);
            if (total === 0) { alert('Select at least one account.'); return; }
            this.disabled = true;
            var orig = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            var self = this;

            function done() {
                saved++;
                if (saved >= total) {
                    self.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
                    setTimeout(function () { self.innerHTML = orig; self.disabled = false; }, 1500);
                }
            }

            if (outSel && outSel.value) {
                fetch(saveUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ mapping_type: 'tax_output', source_type: sourceType, source_id: parseInt(tcId), account_id: parseInt(outSel.value) })
                }).then(done).catch(done);
            }
            if (inSel && inSel.value) {
                fetch(saveUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ mapping_type: 'tax_input', source_type: sourceType, source_id: parseInt(tcId), account_id: parseInt(inSel.value) })
                }).then(done).catch(done);
            }
        });
    });
})();
</script>
@endpush

@endsection
