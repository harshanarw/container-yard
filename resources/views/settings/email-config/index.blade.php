@extends('layouts.app')

@section('title', 'Email Configuration')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Settings</a></li>
    <li class="breadcrumb-item active">Email Configuration</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-envelope-gear me-2 text-primary"></i>Email Configuration</h4>
        <p class="text-muted mb-0 small">Manage email drivers and notification categories</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addConfigModal">
        <i class="bi bi-plus-lg me-1"></i>Add Configuration
    </button>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@php
$driverIcons = ['smtp' => 'bi-server', 'mailgun' => 'bi-lightning', 'sendgrid' => 'bi-send'];
$driverColors = ['smtp' => 'primary', 'mailgun' => 'warning', 'sendgrid' => 'info'];
$catColors = ['estimate' => 'primary', 'invoice' => 'success', 'stock_report' => 'secondary', 'movement_report' => 'dark', 'general' => 'light text-dark border'];
@endphp

@forelse($configs as $config)
<div class="card content-card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi {{ $driverIcons[$config->driver] ?? 'bi-envelope' }} text-{{ $driverColors[$config->driver] ?? 'secondary' }}"></i>
            <span class="fw-semibold">{{ $config->name }}</span>
            <span class="badge bg-{{ $driverColors[$config->driver] ?? 'secondary' }}">{{ strtoupper($config->driver) }}</span>
            <span class="badge bg-{{ $catColors[$config->category] ?? 'secondary' }}">{{ $categories[$config->category] ?? $config->category }}</span>
            @if($config->is_default)
                <span class="badge bg-success"><i class="bi bi-star-fill me-1"></i>Default</span>
            @endif
            @if(!$config->is_active)
                <span class="badge bg-secondary">Inactive</span>
            @endif
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal"
                    data-bs-target="#testModal{{ $config->id }}">
                <i class="bi bi-send me-1"></i>Test
            </button>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal"
                    data-bs-target="#editConfigModal{{ $config->id }}">
                <i class="bi bi-pencil me-1"></i>Edit
            </button>
            <form method="POST" action="{{ route('settings.email-config.destroy', $config) }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm"
                        data-confirm="Delete this email configuration?"
                        data-confirm-title="Delete Email Config"
                        data-confirm-class="btn-danger"
                        data-confirm-label="Delete">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        </div>
    </div>
    <div class="card-body small">
        <div class="row g-2">
            @if($config->driver === 'smtp')
                <div class="col-md-3"><span class="text-muted">Host:</span> {{ $config->smtp_host ?? '—' }}</div>
                <div class="col-md-2"><span class="text-muted">Port:</span> {{ $config->smtp_port ?? '—' }}</div>
                <div class="col-md-2"><span class="text-muted">Encryption:</span> {{ $config->smtp_encryption ?? '—' }}</div>
                <div class="col-md-3"><span class="text-muted">Username:</span> {{ $config->smtp_username ?? '—' }}</div>
            @elseif($config->driver === 'mailgun')
                <div class="col-md-4"><span class="text-muted">Domain:</span> {{ $config->mailgun_domain ?? '—' }}</div>
                <div class="col-md-4"><span class="text-muted">Endpoint:</span> {{ $config->mailgun_endpoint ?? '—' }}</div>
            @elseif($config->driver === 'sendgrid')
                <div class="col-md-4"><span class="text-muted">API Key:</span> ••••••••••••</div>
            @endif
            @if($config->from_email)
                <div class="col-md-4"><span class="text-muted">From:</span> {{ $config->from_name ? $config->from_name . ' <' . $config->from_email . '>' : $config->from_email }}</div>
            @endif
        </div>
    </div>
</div>

{{-- Edit Modal --}}
<div class="modal fade" id="editConfigModal{{ $config->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('settings.email-config.update', $config) }}">
                @csrf @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title">Edit: {{ $config->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('settings.email-config._form', ['config' => $config, 'categories' => $categories])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Test Modal --}}
<div class="modal fade" id="testModal{{ $config->id }}" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" action="{{ route('settings.email-config.test', $config) }}">
                @csrf
                <div class="modal-header py-2">
                    <h6 class="modal-title">Send Test Email</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-2">
                    <label class="form-label small fw-semibold">Send test to</label>
                    <input type="email" name="test_email" class="form-control form-control-sm" required
                           placeholder="recipient@example.com">
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Send Test</button>
                </div>
            </form>
        </div>
    </div>
</div>

@empty
<div class="card content-card">
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-envelope-x fs-1 d-block mb-2"></i>
        No email configurations yet. Add one to enable email sending.
    </div>
</div>
@endforelse

{{-- Add Modal --}}
<div class="modal fade" id="addConfigModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('settings.email-config.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Email Configuration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('settings.email-config._form', ['config' => null, 'categories' => $categories])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════
     INTERNAL NOTIFICATION RECIPIENTS
     ═══════════════════════════════════════════════════════════════════════ --}}
<div class="page-header d-flex align-items-center justify-content-between mt-4 mb-2">
    <div>
        <h5 class="mb-0"><i class="bi bi-people-fill me-2 text-warning"></i>Internal Notification Recipients</h5>
        <p class="text-muted mb-0 small">Define who receives internal email alerts for each notification category</p>
    </div>
