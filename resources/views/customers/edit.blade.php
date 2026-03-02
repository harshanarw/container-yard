@extends('layouts.app')

@section('title', 'Edit Customer — ' . $customer->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('customers.index') }}">Customers</a></li>
    <li class="breadcrumb-item"><a href="{{ route('customers.show', $customer) }}">{{ $customer->code }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')

<div class="page-header">
    <h4><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Customer</h4>
    <p class="text-muted mb-0 small">Update the profile for <strong>{{ $customer->name }}</strong></p>
</div>

<form method="POST" action="{{ route('customers.update', $customer) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')

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
                            <input type="text" name="code" class="form-control text-uppercase @error('code') is-invalid @enderror"
                                   placeholder="e.g. MSK" maxlength="10"
                                   value="{{ old('code', $customer->code) }}" required>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Short unique identifier (max 10 chars)</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Company / Customer Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   placeholder="e.g. Maersk Line Sdn Bhd"
                                   value="{{ old('name', $customer->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                                <option value="">— Select Type —</option>
                                <option value="shipping_line"     {{ old('type', $customer->type)=='shipping_line'    ?'selected':'' }}>Shipping Line</option>
                                <option value="freight_forwarder" {{ old('type', $customer->type)=='freight_forwarder'?'selected':'' }}>Freight Forwarder</option>
                                <option value="depot_owner"       {{ old('type', $customer->type)=='depot_owner'      ?'selected':'' }}>Depot Owner</option>
                                <option value="nvo_carrier"       {{ old('type', $customer->type)=='nvo_carrier'      ?'selected':'' }}>NVO Carrier</option>
                                <option value="leasing_company"   {{ old('type', $customer->type)=='leasing_company'  ?'selected':'' }}>Container Leasing Company</option>
                            </select>
                            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Registration No. (SSM)</label>
                            <input type="text" name="registration_no" class="form-control"
                                   placeholder="e.g. 202001012345"
                                   value="{{ old('registration_no', $customer->registration_no) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Registered Address</label>
                            <textarea name="address" class="form-control" rows="2"
                                      placeholder="Street address, city, postcode, state">{{ old('address', $customer->address) }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">City</label>
                            <input type="text" name="city" class="form-control"
                                   value="{{ old('city', $customer->city) }}" placeholder="Port Klang">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">State</label>
                            <select name="state" class="form-select">
                                <option value="">— State —</option>
                                @foreach(['Johor','Kedah','Kelantan','Melaka','Negeri Sembilan','Pahang',
                                          'Perak','Perlis','Pulau Pinang','Sabah','Sarawak',
                                          'Selangor','Terengganu','W.P. Kuala Lumpur','W.P. Labuan','W.P. Putrajaya'] as $state)
                                    <option {{ old('state', $customer->state)==$state?'selected':'' }}>{{ $state }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Country</label>
                            <input type="text" name="country" class="form-control"
                                   value="{{ old('country', $customer->country) }}">
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
                            <label class="form-label fw-semibold">Contact Person <span class="text-danger">*</span></label>
                            <input type="text" name="contact_person" class="form-control @error('contact_person') is-invalid @enderror"
                                   placeholder="Full name"
                                   value="{{ old('contact_person', $customer->contact_person) }}" required>
                            @error('contact_person')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Designation</label>
                            <input type="text" name="designation" class="form-control"
                                   placeholder="e.g. Operations Manager"
                                   value="{{ old('designation', $customer->designation) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Office Phone <span class="text-danger">*</span></label>
                            <input type="text" name="phone_office" class="form-control @error('phone_office') is-invalid @enderror"
                                   placeholder="03-XXXXXXXX"
                                   value="{{ old('phone_office', $customer->phone_office) }}" required>
                            @error('phone_office')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Mobile Phone</label>
                            <input type="text" name="phone_mobile" class="form-control"
                                   placeholder="01X-XXXXXXX"
                                   value="{{ old('phone_mobile', $customer->phone_mobile) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fax Number</label>
                            <input type="text" name="fax" class="form-control"
                                   placeholder="03-XXXXXXXX"
                                   value="{{ old('fax', $customer->fax) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                   placeholder="ops@company.com"
                                   value="{{ old('email', $customer->email) }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Website</label>
                            <input type="url" name="website" class="form-control"
                                   placeholder="https://www.company.com"
                                   value="{{ old('website', $customer->website) }}">
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
                                <option value="MYR" {{ old('currency', $customer->currency)=='MYR'?'selected':'' }}>MYR — Malaysian Ringgit</option>
                                <option value="USD" {{ old('currency', $customer->currency)=='USD'?'selected':'' }}>USD — US Dollar</option>
                                <option value="SGD" {{ old('currency', $customer->currency)=='SGD'?'selected':'' }}>SGD — Singapore Dollar</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Credit Limit</label>
                            <div class="input-group">
                                <span class="input-group-text">{{ $customer->currency }}</span>
                                <input type="number" name="credit_limit" class="form-control"
                                       placeholder="0.00" min="0" step="0.01"
                                       value="{{ old('credit_limit', $customer->credit_limit) }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Payment Terms</label>
                            <select name="payment_terms" class="form-select">
                                <option value="cod"   {{ old('payment_terms', $customer->payment_terms)=='cod'  ?'selected':'' }}>Cash on Delivery</option>
                                <option value="net15" {{ old('payment_terms', $customer->payment_terms)=='net15'?'selected':'' }}>Net 15 Days</option>
                                <option value="net30" {{ old('payment_terms', $customer->payment_terms)=='net30'?'selected':'' }}>Net 30 Days</option>
                                <option value="net45" {{ old('payment_terms', $customer->payment_terms)=='net45'?'selected':'' }}>Net 45 Days</option>
                                <option value="net60" {{ old('payment_terms', $customer->payment_terms)=='net60'?'selected':'' }}>Net 60 Days</option>
                            </select>
                        </div>

                        <div class="col-12"><hr class="my-1"><strong class="small text-muted">Storage Rates (per day)</strong></div>

                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">20' GP</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">{{ $customer->currency }}</span>
                                <input type="number" name="rate_20gp" class="form-control"
                                       placeholder="25.00" step="0.01"
                                       value="{{ old('rate_20gp', $customer->rate_20gp) }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">40' GP</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">{{ $customer->currency }}</span>
                                <input type="number" name="rate_40gp" class="form-control"
                                       placeholder="45.00" step="0.01"
                                       value="{{ old('rate_40gp', $customer->rate_40gp) }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">40' HC</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">{{ $customer->currency }}</span>
                                <input type="number" name="rate_40hc" class="form-control"
                                       placeholder="50.00" step="0.01"
                                       value="{{ old('rate_40hc', $customer->rate_40hc) }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Free Days</label>
                            <input type="number" name="free_days" class="form-control form-control-sm"
                                   placeholder="7" min="0"
                                   value="{{ old('free_days', $customer->free_days) }}">
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
                            <option value="active"   {{ old('status', $customer->status)=='active'  ?'selected':'' }}>Active</option>
                            <option value="pending"  {{ old('status', $customer->status)=='pending' ?'selected':'' }}>Pending Verification</option>
                            <option value="inactive" {{ old('status', $customer->status)=='inactive'?'selected':'' }}>Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Contract Start Date</label>
                        <input type="date" name="contract_start" class="form-control"
                               value="{{ old('contract_start', $customer->contract_start?->format('Y-m-d')) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Contract End Date</label>
                        <input type="date" name="contract_end" class="form-control"
                               value="{{ old('contract_end', $customer->contract_end?->format('Y-m-d')) }}">
                    </div>
                    <div class="mb-0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="email_notifications"
                                   id="emailNotif" {{ old('email_notifications', $customer->email_notifications) ? 'checked' : '' }}>
                            <label class="form-check-label small" for="emailNotif">Email Notifications</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="auto_invoice"
                                   id="autoInvoice" {{ old('auto_invoice', $customer->auto_invoice) ? 'checked' : '' }}>
                            <label class="form-check-label small" for="autoInvoice">Auto Invoice Generation</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logo -->
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-image me-2 text-primary"></i>Company Logo
                </div>
                <div class="card-body text-center">
                    @if($customer->logo)
                    <img src="{{ Storage::url($customer->logo) }}" alt="Logo"
                         class="img-thumbnail mb-2" style="max-height:80px;">
                    <div class="small text-muted mb-2">Current logo</div>
                    @endif
                    <div class="border rounded p-3 mb-2 bg-light" style="border-style:dashed!important;">
                        <i class="bi bi-cloud-arrow-up fs-2 text-muted"></i>
                        <div class="small text-muted mt-1">Upload new logo to replace</div>
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
                              placeholder="Any additional notes about this customer…">{{ old('notes', $customer->notes) }}</textarea>
                </div>
            </div>

            <!-- Actions -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle me-2"></i>Save Changes
                </button>
                <a href="{{ route('customers.show', $customer) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                </a>
            </div>

        </div>
    </div>
</form>

@endsection
