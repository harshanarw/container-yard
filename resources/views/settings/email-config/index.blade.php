@extends('layouts.app')

@section('title', 'Email Configuration')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Settings</a></li>
    <li class="breadcrumb-item active">Email Configuration</li>
@endsection

@section('content')

<div class="page-header mb-3">
    <h4><i class="bi bi-envelope-gear me-2 text-primary"></i>Email Configuration</h4>
    <p class="text-muted mb-0 small">Configure mailer drivers, common CC addresses, internal staff notifications, and per-customer recipient lists.</p>
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

{{-- ═══════════════════════════════ TAB NAV ═══════════════════════════════ --}}
<ul class="nav nav-tabs mb-3" id="emailConfigTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="internal-tab"
                data-bs-toggle="tab" data-bs-target="#internal-tab-pane"
                type="button" role="tab"
                aria-controls="internal-tab-pane" aria-selected="true">
            <i class="bi bi-people-fill me-1 text-warning"></i>Internal Notifications
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="external-tab"
                data-bs-toggle="tab" data-bs-target="#external-tab-pane"
                type="button" role="tab"
                aria-controls="external-tab-pane" aria-selected="false">
            <i class="bi bi-envelope-at me-1 text-primary"></i>External / Customer Emails
        </button>
    </li>
</ul>

<div class="tab-content" id="emailConfigTabsContent">

{{-- ════════════════════════════════════════════════════════════════════════
     TAB 1 — INTERNAL NOTIFICATIONS
     Who among yard staff receives copies of internal alert categories.
     ════════════════════════════════════════════════════════════════════════ --}}