</div>

@php
$intCategories = config('email_categories.internal');
$internalEmails = \App\Models\InternalNotificationEmail::orderBy('category')->orderBy('sort_order')->orderBy('address_type')->get()->groupBy('category');
@endphp

<div class="row g-3 mb-4">
@foreach($intCategories as $catKey => $catInfo)
<div class="col-lg-6">
<div class="card content-card h-100">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi {{ $catInfo['icon'] }} text-{{ $catInfo['color'] }}"></i>
        <span class="fw-semibold small">{{ $catInfo['label'] }}</span>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0 small align-middle">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:40%">Email</th>
                    <th style="width:30%">Label</th>
                    <th class="text-center" style="width:15%">Type</th>
                    <th class="text-center" style="width:15%"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($internalEmails->get($catKey, collect()) as $rec)
                <tr>
                    <td class="ps-3">
                        <span class="{{ $rec->is_active ? '' : 'text-muted text-decoration-line-through' }}">{{ $rec->email }}</span>
                    </td>
                    <td class="text-muted">{{ $rec->label ?: '—' }}</td>
                    <td class="text-center">
                        <span class="badge bg-{{ $rec->address_type === 'to' ? 'primary' : 'secondary' }}">{{ strtoupper($rec->address_type) }}</span>
                    </td>
                    <td class="text-center">
                        <div class="d-flex justify-content-center gap-1">
                            <button class="btn btn-xs btn-outline-secondary" data-bs-toggle="modal"
                                    data-bs-target="#editIntEmail{{ $rec->id }}" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="{{ route('settings.internal-emails.destroy', $rec) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-outline-danger"
                                        data-confirm="Remove this recipient?"
                                        data-confirm-title="Remove Recipient"
                                        data-confirm-class="btn-danger"
                                        data-confirm-label="Remove">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                {{-- Edit modal --}}
                <div class="modal fade" id="editIntEmail{{ $rec->id }}" tabindex="-1">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('settings.internal-emails.update', $rec) }}">
                                @csrf @method('PATCH')
                                <div class="modal-header py-2">
                                    <h6 class="modal-title">Edit Recipient</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body py-2">
                                    <div class="mb-2">
                                        <label class="form-label form-label-sm">Email</label>
                                        <input type="email" name="email" class="form-control form-control-sm" value="{{ $rec->email }}" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label form-label-sm">Label / Name</label>
                                        <input type="text" name="label" class="form-control form-control-sm" value="{{ $rec->label }}" placeholder="Optional">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label form-label-sm">Type</label>
                                        <select name="address_type" class="form-select form-select-sm">
                                            <option value="to" {{ $rec->address_type === 'to' ? 'selected' : '' }}>TO — Primary recipient</option>
                                            <option value="cc" {{ $rec->address_type === 'cc' ? 'selected' : '' }}>CC — Copy</option>
                                        </select>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="intActive{{ $rec->id }}" {{ $rec->is_active ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="intActive{{ $rec->id }}">Active</label>
                                    </div>
                                </div>
                                <div class="modal-footer py-2">
                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <tr>
                    <td colspan="4" class="text-center text-muted py-3 small fst-italic">No recipients configured</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        {{-- Add row form --}}
        <form method="POST" action="{{ route('settings.internal-emails.store') }}" class="border-top px-3 py-2">
            @csrf
            <input type="hidden" name="category" value="{{ $catKey }}">
            <div class="d-flex gap-2 align-items-end flex-wrap">
                <div style="flex:2;min-width:160px;">
                    <label class="form-label form-label-sm mb-1">Email address</label>
                    <input type="email" name="email" class="form-control form-control-sm" placeholder="staff@yard.com" required>
                </div>
                <div style="flex:1;min-width:100px;">
                    <label class="form-label form-label-sm mb-1">Label</label>
                    <input type="text" name="label" class="form-control form-control-sm" placeholder="Name">
                </div>
                <div style="min-width:90px;">
                    <label class="form-label form-label-sm mb-1">Type</label>
                    <select name="address_type" class="form-select form-select-sm">
                        <option value="to">TO</option>
                        <option value="cc">CC</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i></button>
                </div>
            </div>
        </form>
    </div>
</div>
</div>
@endforeach
</div>

@endsection

@push('styles')
<style>
    .btn-xs { padding: .18rem .5rem; font-size: .72rem; line-height: 1.2; }
</style>
@endpush

@push('scripts')
<script>
// Show/hide driver-specific fields
document.querySelectorAll('[data-driver-toggle]').forEach(function(el) {
    el.addEventListener('change', function() {
        const form = this.closest('form');
        form.querySelectorAll('[data-driver-section]').forEach(function(s) {
            s.style.display = 'none';
        });
        const target = form.querySelector('[data-driver-section="' + el.value + '"]');
        if (target) target.style.display = '';
    });
    // Initialize
    const form = el.closest('form');
    form.querySelectorAll('[data-driver-section]').forEach(function(s) {
        s.style.display = 'none';
    });
    const target = form.querySelector('[data-driver-section="' + el.value + '"]');
    if (target) target.style.display = '';
});
</script>
@endpush
