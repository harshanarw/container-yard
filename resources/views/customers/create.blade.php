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
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="">— Select Type —</option>
                                <option value="shipping_line" {{ old('type')=='shipping_line'?'selected':'' }}>Shipping Line</option>
                                <option value="freight_forwarder" {{ old('type')=='freight_forwarder'?'selected':'' }}>Freight Forwarder</option>
                                <option value="depot_owner" {{ old('type')=='depot_owner'?'selected':'' }}>Depot Owner</option>
                                <option value="nvo_carrier" {{ old('type')=='nvo_carrier'?'selected':'' }}>NVO Carrier</option>
                                <option value="leasing_company" {{ old('type')=='leasing_company'?'selected':'' }}>Container Leasing Company</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Registration No. (SSM)</label>
                            <input type="text" name="registration_no" class="form-control"
                                   placeholder="e.g. 202001012345"
                                   value="{{ old('registration_no') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Registered Address <span class="text-danger">*</span></label>
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
                            <input type="text" name="country" class="form-control"
                                   value="{{ old('country','Malaysia') }}">
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
                            <input type="text" name="contact_person" class="form-control"
                                   placeholder="Full name" value="{{ old('contact_person') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Designation</label>
                            <input type="text" name="designation" class="form-control"
                                   placeholder="e.g. Operations Manager" value="{{ old('designation') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Office Phone <span class="text-danger">*</span></label>
                            <input type="text" name="phone_office" class="form-control"
                                   placeholder="03-XXXXXXXX" value="{{ old('phone_office') }}" required>
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
                            <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control"
                                   placeholder="ops@company.com" value="{{ old('email') }}" required>
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