<div class="tab-pane fade show active" id="internal-tab-pane"
     role="tabpanel" aria-labelledby="internal-tab" tabindex="0">

    <p class="text-muted small mb-3">
        Configure how internal staff notifications are sent (the mail server / sender per category)
        and who receives them. Senders are optional and fall back to General; recipients are CC'd on
        customer-facing emails or sent directly for internal-only alerts.
    </p>

    {{-- ─── Configure Email Sender (per internal category) ─────────────────
         A dedicated mail server / sender identity per internal notification
         category. A category without its own active sender uses General
         Notifications; if General is also unset, internal mail falls back to
         the external 'General' sender configured in the External tab. --}}
    @php
        $intCategories   = config('email_categories.internal');
        $intDriverIcons  = ['smtp' => 'bi-server', 'mailgun' => 'bi-lightning', 'sendgrid' => 'bi-send'];
        $intDriverColors = ['smtp' => 'primary', 'mailgun' => 'warning', 'sendgrid' => 'info'];
    @endphp

    <h6 class="fw-semibold mb-1"><i class="bi bi-hdd-network me-2 text-warning"></i>Configure Email Sender</h6>
    <p class="text-muted small mb-3">
        Define a mail server (SMTP / Mailgun / SendGrid) and sender identity for each internal notification category.
        A category without its own active sender uses <strong>General Notifications</strong>; if that is also unset,
        internal mail falls back to the external <strong>General</strong> sender (External tab).
    </p>

    <div class="row g-3 mb-4">
    @foreach($intCategories as $catKey => $catInfo)
        @php
            $cfg       = $internalConfigs->firstWhere('category', $catKey);
            $isGeneral = $catKey === 'general';
        @endphp
        <div class="col-lg-6">
            <div class="card content-card h-100 {{ $cfg ? 'border-warning' : '' }}">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="fw-semibold small">
                        <i class="bi {{ $catInfo['icon'] }} text-{{ $catInfo['color'] }} me-2"></i>{{ $catInfo['label'] }}
                        @if($isGeneral)<span class="badge bg-light text-muted border ms-1">Fallback</span>@endif
                    </span>
                    @if($cfg)
                        <div class="d-flex gap-1">
                            <button class="btn btn-xs btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editIntSender{{ $catKey }}" title="Edit"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-xs btn-outline-primary" data-bs-toggle="modal" data-bs-target="#testIntSender{{ $catKey }}" title="Send test"><i class="bi bi-send"></i></button>
                            <form method="POST" action="{{ route('settings.email-config.destroy', $cfg) }}" class="d-inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-outline-danger"
                                        data-confirm="Remove this internal sender? This category will fall back to General."
                                        data-confirm-title="Remove Sender" data-confirm-class="btn-danger" data-confirm-label="Remove" title="Remove">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    @else
                        <button class="btn btn-xs btn-warning" data-bs-toggle="modal" data-bs-target="#addIntSender{{ $catKey }}">
                            <i class="bi bi-plus-lg me-1"></i>Configure
                        </button>
                    @endif
                </div>
                <div class="card-body small">
                    @if($cfg)
                        <div class="mb-1">
                            <span class="badge bg-{{ $intDriverColors[$cfg->driver] ?? 'secondary' }}">
                                <i class="bi {{ $intDriverIcons[$cfg->driver] ?? 'bi-envelope' }} me-1"></i>{{ strtoupper($cfg->driver) }}
                            </span>
                            @if($cfg->is_active)
                                <span class="badge bg-success ms-1">Active</span>
                            @else
                                <span class="badge bg-secondary ms-1">Inactive — uses fallback</span>
                            @endif
                        </div>
                        @if($cfg->driver === 'smtp')
                            <div><span class="text-muted">Host:</span> {{ $cfg->smtp_host ?? '—' }}:{{ $cfg->smtp_port ?? '—' }}</div>
                            <div><span class="text-muted">User:</span> {{ $cfg->smtp_username ?? '—' }}</div>
                        @elseif($cfg->driver === 'mailgun')
                            <div><span class="text-muted">Domain:</span> {{ $cfg->mailgun_domain ?? '—' }}</div>
                        @elseif($cfg->driver === 'sendgrid')
                            <div><span class="text-muted">API Key:</span> ••••••••••••</div>
                        @endif
                        @if($cfg->from_email)
                            <div class="mt-1"><span class="text-muted">From:</span> {{ $cfg->from_name ? $cfg->from_name . ' <' . $cfg->from_email . '>' : $cfg->from_email }}</div>
                        @endif
                    @else
                        <div class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            @if($isGeneral)
                                No General internal sender — internal mail falls back to the external <strong>General</strong> sender.
                            @else
                                No dedicated sender — uses <strong>General Notifications</strong> (or the external General sender).
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Per-category sender modals --}}
        @if($cfg)
            <div class="modal fade" id="editIntSender{{ $catKey }}" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('settings.email-config.update', $cfg) }}">
                            @csrf @method('PATCH')
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Sender — {{ $catInfo['label'] }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                @include('settings.email-config._form', ['config' => $cfg, 'categories' => $categories, 'isInternal' => true, 'fixedCategory' => $catKey, 'fixedCatLabel' => $catInfo['label']])
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="testIntSender{{ $catKey }}" tabindex="-1">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('settings.email-config.test', $cfg) }}">
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
        @else
            <div class="modal fade" id="addIntSender{{ $catKey }}" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('settings.email-config.store') }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title">Configure Sender — {{ $catInfo['label'] }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                @include('settings.email-config._form', ['config' => null, 'categories' => $categories, 'isInternal' => true, 'fixedCategory' => $catKey, 'fixedCatLabel' => $catInfo['label']])
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-warning">Save Sender</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
    </div>

    <hr class="my-4">

    {{-- ─── Notification Recipients (per internal category) ──────────────── --}}
    <h6 class="fw-semibold mb-1"><i class="bi bi-people-fill me-2 text-warning"></i>Notification Recipients</h6>
    <p class="text-muted small mb-3">
        Who among yard staff receives each internal notification category (TO / CC).
    </p>

    @php
    $internalEmails = \App\Models\InternalNotificationEmail::orderBy('category')
        ->orderBy('sort_order')->orderBy('address_type')
        ->get()->groupBy('category');
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
                                <button class="btn btn-xs btn-outline-secondary"
                                        data-bs-toggle="modal"
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
                                            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                                   id="intActive{{ $rec->id }}" {{ $rec->is_active ? 'checked' : '' }}>
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

