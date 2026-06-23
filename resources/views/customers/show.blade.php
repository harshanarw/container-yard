@extends('layouts.app')

@section('title', 'Customer — ' . $customer->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('customers.index') }}">Customers</a></li>
    <li class="breadcrumb-item active">{{ $customer->code }}</li>
@endsection

@section('content')

@php
$paymentLabels = [
    'cod'   => 'Cash on Delivery',
    'net15' => 'Net 15 Days',
    'net30' => 'Net 30 Days',
    'net45' => 'Net 45 Days',
    'net60' => 'Net 60 Days',
];
$statusColor = $customer->status === 'active' ? 'success' : ($customer->status === 'pending' ? 'warning text-dark' : 'secondary');
@endphp

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-person-badge me-2 text-primary"></i>{{ $customer->name }}</h4>
        <p class="text-muted mb-0 small">
            <span class="badge bg-dark font-monospace me-1">{{ $customer->code }}</span>
            @foreach($customer->types as $t)
                <span class="badge bg-info-subtle text-info border border-info-subtle me-1">{{ $t->name }}</span>
            @endforeach
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @can('customers.edit')
        <a href="{{ route('customers.edit', $customer) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        @endcan
        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<!-- Stats Row -->
<div class="row g-3 mb-3">
    <div class="col-sm-3">
        <div class="card stat-card text-center py-3">
            <div class="fs-4 fw-bold text-primary">{{ $customer->containers_count }}</div>
            <div class="text-muted small">Containers</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card stat-card text-center py-3">
            <div class="fs-4 fw-bold text-info">{{ $customer->inquiries_count }}</div>
            <div class="text-muted small">Inquiries</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card stat-card text-center py-3">
            <div class="fs-4 fw-bold text-success">{{ $customer->estimates_count }}</div>
            <div class="text-muted small">Estimates</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card stat-card text-center py-3">
            <div class="fs-4 fw-bold text-warning">{{ $customer->gate_movements_count }}</div>
            <div class="text-muted small">Gate Movements</div>
        </div>
    </div>
</div>

@if($apVisible && ($recentApBills->isNotEmpty() || $apOutstanding > 0))
{{-- Accounts Payable — this contact acting as a supplier/creditor --}}
<div class="card content-card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-receipt-cutoff me-2 text-danger"></i>Accounts Payable (as Supplier)</span>
        <span class="small">
            <span class="text-muted">Outstanding:</span>
            <span class="font-monospace fw-semibold {{ $apOutstanding > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($apOutstanding, 2) }}</span>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Invoice No</th>
                    <th>Bill No</th>
                    <th>Date</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentApBills as $bill)
                <tr>
                    <td class="font-monospace fw-semibold">
                        <a href="{{ route('finance.ap.invoices.show', $bill) }}" class="text-decoration-none">{{ $bill->invoice_no }}</a>
                    </td>
                    <td class="text-muted">{{ $bill->supplier_invoice_no ?: '—' }}</td>
                    <td>{{ $bill->invoice_date->format('d M Y') }}</td>
                    <td class="text-end font-monospace">{{ number_format($bill->total_amount, 2) }} <span class="text-muted">{{ $bill->currency }}</span></td>
                    <td><span class="badge {{ $bill->status_badge_class }}">{{ $bill->status_label }}</span></td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-muted py-3">No supplier invoices for this contact.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @can('finance.ap.create')
    <div class="card-footer bg-transparent py-2">
        <a href="{{ route('finance.ap.invoices.create', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-plus-lg me-1"></i>New Supplier Invoice
        </a>
    </div>
    @endcan
</div>
@endif

