@extends('layouts.app')

@section('title', 'Register Customer')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('customers.index') }}">Customers</a></li>
    <li class="breadcrumb-item active">Register Customer</li>
@endsection

@section('content')

<div class="page-header">
    <h4><i class="bi bi-person-plus me-2 text-primary"></i>Register New Customer</h4>
    <p class="text-muted mb-0 small">Create a new customer profile for the yard management system</p>
</div>

<form method="POST" action="{{ route('customers.store') }}" enctype="multipart/form-data">
    @csrf

    <div class="row g-3">

        <!-- Left Column -->
        <div class="col-lg-8">

            <!-- Company Information -->
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-building me-2 text-primary"></i>Company Information
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Customer Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control text-uppercase"
                                   placeholder="e.g. MSK" maxlength="10"
                                   value="{{ old('code') }}" required>
                            <div class="form-text">Short unique identifier (max 10 chars)</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Company / Customer Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   placeholder="e.g. Maersk Line Sdn Bhd"
                                   value="{{ old('name') }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Customer Type(s)</label>
                            <div class="border rounded p-3" style="background:#fafafa;">
                                @if($customerTypes->isEmpty())
                                    <span class="text-muted small">No customer types defined yet. Add them in Setup → Customer → Customer Types.</span>
                                @else
                                <div class="row g-1">
                                    @foreach($customerTypes as $ct)
                                    <div class="col-md-4 col-6">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox"
                                                   name="types[]" value="{{ $ct->id }}" id="ct_{{ $ct->id }}"
                                                   {{ in_array($ct->id, old('types', [])) ? 'checked' : '' }}>
                                            <label class="form-check-label small" for="ct_{{ $ct->id }}">{{ $ct->name }}</label>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                            <div class="form-text">Select all roles that apply to this customer.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Registration No. (SSM)</label>
                            <input type="text" name="registration_no" class="form-control"
                                   placeholder="e.g. 202001012345"
                                   value="{{ old('registration_no') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Registered Address</label>
                            <textarea name="address" class="form-control" rows="2"
                                      placeholder="Street address, city, postcode, state">{{ old('address') }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">City</label>
                            <input type="text" name="city" class="form-control"
                                   value="{{ old('city') }}" placeholder="Port Klang">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">State</label>
                            <select name="state" class="form-select">
                                <option value="">— State —</option>
                                @foreach(['Johor','Kedah','Kelantan','Melaka','Negeri Sembilan','Pahang',
                                          'Perak','Perlis','Pulau Pinang','Sabah','Sarawak',
                                          'Selangor','Terengganu','W.P. Kuala Lumpur','W.P. Labuan','W.P. Putrajaya'] as $state)
                                    <option {{ old('state')==$state?'selected':'' }}>{{ $state }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Country</label>
                            <select name="country_id" class="form-select select2">
                                <option value="">— Select Country —</option>
                                @foreach($countries as $c)
                                    <option value="{{ $c->id }}"
                                        {{ old('country_id', $defaultCountryId) == $c->id ? 'selected' : '' }}>
                                        {{ $c->flag_emoji }} {{ $c->name }} ({{ $c->iso2 }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Local Agent</label>
                            <div class="customer-autocomplete" data-local-agents="1">
                                <input type="hidden" name="local_agent_id" class="ac-id" value="{{ old('local_agent_id') }}">
                                <input type="text" class="form-control ac-text" placeholder="Search by name or code…"
                                       autocomplete="off" value="">
                                <ul class="ac-dropdown list-group position-absolute shadow-sm" style="z-index:1000;display:none;width:100%;max-height:200px;overflow-y:auto;"></ul>
                            </div>
                            <div class="form-text">Only customers tagged as "Local Agent".</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Billing Party</label>
                            <div class="customer-autocomplete" data-local-agents="0">
                                <input type="hidden" name="billing_party_id" class="ac-id" value="{{ old('billing_party_id') }}">
                                <input type="text" class="form-control ac-text" placeholder="Search by name or code…"
                                       autocomplete="off" value="">
                                <ul class="ac-dropdown list-group position-absolute shadow-sm" style="z-index:1000;display:none;width:100%;max-height:200px;overflow-y:auto;"></ul>
                            </div>
                            <div class="form-text">Defaults to same customer if not specified.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-telephone me-2 text-primary"></i>Contact Information
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control"
                                   placeholder="Full name" value="{{ old('contact_person') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Designation</label>
                            <input type="text" name="designation" class="form-control"
                                   placeholder="e.g. Operations Manager" value="{{ old('designation') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Office Phone</label>
                            <input type="text" name="phone_office" class="form-control"
                                   placeholder="03-XXXXXXXX" value="{{ old('phone_office') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Mobile Phone</label>
                            <input type="text" name="phone_mobile" class="form-control"
                                   placeholder="01X-XXXXXXX" value="{{ old('phone_mobile') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fax Number</label>
                            <input type="text" name="fax" class="form-control"
                                   placeholder="03-XXXXXXXX" value="{{ old('fax') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control"
                                   placeholder="ops@company.com" value="{{ old('email') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Website</label>
                            <input type="url" name="website" class="form-control"
                                   placeholder="https://www.company.com" value="{{ old('website') }}">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Billing & Rate Configuration -->
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-cash-stack me-2 text-primary"></i>Billing & Rate Configuration
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Currency</label>
                            <select name="currency" class="form-select">
                                <option value="LKR">LKR — Sri Lankan Rupee</option>
                                <option value="USD">USD — US Dollar</option>
                                <option value="SGD">SGD — Singapore Dollar</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Credit Limit</label>
                            <div class="input-group">
                                <span class="input-group-text">LKR</span>
                                <input type="number" name="credit_limit" class="form-control"
                                       placeholder="0.00" min="0" step="0.01" value="{{ old('credit_limit') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Payment Terms</label>
                            <select name="payment_terms" class="form-select">
                                <option value="cod">Cash on Delivery</option>
                                <option value="net15">Net 15 Days</option>
                                <option value="net30">Net 30 Days</option>
                                <option value="net45">Net 45 Days</option>
                                <option value="net60">Net 60 Days</option>
                            </select>
                        </div>

                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column -->
        <div class="col-lg-4">

            <!-- Status & Contract -->
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-file-earmark-check me-2 text-primary"></i>Contract & Status
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Customer Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="pending">Pending Verification</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Contract Start Date</label>
                        <input type="date" name="contract_start" class="form-control"
                               value="{{ old('contract_start', date('Y-m-d')) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Contract End Date</label>
                        <input type="date" name="contract_end" class="form-control"
                               value="{{ old('contract_end') }}">
                    </div>
                    <div class="mb-0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="email_notifications" id="emailNotif" checked>
                            <label class="form-check-label small" for="emailNotif">Email Notifications</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="auto_invoice" id="autoInvoice" checked>
                            <label class="form-check-label small" for="autoInvoice">Auto Invoice Generation</label>
                        </div>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="tax_exempt" id="taxExempt"
                                   {{ old('tax_exempt') ? 'checked' : '' }}>
                            <label class="form-check-label small fw-semibold text-warning-emphasis" for="taxExempt">
                                <i class="bi bi-shield-check me-1"></i>Tax Exempt Customer
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logo Upload -->
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-image me-2 text-primary"></i>Company Logo
                </div>
                <div class="card-body text-center">
                    <div class="border rounded p-4 mb-2 bg-light" style="border-style:dashed!important;">
                        <i class="bi bi-cloud-arrow-up fs-2 text-muted"></i>
                        <div class="small text-muted mt-1">Drag & drop or click to upload</div>
                        <div class="small text-muted">PNG, JPG up to 2MB</div>
                    </div>
                    <input type="file" name="logo" class="form-control form-control-sm" accept="image/*">
                </div>
            </div>

            <!-- Notes -->
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-sticky me-2 text-primary"></i>Notes / Remarks
                </div>
                <div class="card-body">
                    <textarea name="notes" class="form-control" rows="4"
                              placeholder="Any additional notes about this customer…">{{ old('notes') }}</textarea>
                </div>
            </div>

            <!-- Actions -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle me-2"></i>Register Customer
                </button>
                <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                </a>
            </div>

        </div>
    </div>
</form>

@endsection

@push('scripts')
<script>
(function () {
    const searchUrl = '{{ route("customers.search") }}';

    document.querySelectorAll('.customer-autocomplete').forEach(function (widget) {
        const localAgents = widget.dataset.localAgents === '1';
        const idInput     = widget.querySelector('.ac-id');
        const textInput   = widget.querySelector('.ac-text');
        const dropdown    = widget.querySelector('.ac-dropdown');
        let debounce      = null;

        widget.style.position = 'relative';

        textInput.addEventListener('input', function () {
            clearTimeout(debounce);
            const q = textInput.value.trim();
            if (q.length < 1) { closeDropdown(); idInput.value = ''; return; }
            debounce = setTimeout(function () { fetchResults(q); }, 280);
        });

        textInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeDropdown();
        });

        document.addEventListener('click', function (e) {
            if (!widget.contains(e.target)) closeDropdown();
        });

        function fetchResults(q) {
            const url = searchUrl + '?q=' + encodeURIComponent(q) + (localAgents ? '&local_agents=1' : '');
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(renderDropdown);
        }

        function renderDropdown(items) {
            dropdown.innerHTML = '';
            if (!items.length) {
                dropdown.innerHTML = '<li class="list-group-item py-2 small text-muted">No results found</li>';
            } else {
                items.forEach(function (item) {
                    const li = document.createElement('li');
                    li.className = 'list-group-item list-group-item-action py-2 small';
                    li.textContent = item.label;
                    li.style.cursor = 'pointer';
                    li.addEventListener('click', function () { selectItem(item); });
                    dropdown.appendChild(li);
                });
            }
            dropdown.style.display = 'block';
        }

        function selectItem(item) {
            idInput.value   = item.id;
            textInput.value = item.label;
            closeDropdown();

            // Auto-fill billing party when local agent is picked (if billing party is blank)
            if (localAgents) {
                const form           = widget.closest('form');
                const billingIdInput = form.querySelector('[name="billing_party_id"]');
                const billingText    = form.querySelector('.customer-autocomplete[data-local-agents="0"] .ac-text');
                if (billingIdInput && !billingIdInput.value) {
                    billingIdInput.value = item.id;
                    if (billingText) billingText.value = item.label;
                }
            }
        }

        function closeDropdown() { dropdown.style.display = 'none'; }
    });
})();
</script>
@endpush
