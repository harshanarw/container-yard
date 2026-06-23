@php $s = $supplier ?? null; @endphp

@if($errors->any())
<div class="alert alert-danger py-2 small">
    <i class="bi bi-exclamation-triangle me-1"></i>Please correct the errors below.
    <ul class="mb-0 mt-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="row g-3">
    {{-- Identity --}}
    <div class="col-lg-6">
        <div class="card content-card h-100">
            <div class="card-header bg-transparent py-2"><strong class="small">Identity</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small">Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control form-control-sm font-monospace" required
                               value="{{ old('code', $s->code ?? ($nextCode ?? '')) }}">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" required value="{{ old('name', $s->name ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Registration No</label>
                        <input type="text" name="registration_no" class="form-control form-control-sm" value="{{ old('registration_no', $s->registration_no ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">TIN Number</label>
                        <input type="text" name="tin_number" class="form-control form-control-sm" value="{{ old('tin_number', $s->tin_number ?? '') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Address</label>
                        <textarea name="address" rows="2" class="form-control form-control-sm">{{ old('address', $s->address ?? '') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">City</label>
                        <input type="text" name="city" class="form-control form-control-sm" value="{{ old('city', $s->city ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Country</label>
                        <select name="country_id" class="form-select form-select-sm">
                            <option value="">— Select —</option>
                            @foreach($countries as $c)
                            <option value="{{ $c->id }}" {{ (string) old('country_id', $s->country_id ?? '') === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Contact + Commercial --}}
    <div class="col-lg-6">
        <div class="card content-card mb-3">
            <div class="card-header bg-transparent py-2"><strong class="small">Contact</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control form-control-sm" value="{{ old('contact_person', $s->contact_person ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Phone</label>
                        <input type="text" name="phone" class="form-control form-control-sm" value="{{ old('phone', $s->phone ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Email</label>
                        <input type="email" name="email" class="form-control form-control-sm" value="{{ old('email', $s->email ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Website</label>
                        <input type="text" name="website" class="form-control form-control-sm" value="{{ old('website', $s->website ?? '') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card content-card">
            <div class="card-header bg-transparent py-2"><strong class="small">Commercial Terms</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small">Currency <span class="text-danger">*</span></label>
                        <select name="currency" class="form-select form-select-sm" required>
                            @foreach(['LKR','USD','SGD'] as $c)
                            <option value="{{ $c }}" {{ old('currency', $s->currency ?? 'LKR') === $c ? 'selected' : '' }}>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Credit Limit</label>
                        <input type="number" step="0.01" min="0" name="credit_limit" class="form-control form-control-sm text-end" value="{{ old('credit_limit', $s->credit_limit ?? 0) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Payment Terms <span class="text-danger">*</span></label>
                        <select name="payment_terms" class="form-select form-select-sm" required>
                            @foreach(['cod'=>'COD','net15'=>'Net 15','net30'=>'Net 30','net45'=>'Net 45','net60'=>'Net 60'] as $k => $v)
                            <option value="{{ $k }}" {{ old('payment_terms', $s->payment_terms ?? 'net30') === $k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select form-select-sm" required>
                            @foreach(['active','pending','inactive'] as $st)
                            <option value="{{ $st }}" {{ old('status', $s->status ?? 'active') === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="tax_exempt" value="1" class="form-check-input" id="tax_exempt" {{ old('tax_exempt', $s->tax_exempt ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label small" for="tax_exempt">Tax exempt</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Notes</label>
                        <textarea name="notes" rows="2" class="form-control form-control-sm">{{ old('notes', $s->notes ?? '') }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