<div class="row g-3">
    <!-- Left Column -->
    <div class="col-lg-8">

        <!-- Company Information -->
        <div class="card content-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-building me-2 text-primary"></i>Company Information</span>
                <span class="badge rounded-pill bg-{{ $statusColor }}">{{ ucfirst($customer->status) }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Customer Code</div>
                        <div class="fw-semibold font-monospace">{{ $customer->code }}</div>
                    </div>
                    <div class="col-md-8">
                        <div class="text-muted small">Company Name</div>
                        <div class="fw-semibold">{{ $customer->name }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Customer Type(s)</div>
                        <div>
                            @forelse($customer->types as $t)
                                <span class="badge bg-info-subtle text-info border border-info-subtle me-1 mb-1">{{ $t->name }}</span>
                            @empty
                                <span class="text-muted small">—</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Registration No. (SSM)</div>
                        <div>{{ $customer->registration_no ?: '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">TIN (Tax Identification No.)</div>
                        <div class="font-monospace">{{ $customer->tin_number ?: '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Local Agent</div>
                        <div>
                            @if($customer->localAgent)
                                <a href="{{ route('customers.show', $customer->localAgent) }}" class="text-decoration-none">
                                    <span class="badge bg-secondary-subtle text-secondary border me-1">{{ $customer->localAgent->code }}</span>{{ $customer->localAgent->name }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Billing Party</div>
                        <div>
                            @if($customer->billingParty)
                                <a href="{{ route('customers.show', $customer->billingParty) }}" class="text-decoration-none">
                                    <span class="badge bg-secondary-subtle text-secondary border me-1">{{ $customer->billingParty->code }}</span>{{ $customer->billingParty->name }}
                                </a>
                            @else
                                <span class="text-muted small fst-italic">Same as customer</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Address</div>
                        <div>{{ $customer->address ?: '—' }}</div>
                        @if($customer->city || $customer->state)
                        <div class="text-muted small mt-1">{{ implode(', ', array_filter([$customer->city, $customer->state, $customer->country])) }}</div>
                        @endif
                    </div>
                    @if($customer->website)
                    <div class="col-12">
                        <div class="text-muted small">Website</div>
                        <a href="{{ $customer->website }}" target="_blank" class="small">{{ $customer->website }}</a>
                    </div>
                    @endif
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
                        <div class="text-muted small">Contact Person</div>
                        <div class="fw-semibold">{{ $customer->contact_person }}</div>
                        @if($customer->designation)
                        <div class="text-muted small">{{ $customer->designation }}</div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Email</div>
                        <a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Office Phone</div>
                        <div>{{ $customer->phone_office }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Mobile</div>
                        <div>{{ $customer->phone_mobile ?: '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Fax</div>
                        <div>{{ $customer->fax ?: '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Containers -->
        @if($recentContainers->isNotEmpty())
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-box-seam me-2 text-primary"></i>Recent Containers
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Container No.</th>
                            <th>Size/Type</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentContainers as $container)
                        <tr>
                            <td class="ps-3 font-monospace small">{{ $container->container_no ?? '—' }}</td>
                            <td class="small">{{ $container->size ?? '—' }}</td>
                            <td class="small">{{ ucfirst($container->status ?? '—') }}</td>
                            <td class="small text-muted">{{ $container->created_at->format('d M Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <!-- Email Notification Contacts -->
        @can('customers.edit')
        @php
        $emailContactCategories = config('email_categories.customer');
        $emailContacts = $customer->emailContacts->groupBy('category');
        @endphp
        <div class="card content-card mb-3" id="email-contacts" style="scroll-margin-top:80px;">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-envelope-at me-2 text-warning"></i>Email Notification Contacts</span>
                <span class="badge bg-light text-muted border small">Per-category recipient lists</span>
            </div>
            <div class="px-3 py-2 border-bottom bg-light-subtle small text-muted">
                <i class="bi bi-info-circle me-1"></i>These are the customer-facing <strong>TO</strong> / <strong>CC</strong> recipients for this customer's emails.
                Sender identity and common (always-CC) addresses are managed in
                @if(auth()->user()->isSystemAdmin())
                    <a href="{{ route('settings.email-config.index', ['tab' => 'external', 'customer_id' => $customer->id]) }}">Settings → Email Configuration</a>.
                @else
                    Settings → Email Configuration.
                @endif
            </div>
            <div class="card-body p-0">
                @foreach($emailContactCategories as $catKey => $catInfo)
                    @php
                        $catContacts = $emailContacts->get($catKey, collect());
                        $fmt = fn ($c) => $c->label ? "{$c->label} <{$c->email}>" : $c->email;
                        $toLines = $catContacts->where('address_type', 'to')->map($fmt)->implode("\n");
                        $ccLines = $catContacts->where('address_type', 'cc')->map($fmt)->implode("\n");
                    @endphp
                    <div class="border-bottom px-3 py-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi {{ $catInfo['icon'] }} text-{{ $catInfo['color'] }}"></i>
                            <span class="fw-semibold small">{{ $catInfo['label'] }}</span>
                            @if($catContacts->isEmpty())
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1">No recipients</span>
                            @else
                                <span class="badge bg-light text-muted border ms-1">{{ $catContacts->where('address_type','to')->count() }} TO · {{ $catContacts->where('address_type','cc')->count() }} CC</span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('customers.email-contacts.sync', $customer) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="category" value="{{ $catKey }}">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label form-label-sm mb-1">
                                        <span class="badge bg-primary">TO</span> Primary recipients
                                    </label>
                                    <textarea name="to_emails" class="form-control form-control-sm font-monospace" rows="3"
                                              placeholder="one email per line&#10;Sophie Lim &lt;sophie@cmacgm.com&gt;&#10;ops@cmacgm.com">{{ old('to_emails', $toLines) }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label form-label-sm mb-1">
                                        <span class="badge bg-secondary">CC</span> Copies <span class="text-muted fw-normal">(optional)</span>
                                    </label>
                                    <textarea name="cc_emails" class="form-control form-control-sm font-monospace" rows="3"
                                              placeholder="one email per line&#10;accounts@cmacgm.com">{{ old('cc_emails', $ccLines) }}</textarea>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mt-2">
                                <span class="form-text mb-0">
                                    <i class="bi bi-info-circle me-1"></i>One email per line (or comma-separated). Optional name as <code>Name &lt;email&gt;</code>. Saving replaces this category's list.
                                </span>
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-check2 me-1"></i>Update {{ $catInfo['label'] }}
                                </button>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
        @endcan

    </div>

    <!-- Right Column -->
    <div class="col-lg-4">

        <!-- AR Terms -->
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-cash-stack me-2 text-success"></i>Receivable Terms <span class="text-muted fw-normal small">(AR)</span>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <div class="text-muted small">Currency</div>
                    <div class="fw-semibold">{{ $customer->currency }}</div>
                </div>
                <div class="mb-2">
                    <div class="text-muted small">AR Credit Limit</div>
                    <div class="fw-semibold">{{ $customer->currency }} {{ number_format($customer->credit_limit, 2) }}</div>
                </div>
                <div class="mb-0">
                    <div class="text-muted small">AR Payment Terms</div>
                    <div>{{ $paymentLabels[$customer->payment_terms] ?? $customer->payment_terms }}</div>
                </div>
            </div>
        </div>

        <!-- AP Terms -->
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-receipt-cutoff me-2 text-danger"></i>Payable Terms <span class="text-muted fw-normal small">(AP)</span>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <div class="text-muted small">AP Credit Limit</div>
                    <div class="fw-semibold">{{ $customer->currency }} {{ number_format($customer->ap_credit_limit ?? 0, 2) }}</div>
                </div>
                <div class="mb-0">
                    <div class="text-muted small">AP Payment Terms</div>
                    <div>{{ $paymentLabels[$customer->ap_payment_terms ?? ''] ?? ($customer->ap_payment_terms ? $customer->ap_payment_terms : '—') }}</div>
                </div>
            </div>
        </div>

        <!-- Storage Tariff -->
        <div class="card content-card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-calendar2-range me-2 text-primary"></i>Storage Tariff</span>
                <a href="{{ route('masters.storage-tariff.index') }}"
                   class="btn btn-xs btn-outline-primary btn-sm py-0 px-2"
                   style="font-size:.72rem;" title="Manage Storage Tariffs">
                    <i class="bi bi-calendar2-range me-1"></i>Manage
                </a>
            </div>
            <div class="card-body">
                @php $tariff = $customer->activeTariff; @endphp
                @if($tariff)
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Free Days</span>
                        <span class="fw-semibold">{{ $tariff->default_free_days }} days</span>
                    </div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Validity</span>
                        <span>{{ $tariff->valid_from->format('d M Y') }}
                            @if($tariff->valid_to) — {{ $tariff->valid_to->format('d M Y') }}
                            @else <span class="text-muted">open-ended</span>
                            @endif
                        </span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Rate Lines</span>
                        <a href="{{ route('masters.storage-tariff.show', $tariff) }}"
                           class="fw-semibold text-decoration-none">
                            {{ $tariff->details->count() }} type(s)
                            <i class="bi bi-arrow-right-short"></i>
                        </a>
                    </div>
                @else
                    <div class="text-muted small fst-italic">
                        <i class="bi bi-exclamation-circle me-1 text-warning"></i>
                        No active storage tariff defined.
                        <a href="{{ route('masters.storage-tariff.index') }}" class="text-decoration-none">
                            Add one &rarr;
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <!-- Contract -->
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-file-earmark-check me-2 text-primary"></i>Contract
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <div class="text-muted small">Contract Start</div>
                    <div>{{ $customer->contract_start?->format('d M Y') ?? '—' }}</div>
                </div>
                <div class="mb-2">
                    <div class="text-muted small">Contract End</div>
                    <div>{{ $customer->contract_end?->format('d M Y') ?? '—' }}</div>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between small">
                    <span>Auto Invoice</span>
                    <span class="badge bg-{{ $customer->auto_invoice ? 'success' : 'secondary' }}">
                        {{ $customer->auto_invoice ? 'On' : 'Off' }}
                    </span>
                </div>
                <div class="d-flex justify-content-between small mt-1">
                    <span>Tax Exempt</span>
                    @if($customer->tax_exempt)
                        <span class="badge bg-warning text-dark">
                            <i class="bi bi-shield-check me-1"></i>Exempt
                        </span>
                    @else
                        <span class="badge bg-secondary">Taxable</span>
                    @endif
                </div>
            </div>
        </div>

        @if($customer->notes)
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-sticky me-2 text-primary"></i>Notes
            </div>
            <div class="card-body small">{{ $customer->notes }}</div>
        </div>
        @endif

        <!-- Delete -->
        @can('customers.delete')
        <form action="{{ route('customers.destroy', $customer) }}" method="POST"
              data-confirm="Delete {{ addslashes($customer->name) }}? This cannot be undone."
              data-confirm-title="Delete Customer"
              data-confirm-class="btn-danger"
              data-confirm-label="Delete">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                <i class="bi bi-trash me-1"></i>Delete Customer
            </button>
        </form>
        @endcan

    </div>
</div>

@endsection

@push('styles')
<style>
    .btn-xs { padding: .18rem .5rem; font-size: .72rem; line-height: 1.2; }
</style>
@endpush