</div>{{-- /internal-tab-pane --}}

{{-- ════════════════════════════════════════════════════════════════════════
     TAB 2 — EXTERNAL / CUSTOMER EMAILS
     Mailer driver configs (SMTP/Mailgun/SendGrid) + Customer contact directory
     ════════════════════════════════════════════════════════════════════════ --}}
<div class="tab-pane fade" id="external-tab-pane"
     role="tabpanel" aria-labelledby="external-tab" tabindex="0">

    {{-- ─── Mailer Configurations ─────────────────────────────────────── --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h6 class="mb-0 fw-semibold"><i class="bi bi-gear me-1 text-primary"></i>Mailer Configurations</h6>
            <p class="text-muted small mb-0">SMTP / Mailgun / SendGrid driver settings and common CC addresses per category.</p>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addConfigModal">
            <i class="bi bi-plus-lg me-1"></i>Add Configuration
        </button>
    </div>

    @php
    $driverIcons  = ['smtp' => 'bi-server', 'mailgun' => 'bi-lightning', 'sendgrid' => 'bi-send'];
    $driverColors = ['smtp' => 'primary', 'mailgun' => 'warning', 'sendgrid' => 'info'];
    $catColors    = ['estimate' => 'primary', 'invoice' => 'success', 'stock_report' => 'secondary', 'movement_report' => 'dark', 'general' => 'light text-dark border'];
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
                @if(!empty($config->cc_emails))
                    <div class="col-12 mt-1">
                        <span class="text-muted">Common CC:</span>
                        @foreach($config->cc_emails as $ccEmail)
                            <span class="badge bg-light text-secondary border ms-1">{{ $ccEmail }}</span>
                        @endforeach
                    </div>
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
    <div class="card content-card mb-3">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-envelope-x fs-1 d-block mb-2"></i>
            No email configurations yet. Add one to enable email sending.
        </div>
    </div>
    @endforelse

    {{-- ─── Customer Email Contacts Directory ─────────────────────────── --}}
    <div class="mt-4 pt-3 border-top">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div>
                <h6 class="mb-0 fw-semibold"><i class="bi bi-address-book me-1 text-warning"></i>Customer Email Contacts</h6>
                <p class="text-muted small mb-0">
                    Per-customer, per-category TO / CC addresses. Search for a customer to view and manage their contacts inline.
                </p>
            </div>
        </div>

        {{-- Customer search form --}}
        <form method="GET" action="{{ route('settings.email-config.index') }}" class="mb-3">
            <input type="hidden" name="tab" value="external">
            <div class="d-flex gap-2 align-items-center" style="max-width:520px;">
                <input type="text" name="customer_search"
                       class="form-control form-control-sm"
                       placeholder="Search customer by name or code…"
                       value="{{ $customerSearch ?: ($selectedCustomer?->name ?? '') }}">
                <button type="submit" class="btn btn-sm btn-outline-secondary flex-shrink-0">
                    <i class="bi bi-search me-1"></i>Search
                </button>
                @if($customerSearch || $selectedCustomer)
                <a href="{{ route('settings.email-config.index', ['tab' => 'external']) }}"
                   class="btn btn-sm btn-outline-danger flex-shrink-0" title="Clear">
                    <i class="bi bi-x-lg"></i>
                </a>
                @endif
            </div>
        </form>

        {{-- Search results list --}}
        @if($customerSearch && $customerResults->isEmpty())
            <p class="text-muted small fst-italic">No customers found matching "{{ $customerSearch }}".</p>
        @elseif($customerResults->isNotEmpty())
            <div class="list-group mb-3" style="max-width:520px;">
                @foreach($customerResults as $cust)
                <a href="{{ route('settings.email-config.index', ['customer_id' => $cust->id, 'tab' => 'external']) }}"
                   class="list-group-item list-group-item-action py-2 small d-flex align-items-center gap-2
                          {{ ($selectedCustomer?->id === $cust->id) ? 'active' : '' }}">
                    <i class="bi bi-building"></i>
                    <span class="fw-semibold">{{ $cust->name }}</span>
                    @if($cust->code)
                        <span class="ms-1 {{ ($selectedCustomer?->id === $cust->id) ? 'text-white-50' : 'text-muted' }}">{{ $cust->code }}</span>
                    @endif
                </a>
                @endforeach
            </div>
        @endif

        {{-- Selected customer contacts panel --}}
        @if($selectedCustomer)
        @php
            $emailContactCategories = config('email_categories.customer');
            $emailContacts          = $selectedCustomer->emailContacts->groupBy('category');
        @endphp
        <div class="card content-card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>
                    <i class="bi bi-envelope-at me-2 text-warning"></i>
                    Email Contacts &mdash; <strong>{{ $selectedCustomer->name }}</strong>
                </span>
                <a href="{{ route('customers.show', $selectedCustomer) }}"
                   class="btn btn-xs btn-outline-secondary" target="_blank" title="Open customer profile">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Customer Profile
                </a>
            </div>
            <div class="card-body p-0">
                @foreach($emailContactCategories as $catKey => $catInfo)
                <div class="{{ !$loop->last ? 'border-bottom' : '' }}">
                    <div class="d-flex align-items-center gap-2 px-3 py-2 bg-light">
                        <i class="bi {{ $catInfo['icon'] }} text-{{ $catInfo['color'] }} small"></i>
                        <span class="fw-semibold small">{{ $catInfo['label'] }}</span>
                        <span class="ms-auto badge bg-light text-muted border small">
                            {{ $emailContacts->get($catKey, collect())->where('is_active', true)->count() }} active
                        </span>
                    </div>
                    <table class="table table-sm mb-0 small align-middle">
                        <tbody>
                            @forelse($emailContacts->get($catKey, collect()) as $contact)
                            <tr>
                                <td class="ps-4" style="width:40%">
                                    <span class="{{ $contact->is_active ? '' : 'text-muted text-decoration-line-through' }}">
                                        {{ $contact->email }}
                                    </span>
                                </td>
                                <td class="text-muted" style="width:28%">{{ $contact->label ?: '—' }}</td>
                                <td class="text-center" style="width:14%">
                                    <span class="badge bg-{{ $contact->address_type === 'to' ? 'primary' : 'secondary' }}">
                                        {{ strtoupper($contact->address_type) }}
                                    </span>
                                </td>
                                <td class="text-end pe-3" style="width:18%">
                                    <div class="d-flex justify-content-end gap-1">
                                        <button class="btn btn-xs btn-outline-secondary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editCustContact{{ $contact->id }}"
                                                title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="{{ route('customers.email-contacts.destroy', [$selectedCustomer, $contact]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-outline-danger"
                                                    data-confirm="Remove this contact?"
                                                    data-confirm-title="Remove Contact"
                                                    data-confirm-class="btn-danger"
                                                    data-confirm-label="Remove">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            {{-- Edit modal --}}
                            <div class="modal fade" id="editCustContact{{ $contact->id }}" tabindex="-1">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content">
                                        <form method="POST" action="{{ route('customers.email-contacts.update', [$selectedCustomer, $contact]) }}">
                                            @csrf @method('PATCH')
                                            <div class="modal-header py-2">
                                                <h6 class="modal-title">Edit Contact</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body py-2">
                                                <div class="mb-2">
                                                    <label class="form-label form-label-sm">Email</label>
                                                    <input type="email" name="email" class="form-control form-control-sm" value="{{ $contact->email }}" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label form-label-sm">Label / Name</label>
                                                    <input type="text" name="label" class="form-control form-control-sm" value="{{ $contact->label }}" placeholder="Optional">
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label form-label-sm">Type</label>
                                                    <select name="address_type" class="form-select form-select-sm">
                                                        <option value="to" {{ $contact->address_type === 'to' ? 'selected' : '' }}>TO — Primary recipient</option>
                                                        <option value="cc" {{ $contact->address_type === 'cc' ? 'selected' : '' }}>CC — Copy</option>
                                                    </select>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                                           id="cActive{{ $contact->id }}" {{ $contact->is_active ? 'checked' : '' }}>
                                                    <label class="form-check-label small" for="cActive{{ $contact->id }}">Active</label>
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
                                <td colspan="4" class="ps-4 text-muted small fst-italic py-2">No contacts configured</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{-- Add contact inline form --}}
                    <form method="POST" action="{{ route('customers.email-contacts.store', $selectedCustomer) }}"
                          class="d-flex gap-2 align-items-end flex-wrap px-3 py-2 border-top bg-white">
                        @csrf
                        <input type="hidden" name="category" value="{{ $catKey }}">
                        <div style="flex:2;min-width:150px;">
                            <label class="form-label form-label-sm mb-1">Email</label>
                            <input type="email" name="email" class="form-control form-control-sm" placeholder="contact@company.com" required>
                        </div>
                        <div style="flex:1;min-width:90px;">
                            <label class="form-label form-label-sm mb-1">Label</label>
                            <input type="text" name="label" class="form-control form-control-sm" placeholder="Name">
                        </div>
                        <div style="min-width:85px;">
                            <label class="form-label form-label-sm mb-1">Type</label>
                            <select name="address_type" class="form-select form-select-sm">
                                <option value="to">TO</option>
                                <option value="cc">CC</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i></button>
                        </div>
                    </form>
                </div>
                @endforeach
            </div>
        </div>
        @endif{{-- /selectedCustomer --}}

        @if(!$selectedCustomer && !$customerSearch)
        <p class="text-muted small fst-italic">
            <i class="bi bi-info-circle me-1"></i>
            Search for a customer above to view and manage their per-category email recipient lists.
            You can also manage contacts from the <a href="{{ route('customers.index') }}">Customer Profile</a>.
        </p>
        @endif
    </div>{{-- /customer contacts directory --}}

    {{-- Add Config Modal --}}
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

</div>{{-- /external-tab-pane --}}

</div>{{-- /tab-content --}}

@endsection

@push('styles')
<style>
    .btn-xs { padding: .18rem .5rem; font-size: .72rem; line-height: 1.2; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    // ── Driver-specific field toggle ──────────────────────────────────────
    document.querySelectorAll('[data-driver-toggle]').forEach(function (el) {
        el.addEventListener('change', function () {
            var form = this.closest('form');
            form.querySelectorAll('[data-driver-section]').forEach(function (s) { s.style.display = 'none'; });
            var target = form.querySelector('[data-driver-section="' + el.value + '"]');
            if (target) target.style.display = '';
        });
        // Initialize on page load
        var form = el.closest('form');
        form.querySelectorAll('[data-driver-section]').forEach(function (s) { s.style.display = 'none'; });
        var target = form.querySelector('[data-driver-section="' + el.value + '"]');
        if (target) target.style.display = '';
    });

    // ── Tab state: persist via URL query param ────────────────────────────
    var tabEls = document.querySelectorAll('#emailConfigTabs [data-bs-toggle="tab"]');
    tabEls.forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function (e) {
            var paneId = e.target.getAttribute('data-bs-target').replace('#', '').replace('-pane', '');
            var url = new URL(window.location.href);
            url.searchParams.set('tab', paneId);
            // preserve customer_id / customer_search if present
            history.replaceState(null, '', url.toString());
        });
    });

    // ── Auto-activate External tab from URL ───────────────────────────────
    var params = new URLSearchParams(window.location.search);
    var shouldOpenExternal = params.get('tab') === 'external'
        || params.has('customer_id')
        || params.has('customer_search');

    if (shouldOpenExternal) {
        var extBtn = document.querySelector('#external-tab');
        if (extBtn) { bootstrap.Tab.getOrCreateInstance(extBtn).show(); }
    }
})();
</script>
@endpush
