<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ $companySetting?->company_name ?? 'CYM' }}</title>
    @if($companySetting?->icon_url)
    <link rel="icon" type="image/png" href="{{ $companySetting->icon_url }}">
    @endif

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <!-- Bootstrap Datepicker (date-only inputs) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css" rel="stylesheet">
    <!-- Air Datepicker v3 (datetime inputs — viewport-aware, no Bootstrap 5 conflicts) -->
    <link href="https://cdn.jsdelivr.net/npm/air-datepicker@3.5.3/air-datepicker.css" rel="stylesheet">
    <style>
        /* ── Bootstrap Datepicker — bordered, system-blue accents ────────── */
        /* Neutralise Bootstrap 5's .dropdown-menu base so it doesn't hide the calendar */
        .datepicker.dropdown-menu {
            display: block;
            min-width: 0;
            padding: 0;
            margin: 0;
        }
        .datepicker-dropdown {
            border: 1px solid #90c8f9 !important;
            border-radius: 8px !important;
            box-shadow: 0 6px 20px rgba(33,150,243,.15) !important;
            font-family: inherit;
            font-size: .875rem;
            z-index: 1060 !important;
            position: absolute !important;
        }
        .datepicker.datepicker-dropdown::before,
        .datepicker.datepicker-dropdown::after { display: none !important; }
        .datepicker table tr td.today:not(.active) {
            background: #e3f2fd; border-color: #90c8f9; color: #1565C0; font-weight: 600;
        }
        .datepicker table tr td.active,
        .datepicker table tr td.active:hover,
        .datepicker table tr td.active.disabled,
        .datepicker table tr td.active.disabled:hover {
            background: #2196F3 !important; border-color: #2196F3 !important;
            color: #fff !important; text-shadow: none;
        }
        .datepicker table tr td:hover:not(.active),
        .datepicker table tr td span:hover:not(.active) { background: #d0e8fd; color: #1565C0; }
        .datepicker table tr th.switch:hover,
        .datepicker table tr th.prev:hover,
        .datepicker table tr th.next:hover { background: #e3f2fd; color: #1565C0; }

        /* ── Air Datepicker v3 — match Bootstrap Datepicker border style ── */
        .air-datepicker-global-container {
            z-index: 9999 !important;
        }
        .air-datepicker {
            /* override the CSS variable Air Datepicker uses for its border */
            --adp-border-color: #90c8f9;
            border: 1px solid #90c8f9 !important;
            border-radius: 8px !important;
            box-shadow: 0 6px 20px rgba(33,150,243,.15) !important;
            font-family: inherit !important;
            font-size: .875rem !important;
        }
        .air-datepicker-cell.-selected-,
        .air-datepicker-cell.-selected-.-focus- {
            background: #2196F3 !important;
            color: #fff !important;
        }
        .air-datepicker-cell.-current- {
            color: #1565C0 !important;
        }
        .air-datepicker-cell:hover:not(.-selected-) {
            background: #d0e8fd !important;
            color: #1565C0 !important;
        }
        .air-datepicker-time--current-hours:after,
        .air-datepicker-time--current-minutes:after {
            background: #2196F3 !important;
        }
        .air-datepicker-nav--action:hover {
            background: #e3f2fd !important;
            color: #1565C0 !important;
        }
    </style>

    <style>
        :root {
            --sidebar-width: 260px;
            --topbar-height: 60px;
            --primary:   #2196F3;
            --dark-bg:   #1565C0;
            --dark-side: #0d47a1;

            /* Bootstrap primary colour override — #2196F3 */
            --bs-primary:                  #2196F3;
            --bs-primary-rgb:              33, 150, 243;
            --bs-primary-text-emphasis:    #0a4272;
            --bs-primary-bg-subtle:        #d0e8fd;
            --bs-primary-border-subtle:    #90c8f9;
            --bs-link-color:               #2196F3;
            --bs-link-color-rgb:           33, 150, 243;
            --bs-link-hover-color:         #1976D2;
            --bs-link-hover-color-rgb:     25, 118, 210;
        }

        .btn-primary {
            --bs-btn-bg:                   #2196F3;
            --bs-btn-border-color:         #2196F3;
            --bs-btn-hover-bg:             #1a88e7;
            --bs-btn-hover-border-color:   #1981dc;
            --bs-btn-active-bg:            #1976D2;
            --bs-btn-active-border-color:  #1976D2;
            --bs-btn-disabled-bg:          #2196F3;
            --bs-btn-disabled-border-color:#2196F3;
            --bs-btn-focus-shadow-rgb:     33, 150, 243;
        }
        .btn-outline-primary {
            --bs-btn-color:                #2196F3;
            --bs-btn-border-color:         #2196F3;
            --bs-btn-hover-bg:             #2196F3;
            --bs-btn-hover-border-color:   #2196F3;
            --bs-btn-active-bg:            #2196F3;
            --bs-btn-active-border-color:  #2196F3;
        }

        body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; }

        /* ── Sidebar ── */
        #sidebar {
            position: fixed;
            top: 0; left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--dark-bg);
            display: flex;
            flex-direction: column;
            z-index: 1040;
            transition: width .3s;
            overflow-x: hidden;
        }
        #sidebar.collapsed { width: 68px; }

        .sidebar-brand {
            height: var(--topbar-height);
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 18px;
            background: var(--dark-side);
            color: #fff;
            text-decoration: none;
            white-space: nowrap;
            overflow: hidden;
        }
        .sidebar-brand .brand-icon { font-size: 1.5rem; color: var(--primary); flex-shrink: 0; }
        .sidebar-brand .brand-text { font-size: .9rem; font-weight: 700; line-height: 1.2; }
        .sidebar-brand .brand-text small { font-weight: 400; color: rgba(255,255,255,.55); font-size: .7rem; }

        .sidebar-nav { flex: 1; overflow-y: auto; padding: 8px 0; }
        .sidebar-nav::-webkit-scrollbar { width: 4px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: #1976D2; border-radius: 2px; }

        /* ══ Level 1 — Section headers ══════════════════════════════════
           Light white overlay → clear chapter divider, stays bright.
           Hover adds a little more white (consistent lightening).       */
        .nav-section-label {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .09em;
            color: rgba(255,255,255,.95);
            text-transform: uppercase;
            padding: 9px 20px;
            gap: 10px;
            white-space: nowrap;
            overflow: hidden;
            background: rgba(255,255,255,.10);
            border-top: 1px solid rgba(255,255,255,.08);
            /* button reset */
            display: flex;
            align-items: center;
            width: 100%;
            border-left: none;
            border-right: none;
            border-bottom: none;
            text-align: left;
            cursor: pointer;
            transition: color .15s, background .15s;
        }
        .nav-section-label:hover {
            color: #fff;
            background: rgba(255,255,255,.17);
        }
        .nav-section-label[aria-expanded="true"] {
            background: rgba(255,255,255,.10);
        }
        .nav-section-label .section-icon {
            font-size: 1rem;
            flex-shrink: 0;
            color: #90caf9;
            transition: color .15s;
        }
        .nav-section-label:hover .section-icon,
        .nav-section-label[aria-expanded="true"] .section-icon { color: #bbdefb; }
        .nav-section-label .section-chevron {
            margin-left: auto;
            font-size: .65rem;
            transition: transform .2s;
            flex-shrink: 0;
            color: rgba(255,255,255,.50);
        }
        .nav-section-label[aria-expanded="false"] .section-chevron { transform: rotate(-90deg); }
        #sidebar.collapsed .nav-section-label {
            visibility: hidden;
            pointer-events: none;
        }

        /* ══ Level 2 — Sub-group toggles ════════════════════════════════
           Smaller white overlay → sits between L1 and the bare sidebar.
           Hover adds a little white (consistent with L1 and L3).       */
        .nav-sub-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 20px;
            color: rgba(255,255,255,.72);
            font-size: .8rem;
            font-weight: 600;
            background: rgba(255,255,255,.05);
            border: none;
            border-left: 3px solid rgba(255,255,255,.14);
            width: 100%;
            text-align: left;
            cursor: pointer;
            transition: color .15s, background .15s, border-color .15s;
            white-space: nowrap;
            overflow: hidden;
        }
        .nav-sub-toggle:hover {
            color: rgba(255,255,255,.92);
            background: rgba(255,255,255,.11);
            border-left-color: rgba(255,255,255,.35);
        }
        .nav-sub-toggle[aria-expanded="true"] {
            color: rgba(255,255,255,.95);
            background: rgba(33,150,243,.15);
            border-left-color: #64b5f6;
        }
        .nav-sub-toggle i.nav-sub-icon {
            font-size: .9rem;
            flex-shrink: 0;
            min-width: 20px;
            text-align: center;
            color: rgba(255,255,255,.45);
            transition: color .15s;
        }
        .nav-sub-toggle:hover i.nav-sub-icon,
        .nav-sub-toggle[aria-expanded="true"] i.nav-sub-icon { color: rgba(255,255,255,.80); }
        .nav-sub-toggle .sub-chevron {
            margin-left: auto;
            font-size: .58rem;
            transition: transform .2s;
            flex-shrink: 0;
            color: rgba(255,255,255,.35);
        }
        .nav-sub-toggle[aria-expanded="false"] .sub-chevron { transform: rotate(-90deg); }

        /* ══ Level 3 — Leaf nav items ════════════════════════════════════
           Transparent background → most recessed, the bare sidebar base.
           Hover adds a subtle white tint (consistent lightening direction). */
        .nav-item a.nav-link {
            color: rgba(255,255,255,.62);
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 9px 20px;
            border-left: 3px solid transparent;
            transition: all .18s;
            white-space: nowrap;
            overflow: hidden;
        }
        .nav-item a.nav-link i { font-size: 1rem; flex-shrink: 0; min-width: 22px; text-align: center; }
        .nav-item a.nav-link span { font-size: .83rem; }
        .nav-item a.nav-link:hover {
            color: rgba(255,255,255,.95);
            background: rgba(255,255,255,.10);
            border-left-color: rgba(255,255,255,.28);
        }
        .nav-item a.nav-link.active {
            color: #fff;
            background: rgba(255,255,255,.16);
            border-left-color: var(--primary);
            font-weight: 600;
        }

        .nav-item.sub-item a.nav-link { padding-left: 42px; }
        #sidebar.collapsed .nav-sub-toggle { display: none; }
        #sidebar.collapsed .nav-item.sub-item a.nav-link { padding-left: 20px; }

        .sidebar-footer {
            padding: 12px 20px;
            background: var(--dark-side);
            color: rgba(255,255,255,.45);
            font-size: .75rem;
            white-space: nowrap;
            overflow: hidden;
        }

        /* ── Topbar ── */
        #topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--topbar-height);
            background: #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
            display: flex;
            align-items: center;
            padding: 0 20px;
            z-index: 1030;
            transition: left .3s;
        }
        #topbar.expanded { left: 68px; }

        /* ── Main content ── */
        #main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--topbar-height);
            padding: 24px;
            min-height: calc(100vh - var(--topbar-height));
            transition: margin-left .3s;
        }
        #main-content.expanded { margin-left: 68px; }

        /* ── Cards ── */
        .stat-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
            overflow: hidden;
        }
        .stat-card .card-icon {
            width: 54px; height: 54px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
        }

        .content-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
        }
        .content-card .card-header {
            background: #fff;
            border-bottom: 1px solid #eee;
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600;
        }

        /* ── Badges / Status ── */
        .badge-status { font-size: .72rem; padding: .35em .65em; border-radius: 20px; }

        /* ── Page header ── */
        .page-header { margin-bottom: 24px; }
        .page-header h4 { font-weight: 700; color: #1565C0; margin: 0; }
        .page-header .breadcrumb { font-size: .8rem; margin: 0; }
        @media (max-width: 767.98px) {
            .page-header {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 8px;
            }
        }

        /* ── Utility ── */
        .avatar-sm {
            width: 34px; height: 34px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .85rem; font-weight: 700;
        }

        /* ── Status Nav Tabs ── */
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
            gap: 2px;
        }
        .nav-tabs .nav-link {
            font-size: .82rem;
            font-weight: 500;
            color: #495057;
            background-color: #e9ecef;
            border: 1px solid #dee2e6;
            border-bottom: none;
            border-radius: 6px 6px 0 0;
            padding: .45rem 1rem;
            transition: color .15s, background-color .15s, border-color .15s;
        }
        .nav-tabs .nav-link:hover:not(.active) {
            color: #1565c0;
            background-color: #dbeafe;
            border-color: #90caf9 #90caf9 transparent;
        }
        .nav-tabs .nav-link.active {
            color: #0d47a1;
            font-weight: 700;
            background-color: #e3f2fd;
            border-color: #90caf9;
            border-top: 3px solid var(--primary);
            padding-top: calc(.45rem - 2px);
        }

        /* ── Filter Panel (card that sits directly below status tabs) ── */
        .filter-panel {
            background-color: #fff !important;
            border: 1px solid #dee2e6 !important;
            border-top: 3px solid var(--primary) !important;
            border-radius: 0 12px 12px 12px !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .07) !important;
        }
        .filter-panel .form-control,
        .filter-panel .form-select,
        .filter-panel .input-group-text {
            background-color: #fff;
            color: #212529;
            border-color: #ced4da;
        }
        .filter-panel .form-control::placeholder { color: #6c757d; }
        .filter-panel label,
        .filter-panel .form-label { color: #212529; }

        @media (max-width: 768px) {
            #sidebar {
                width: var(--sidebar-width) !important;
                transform: translateX(-100%);
                transition: transform .3s !important;
                box-shadow: none;
            }
            #sidebar.mobile-open {
                transform: translateX(0);
                box-shadow: 4px 0 24px rgba(0,0,0,.4);
            }
            #topbar { left: 0 !important; }
            #main-content { margin-left: 0 !important; padding: 16px; }
        }

        /* Backdrop — shown behind the mobile drawer */
        #sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.45);
            z-index: 1039;
            cursor: pointer;
        }
        #sidebar-backdrop.show { display: block; }
    </style>
    <style>
        /* Select2 code+name formatting */
        .s2-opt-row { display:flex; align-items:center; gap:8px; white-space:nowrap; overflow:hidden; }
        .s2-code-chip { display:inline-block; background:#dbeafe; color:#1d4ed8; border-radius:4px; padding:1px 6px; font-family:monospace; font-size:.72rem; font-weight:700; flex-shrink:0; }
        .s2-chip-reefer { background:#ccfbf1; color:#0d9488; }
        .s2-code-label { font-size:.85rem; color:#374151; overflow:hidden; text-overflow:ellipsis; }
        .select2-selection__rendered .s2-code-label { font-size:.9rem; }
        .badge-reefer { background-color:#ccfbf1 !important; color:#0d9488 !important; border:1px solid #99f6e4 !important; }
        /* Select2 grade colour dot */
        .s2-grade-dot { display:inline-block; width:10px; height:10px; border-radius:50%; flex-shrink:0; border:1px solid rgba(0,0,0,.12); }
    </style>
    @stack('styles')
</head>
<body>

<!-- ══════════════════════════════════════
     SIDEBAR
══════════════════════════════════════ -->
<nav id="sidebar">

    <a href="{{ route('dashboard') }}" class="sidebar-brand">
        @if($companySetting?->product_icon_url)
            <img src="{{ $companySetting->product_icon_url }}" alt="Product Icon"
                 style="width:32px; height:32px; object-fit:contain; border-radius:6px; flex-shrink:0;">
        @else
            <i class="bi bi-boxes brand-icon"></i>
        @endif
        <span class="brand-text">{{ $companySetting?->company_name ?? 'CYM System' }}</span>
    </a>

    <div class="sidebar-nav">

        {{-- ── OVERVIEW ── --}}
        <button class="nav-section-label"
                data-bs-toggle="collapse" data-bs-target="#nav-section-overview"
                aria-expanded="false" aria-controls="nav-section-overview">
            <i class="bi bi-speedometer2 section-icon"></i><span>Overview</span><i class="bi bi-chevron-down section-chevron"></i>
        </button>
        <div class="collapse" id="nav-section-overview">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}"
                       class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2"></i><span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    @php
                        $pendingApprovalCount = \App\Models\ApprovalAction::query()
                            ->where('status', 'pending')
                            ->whereHas('approvalRequest', fn($q) => $q->where('status', 'pending'))
                            ->count();
                    @endphp
                    <a href="{{ route('approvals.pending') }}"
                       class="nav-link {{ request()->routeIs('approvals.*') ? 'active' : '' }}">
                        <i class="bi bi-check2-circle"></i>
                        <span>My Approvals</span>
                        @if($pendingApprovalCount > 0)
                        <span class="badge bg-warning text-dark ms-auto">{{ $pendingApprovalCount }}</span>
                        @endif
                    </a>
                </li>
            </ul>
        </div>

        {{-- ── ADMINISTRATION ── --}}
        <button class="nav-section-label"
                data-bs-toggle="collapse" data-bs-target="#nav-section-admin"
                aria-expanded="false" aria-controls="nav-section-admin">
            <i class="bi bi-person-lock section-icon"></i><span>Administration</span><i class="bi bi-chevron-down section-chevron"></i>
        </button>
        <div class="collapse" id="nav-section-admin">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="{{ route('users.index') }}"
                       class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <i class="bi bi-people"></i><span>User Management</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('customers.index') }}"
                       class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}">
                        <i class="bi bi-person-badge"></i><span>Customers</span>
                    </a>
                </li>
            </ul>
        </div>

        {{-- ── GUARD POST (optional feature) ── --}}
        @if($companySetting?->enable_guard_post && in_array(auth()->user()->role, ['security_officer','gate_officer','yard_supervisor','administrator','system_administrator']))
        @php $guardPostActive = request()->routeIs('guard-post.*'); @endphp
        <button class="nav-section-label"
                data-bs-toggle="collapse" data-bs-target="#nav-section-guard-post"
                aria-expanded="{{ $guardPostActive ? 'true' : 'false' }}"
                aria-controls="nav-section-guard-post">
            <i class="bi bi-shield-check section-icon"></i><span>Guard Post</span><i class="bi bi-chevron-down section-chevron"></i>
        </button>
        <div class="collapse {{ $guardPostActive ? 'show' : '' }}" id="nav-section-guard-post">
            <ul class="nav flex-column">
                @if(auth()->user()->isSecurityOfficer())
                <li class="nav-item">
                    <a href="{{ route('guard-post.index') }}"
                       class="nav-link {{ request()->routeIs('guard-post.index') || request()->routeIs('guard-post.create') || request()->routeIs('guard-post.status') ? 'active' : '' }}">
                        <i class="bi bi-camera"></i><span>Capture</span>
                    </a>
                </li>
                @else
                <li class="nav-item">
                    <a href="{{ route('guard-post.queue') }}"
                       class="nav-link {{ request()->routeIs('guard-post.queue') ? 'active' : '' }}">
                        @php
                            $pendingCaptures = \App\Models\GuardCapture::where('status','pending')->count();
                        @endphp
                        <i class="bi bi-list-check"></i><span>Capture Queue</span>
                        @if($pendingCaptures > 0)
                            <span class="badge bg-warning text-dark ms-auto">{{ $pendingCaptures }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('guard-post.create') }}"
                       class="nav-link {{ request()->routeIs('guard-post.create') ? 'active' : '' }}">
                        <i class="bi bi-camera"></i><span>New Capture</span>
                    </a>
                </li>
                @endif
            </ul>
        </div>
        @endif

        {{-- ── OPERATIONS ── --}}
        <button class="nav-section-label"
                data-bs-toggle="collapse" data-bs-target="#nav-section-operations"
                aria-expanded="false" aria-controls="nav-section-operations">
            <i class="bi bi-lightning-charge section-icon"></i><span>Operations</span><i class="bi bi-chevron-down section-chevron"></i>
        </button>
        <div class="collapse" id="nav-section-operations">
            {{-- Yard sub-group --}}
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-ops-yard"
                    aria-expanded="false" aria-controls="nav-sub-ops-yard">
                <i class="bi bi-geo nav-sub-icon"></i>
                <span>Yard</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse" id="nav-sub-ops-yard">
                <ul class="nav flex-column">
                    <li class="nav-item sub-item">
                        <a href="{{ route('yard.gate') }}"
                           class="nav-link {{ request()->routeIs('yard.gate*') ? 'active' : '' }}">
                            <i class="bi bi-box-arrow-in-right"></i><span>Gate In / Gate Out</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('yard.index') }}"
                           class="nav-link {{ request()->routeIs('yard.index') ? 'active' : '' }}">
                            <i class="bi bi-map"></i><span>Yard Overview</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('yard.storage') }}"
                           class="nav-link {{ request()->routeIs('yard.storage*') ? 'active' : '' }}">
                            <i class="bi bi-calculator"></i><span>Storage Calculator</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('yard.reefer.index') }}"
                           class="nav-link {{ request()->routeIs('yard.reefer.*') ? 'active' : '' }}">
                            <i class="bi bi-plug-fill"></i><span>Reefer Plug Sessions</span>
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Containers sub-group --}}
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-ops-containers"
                    aria-expanded="false" aria-controls="nav-sub-ops-containers">
                <i class="bi bi-boxes nav-sub-icon"></i>
                <span>Containers</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse" id="nav-sub-ops-containers">
                <ul class="nav flex-column">
                    <li class="nav-item sub-item">
                        <a href="{{ route('surveys.index') }}"
                           class="nav-link {{ request()->routeIs('surveys.*') ? 'active' : '' }}">
                            <i class="bi bi-card-checklist"></i><span>Surveys</span>
                        </a>
                    </li>
                </ul>
            </div>

            {{-- M&R sub-group --}}
            @php $mrOpsActive = request()->routeIs('estimates.*') || request()->routeIs('work-orders.*') || request()->routeIs('repair-invoices.*'); @endphp
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-ops-mr"
                    aria-expanded="{{ $mrOpsActive ? 'true' : 'false' }}"
                    aria-controls="nav-sub-ops-mr">
                <i class="bi bi-wrench-adjustable nav-sub-icon"></i>
                <span>M&amp;R</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse {{ $mrOpsActive ? 'show' : '' }}" id="nav-sub-ops-mr">
                <ul class="nav flex-column">
                    <li class="nav-item sub-item">
                        <a href="{{ route('estimates.index') }}"
                           class="nav-link {{ request()->routeIs('estimates.*') ? 'active' : '' }}">
                            <i class="bi bi-file-earmark-ruled"></i><span>Repair Estimates</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('work-orders.index') }}"
                           class="nav-link {{ request()->routeIs('work-orders.*') ? 'active' : '' }}">
                            <i class="bi bi-hammer"></i><span>Work Orders</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('repair-invoices.index') }}"
                           class="nav-link {{ request()->routeIs('repair-invoices.*') ? 'active' : '' }}">
                            <i class="bi bi-receipt"></i><span>Repair Invoices</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        {{-- ── BILLING ── --}}
        <button class="nav-section-label"
                data-bs-toggle="collapse" data-bs-target="#nav-section-billing"
                aria-expanded="false" aria-controls="nav-section-billing">
            <i class="bi bi-receipt-cutoff section-icon"></i><span>Billing</span><i class="bi bi-chevron-down section-chevron"></i>
        </button>
        <div class="collapse" id="nav-section-billing">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="{{ route('billing.index') }}"
                       class="nav-link {{ request()->routeIs('billing.*') && !request()->routeIs('billing.storage-handling.*') && !request()->routeIs('billing.reefer.*') ? 'active' : '' }}">
                        <i class="bi bi-file-earmark-text"></i><span>Storage Invoices</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('billing.storage-handling.index') }}"
                       class="nav-link {{ request()->routeIs('billing.storage-handling.*') ? 'active' : '' }}">
                        <i class="bi bi-file-earmark-richtext"></i><span>Storage &amp; Handling</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('billing.reefer.index') }}"
                       class="nav-link {{ request()->routeIs('billing.reefer.*') ? 'active' : '' }}">
                        <i class="bi bi-lightning-charge-fill"></i><span>Reefer Electricity</span>
                    </a>
                </li>
            </ul>
        </div>

        {{-- ── SETUP ── --}}
        <button class="nav-section-label"
                data-bs-toggle="collapse" data-bs-target="#nav-section-setup"
                aria-expanded="false" aria-controls="nav-section-setup">
            <i class="bi bi-tools section-icon"></i><span>Setup</span><i class="bi bi-chevron-down section-chevron"></i>
        </button>
        <div class="collapse" id="nav-section-setup">
            {{-- Containers (Equipment) sub-group --}}
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-setup-containers"
                    aria-expanded="false" aria-controls="nav-sub-setup-containers">
                <i class="bi bi-box-seam nav-sub-icon"></i>
                <span>Containers</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse" id="nav-sub-setup-containers">
                <ul class="nav flex-column">
                    <li class="nav-item sub-item">
                        <a href="{{ route('containers.index') }}"
                           class="nav-link {{ request()->routeIs('containers.*') ? 'active' : '' }}">
                            <i class="bi bi-boxes"></i><span>Container Master</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.equipment-types.index') }}"
                           class="nav-link {{ request()->routeIs('masters.equipment-types.*') ? 'active' : '' }}">
                            <i class="bi bi-box-seam"></i><span>Equipment Types</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.container-grades.index') }}"
                           class="nav-link {{ request()->routeIs('masters.container-grades.*') ? 'active' : '' }}">
                            <i class="bi bi-award"></i><span>Container Grades</span>
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Yard Configuration sub-group --}}
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-setup-yard"
                    aria-expanded="false" aria-controls="nav-sub-setup-yard">
                <i class="bi bi-grid-3x3-gap nav-sub-icon"></i>
                <span>Yard Configuration</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse" id="nav-sub-setup-yard">
                <ul class="nav flex-column">
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.zones.index') }}"
                           class="nav-link {{ request()->routeIs('masters.zones.*') ? 'active' : '' }}">
                            <i class="bi bi-grid-3x3-gap"></i><span>Storage Zones</span>
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Inspection sub-group --}}
            @php $inspectionActive = request()->routeIs('masters.checklist.*') || request()->routeIs('masters.damage-assessment-rules.*'); @endphp
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-setup-inspection"
                    aria-expanded="{{ $inspectionActive ? 'true' : 'false' }}"
                    aria-controls="nav-sub-setup-inspection">
                <i class="bi bi-clipboard-check nav-sub-icon"></i>
                <span>Inspection</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse {{ $inspectionActive ? 'show' : '' }}" id="nav-sub-setup-inspection">
                <ul class="nav flex-column">
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.checklist.index') }}"
                           class="nav-link {{ request()->routeIs('masters.checklist.*') ? 'active' : '' }}">
                            <i class="bi bi-list-check"></i><span>Checklist Items</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.damage-assessment-rules.index') }}"
                           class="nav-link {{ request()->routeIs('masters.damage-assessment-rules.*') ? 'active' : '' }}">
                            <i class="bi bi-journal-check"></i><span>Assessment Rules</span>
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Repair Categories sub-group --}}
            @php $repairCatActive = request()->routeIs('masters.repair-categories.*') || request()->routeIs('masters.repair-category-mappings.*'); @endphp
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-setup-repair-cat"
                    aria-expanded="{{ $repairCatActive ? 'true' : 'false' }}"
                    aria-controls="nav-sub-setup-repair-cat">
                <i class="bi bi-tags nav-sub-icon"></i>
                <span>Repair Categories</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse {{ $repairCatActive ? 'show' : '' }}" id="nav-sub-setup-repair-cat">
                <ul class="nav flex-column">
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.repair-categories.index') }}"
                           class="nav-link {{ request()->routeIs('masters.repair-categories.*') ? 'active' : '' }}">
                            <i class="bi bi-tags"></i><span>Categories</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.repair-category-mappings.index') }}"
                           class="nav-link {{ request()->routeIs('masters.repair-category-mappings.*') ? 'active' : '' }}">
                            <i class="bi bi-diagram-3"></i><span>Mapping Rules</span>
                        </a>
                    </li>
                </ul>
            </div>

            @php $mrCodesActive = request()->routeIs('masters.mr-codes.*') || request()->routeIs('masters.mr-charge-mappings.*'); @endphp
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-setup-mr-codes"
                    aria-expanded="{{ $mrCodesActive ? 'true' : 'false' }}"
                    aria-controls="nav-sub-setup-mr-codes">
                <i class="bi bi-wrench-adjustable-circle nav-sub-icon"></i>
                <span>M&amp;R Codes</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse {{ $mrCodesActive ? 'show' : '' }}" id="nav-sub-setup-mr-codes">
                <ul class="nav flex-column">
                    @php
                    $mrCodeIcons = [
                        'location'       => 'bi-geo-alt',
                        'component'      => 'bi-cpu',
                        'damage'         => 'bi-shield-exclamation',
                        'repair'         => 'bi-tools',
                        'material'       => 'bi-box-seam',
                        'responsibility' => 'bi-person-badge',
                    ];
                    @endphp
                    @foreach(\App\Models\MrCode::TYPES as $slug => $label)
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.mr-codes.index', $slug) }}"
                           class="nav-link {{ request()->routeIs('masters.mr-codes.*') && request()->route('mrCodeType') === $slug ? 'active' : '' }}">
                            <i class="bi {{ $mrCodeIcons[$slug] ?? 'bi-code-square' }}"></i><span>{{ $label }}</span>
                        </a>
                    </li>
                    @endforeach
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.mr-charge-mappings.index') }}"
                           class="nav-link {{ request()->routeIs('masters.mr-charge-mappings.*') ? 'active' : '' }}">
                            <i class="bi bi-arrow-left-right"></i><span>Charge Mappings</span>
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Tariffs sub-group --}}
            @php $tariffsActive = request()->routeIs('masters.storage-tariff.*') || request()->routeIs('masters.handling-tariff.*') || request()->routeIs('masters.mr-tariff.*') || request()->routeIs('masters.reefer-tariff.*'); @endphp
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-setup-tariffs"
                    aria-expanded="{{ $tariffsActive ? 'true' : 'false' }}"
                    aria-controls="nav-sub-setup-tariffs">
                <i class="bi bi-tags nav-sub-icon"></i>
                <span>Tariffs</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse {{ $tariffsActive ? 'show' : '' }}" id="nav-sub-setup-tariffs">
                <ul class="nav flex-column">
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.storage-tariff.index') }}"
                           class="nav-link {{ request()->routeIs('masters.storage-tariff.*') ? 'active' : '' }}">
                            <i class="bi bi-hdd-stack"></i><span>Storage</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.handling-tariff.index') }}"
                           class="nav-link {{ request()->routeIs('masters.handling-tariff.*') ? 'active' : '' }}">
                            <i class="bi bi-wrench"></i><span>Handling</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.mr-tariff.index') }}"
                           class="nav-link {{ request()->routeIs('masters.mr-tariff.*') ? 'active' : '' }}">
                            <i class="bi bi-tools"></i><span>M&amp;R</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.reefer-tariff.index') }}"
                           class="nav-link {{ request()->routeIs('masters.reefer-tariff.*') ? 'active' : '' }}">
                            <i class="bi bi-plug-fill"></i><span>Reefer Electricity</span>
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Customer sub-group --}}
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-setup-customer"
                    aria-expanded="{{ request()->routeIs('masters.customer-types.*') ? 'true' : 'false' }}"
                    aria-controls="nav-sub-setup-customer">
                <i class="bi bi-person-badge nav-sub-icon"></i>
                <span>Customer</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse {{ request()->routeIs('masters.customer-types.*') ? 'show' : '' }}" id="nav-sub-setup-customer">
                <ul class="nav flex-column">
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.customer-types.index') }}"
                           class="nav-link {{ request()->routeIs('masters.customer-types.*') ? 'active' : '' }}">
                            <i class="bi bi-tags"></i><span>Customer Types</span>
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Invoice sub-group --}}
            @php $invoiceActive = request()->routeIs('masters.tax-codes.*') || request()->routeIs('masters.charge-codes.*') || request()->routeIs('masters.currencies.*') || request()->routeIs('masters.exchange-rates.*'); @endphp
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-setup-invoice"
                    aria-expanded="{{ $invoiceActive ? 'true' : 'false' }}"
                    aria-controls="nav-sub-setup-invoice">
                <i class="bi bi-receipt nav-sub-icon"></i>
                <span>Invoice</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse {{ $invoiceActive ? 'show' : '' }}" id="nav-sub-setup-invoice">
                <ul class="nav flex-column">
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.charge-codes.index') }}"
                           class="nav-link {{ request()->routeIs('masters.charge-codes.*') ? 'active' : '' }}">
                            <i class="bi bi-tag"></i><span>Charge Codes</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.tax-codes.index') }}"
                           class="nav-link {{ request()->routeIs('masters.tax-codes.*') ? 'active' : '' }}">
                            <i class="bi bi-percent"></i><span>Tax Codes</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.currencies.index') }}"
                           class="nav-link {{ request()->routeIs('masters.currencies.*') ? 'active' : '' }}">
                            <i class="bi bi-currency-exchange"></i><span>Currency Types</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.exchange-rates.index') }}"
                           class="nav-link {{ request()->routeIs('masters.exchange-rates.*') ? 'active' : '' }}">
                            <i class="bi bi-arrow-left-right"></i><span>Exchange Rates</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        {{-- ── REPORTS ── --}}
        <button class="nav-section-label"
                data-bs-toggle="collapse" data-bs-target="#nav-section-reports"
                aria-expanded="false" aria-controls="nav-section-reports">
            <i class="bi bi-graph-up-arrow section-icon"></i><span>Reports</span><i class="bi bi-chevron-down section-chevron"></i>
        </button>
        <div class="collapse" id="nav-section-reports">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="{{ route('reports.inventory') }}"
                       class="nav-link {{ request()->routeIs('reports.inventory') ? 'active' : '' }}">
                        <i class="bi bi-bar-chart-line"></i><span>Inventory</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('reports.daily-movements') }}"
                       class="nav-link {{ request()->routeIs('reports.daily-movements') ? 'active' : '' }}">
                        <i class="bi bi-calendar-week"></i><span>Daily Movements</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('reports.billing') }}"
                       class="nav-link {{ request()->routeIs('reports.billing') ? 'active' : '' }}">
                        <i class="bi bi-receipt"></i><span>Billing</span>
                    </a>
                </li>
            </ul>
        </div>

        {{-- ── SETTINGS ── --}}
        @php $settingsOpen = request()->routeIs('settings.*') || request()->routeIs('access-control.*'); @endphp
        <button class="nav-section-label"
                data-bs-toggle="collapse" data-bs-target="#nav-section-settings"
                aria-expanded="{{ $settingsOpen ? 'true' : 'false' }}" aria-controls="nav-section-settings">
            <i class="bi bi-gear-wide section-icon"></i><span>Settings</span><i class="bi bi-chevron-down section-chevron"></i>
        </button>
        <div class="collapse {{ $settingsOpen ? 'show' : '' }}" id="nav-section-settings">

            {{-- Access Control --}}
            @can('access-control.view')
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="{{ route('access-control.roles.index') }}"
                       class="nav-link {{ request()->routeIs('access-control.roles.*') ? 'active' : '' }}">
                        <i class="bi bi-shield-lock"></i><span>Roles & Permissions</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('access-control.users.index') }}"
                       class="nav-link {{ request()->routeIs('access-control.users.*') ? 'active' : '' }}">
                        <i class="bi bi-person-badge"></i><span>User Access</span>
                    </a>
                </li>
            </ul>
            @endcan

            {{-- Configuration sub-group --}}
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-settings-config"
                    aria-expanded="false" aria-controls="nav-sub-settings-config">
                <i class="bi bi-sliders nav-sub-icon"></i>
                <span>Configuration</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse" id="nav-sub-settings-config">
                <ul class="nav flex-column">
                    @if(auth()->user()->isSuperUser())
                    <li class="nav-item sub-item">
                        <a href="{{ route('settings.index') }}"
                           class="nav-link {{ request()->routeIs('settings.index') || request()->routeIs('settings.update') ? 'active' : '' }}">
                            <i class="bi bi-gear"></i><span>System Settings</span>
                        </a>
                    </li>
                    @endif
                    @if(auth()->user()->isSystemAdmin())
                    <li class="nav-item sub-item">
                        <a href="{{ route('settings.company.index') }}"
                           class="nav-link {{ request()->routeIs('settings.company.*') ? 'active' : '' }}">
                            <i class="bi bi-building"></i><span>Company Settings</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('settings.email-config.index') }}"
                           class="nav-link {{ request()->routeIs('settings.email-config.*') ? 'active' : '' }}">
                            <i class="bi bi-envelope-at"></i><span>Email Config</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('settings.countries.index') }}"
                           class="nav-link {{ request()->routeIs('settings.countries.*') ? 'active' : '' }}">
                            <i class="bi bi-globe"></i><span>Country List</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('settings.cloud-storage.index') }}"
                           class="nav-link {{ request()->routeIs('settings.cloud-storage.*') ? 'active' : '' }}">
                            <i class="bi bi-cloud-upload"></i><span>Storage</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('settings.approval-workflows.index') }}"
                           class="nav-link {{ request()->routeIs('settings.approval-workflows.*') ? 'active' : '' }}">
                            <i class="bi bi-diagram-3"></i><span>Approval Workflows</span>
                        </a>
                    </li>
                    @endif
                </ul>
            </div>
        </div>

    </div><!-- /sidebar-nav -->

    <div class="sidebar-footer">
        <div style="font-size:.7rem;">
            &copy; {{ date('Y') }} {{ $companySetting?->software_provider ?? 'CYM' }}
        </div>
    </div>

</nav>
<div id="sidebar-backdrop"></div>

<!-- ══════════════════════════════════════
     TOPBAR
══════════════════════════════════════ -->
<header id="topbar">
    <button id="sidebarToggle" class="btn btn-sm btn-light me-3 border-0">
        <i class="bi bi-list fs-5"></i>
    </button>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="d-none d-md-block flex-grow-1">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Home</a></li>
            @yield('breadcrumb')
        </ol>
    </nav>

    <div class="d-flex align-items-center gap-3 ms-auto">
        <!-- Settings shortcut (admin only) -->
        @if(auth()->user()->isSuperUser())
        <a href="{{ route('settings.index') }}"
           class="btn btn-sm btn-light border-0 position-relative {{ request()->routeIs('settings.*') ? 'text-primary' : '' }}"
           title="System Settings">
            <i class="bi bi-gear fs-5"></i>
        </a>
        @endif

        <!-- Notifications -->
        <div class="dropdown">
            <button class="btn btn-sm btn-light border-0 position-relative" data-bs-toggle="dropdown">
                <i class="bi bi-bell fs-5"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem;">3</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width:320px;">
                <li><h6 class="dropdown-header">Notifications</h6></li>
                <li>
                    <a class="dropdown-item py-2" href="#">
                        <div class="d-flex gap-2">
                            <span class="avatar-sm bg-primary-subtle text-primary"><i class="bi bi-box-seam"></i></span>
                            <div>
                                <div class="small fw-semibold">New container arrived — MSCU1234567</div>
                                <div class="text-muted" style="font-size:.72rem;">5 min ago</div>
                            </div>
                        </div>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item py-2" href="#">
                        <div class="d-flex gap-2">
                            <span class="avatar-sm bg-warning-subtle text-warning"><i class="bi bi-tools"></i></span>
                            <div>
                                <div class="small fw-semibold">Repair estimate #RE-0042 pending approval</div>
                                <div class="text-muted" style="font-size:.72rem;">1 hr ago</div>
                            </div>
                        </div>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item py-2" href="#">
                        <div class="d-flex gap-2">
                            <span class="avatar-sm bg-danger-subtle text-danger"><i class="bi bi-exclamation-triangle"></i></span>
                            <div>
                                <div class="small fw-semibold">Storage overdue — 3 containers</div>
                                <div class="text-muted" style="font-size:.72rem;">3 hr ago</div>
                            </div>
                        </div>
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-center small" href="#">View all notifications</a></li>
            </ul>
        </div>

        <!-- User menu -->
        <div class="dropdown">
            <button class="btn btn-sm d-flex align-items-center gap-2 border-0 px-2" data-bs-toggle="dropdown">
                @if(auth()->user()->profile_photo_url)
                    <img src="{{ auth()->user()->profile_photo_url }}"
                         alt="{{ auth()->user()->full_name }}"
                         class="rounded-circle"
                         style="width:34px;height:34px;object-fit:cover;border:2px solid #e3f2fd;">
                @else
                    <span class="avatar-sm bg-primary text-white">
                        {{ auth()->user()->avatar_initials }}
                    </span>
                @endif
                <span class="d-none d-md-block small fw-semibold text-dark">
                    {{ auth()->user()->full_name }}
                </span>
                <i class="bi bi-chevron-down small text-muted"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li>
                    <div class="dropdown-header d-flex align-items-center gap-2 py-2">
                        @if(auth()->user()->profile_photo_url)
                            <img src="{{ auth()->user()->profile_photo_url }}"
                                 alt="{{ auth()->user()->full_name }}"
                                 class="rounded-circle"
                                 style="width:36px;height:36px;object-fit:cover;">
                        @else
                            <span class="avatar-sm bg-primary text-white" style="flex-shrink:0;">
                                {{ auth()->user()->avatar_initials }}
                            </span>
                        @endif
                        <div style="line-height:1.3;">
                            <div class="fw-semibold text-dark" style="font-size:.83rem;">{{ auth()->user()->full_name }}</div>
                            <div class="text-muted" style="font-size:.72rem;">{{ auth()->user()->email }}</div>
                        </div>
                    </div>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item" href="{{ route('users.show', auth()->user()) }}"><i class="bi bi-person me-2"></i>My Profile</a></li>
                <li><a class="dropdown-item" href="{{ route('users.edit', auth()->user()) }}"><i class="bi bi-pencil me-2"></i>Edit Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>

<!-- ══════════════════════════════════════
     MAIN CONTENT
══════════════════════════════════════ -->
<main id="main-content">

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @yield('content')

</main>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Bootstrap Datepicker (date-only inputs) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
<!-- AirDatepicker v3 (datetime inputs) -->
<script src="https://cdn.jsdelivr.net/npm/air-datepicker@3.5.3/air-datepicker.js"></script>

<script>
    // English locale for AirDatepicker (library defaults to Russian)
    window.ADP_EN = {
        days:        ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],
        daysShort:   ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],
        daysMin:     ['Su','Mo','Tu','We','Th','Fr','Sa'],
        months:      ['January','February','March','April','May','June','July','August','September','October','November','December'],
        monthsShort: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        today:       'Today',
        clear:       'Clear',
        dateFormat:  'yyyy-MM-dd',
        timeFormat:  'HH:mm',
        firstDay:    1,
    };

    // Sidebar toggle
    const sidebar      = document.getElementById('sidebar');
    const topbar       = document.getElementById('topbar');
    const mainContent  = document.getElementById('main-content');
    const toggleBtn    = document.getElementById('sidebarToggle');
    const backdrop     = document.getElementById('sidebar-backdrop');

    function isMobile() { return window.innerWidth <= 768; }

    function closeMobileSidebar() {
        sidebar.classList.remove('mobile-open');
        backdrop.classList.remove('show');
        document.body.style.overflow = '';
    }

    toggleBtn.addEventListener('click', () => {
        if (isMobile()) {
            const opening = !sidebar.classList.contains('mobile-open');
            sidebar.classList.toggle('mobile-open');
            backdrop.classList.toggle('show');
            // Prevent body scroll while drawer is open
            document.body.style.overflow = opening ? 'hidden' : '';
        } else {
            sidebar.classList.toggle('collapsed');
            topbar.classList.toggle('expanded');
            mainContent.classList.toggle('expanded');
        }
    });

    // Tap backdrop to close
    backdrop.addEventListener('click', closeMobileSidebar);

    // Tap a nav link on mobile → close drawer and navigate
    sidebar.querySelectorAll('a.nav-link').forEach(link => {
        link.addEventListener('click', () => { if (isMobile()) closeMobileSidebar(); });
    });

    // Rotating to landscape → clean up mobile state
    window.addEventListener('resize', () => { if (!isMobile()) closeMobileSidebar(); });

    // ── Sidebar: open only the active path on every page load ───────────────
    // No localStorage persistence — each full page load starts with everything
    // closed, then opens only the section/sub-group containing the active link.
    // Users can manually expand others; those stay open for that page session only.
    function openActivePath() {
        const activeLink = document.querySelector('#sidebar .nav-link.active');
        if (!activeLink) return;
        let node = activeLink.closest('.collapse');
        while (node && node.id) {
            node.classList.add('show');
            const btn = document.querySelector(`[data-bs-target="#${node.id}"]`);
            btn?.setAttribute('aria-expanded', 'true');
            node = node.parentElement?.closest('.collapse');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        openActivePath();

        // Init Select2
        if (typeof $.fn.select2 !== 'undefined') {
            window.s2CodeResult = function(opt) {
                if (!opt.id) return opt.text;
                var el = opt.element;
                var code = el && el.dataset.code ? el.dataset.code : opt.text;
                var name = el && el.dataset.name ? el.dataset.name : '';
                if (!name) return opt.text;
                var chipClass = (el && el.dataset.chipClass) ? el.dataset.chipClass : 's2-code-chip';
                var isoHtml = (el && el.dataset.iso) ? '<span style="display:inline-block;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;border-radius:3px;padding:0 4px;font-family:monospace;font-size:.68rem;font-weight:600;flex-shrink:0;">'+el.dataset.iso+'</span>' : '';
                return $('<span class="s2-opt-row"><span class="'+chipClass+'">'+code+'</span>'+isoHtml+'<span class="s2-code-label">'+name+'</span></span>');
            };
            window.s2CodeSelection = function(opt) {
                if (!opt.id) return opt.text;
                var el = opt.element;
                if (!el || !el.dataset.code) return opt.text;
                var chipCls = el.dataset.chipClass ? el.dataset.chipClass : 's2-code-chip';
                // "Name [CODE]" mode — triggered by data-s2-sel="name" on the <select>
                if (el.parentElement && el.parentElement.dataset.s2Sel === 'name') {
                    var name = el.dataset.name || opt.text;
                    return $('<span>' + $('<span>').text(name)[0].innerHTML + ' <span class="' + chipCls + '" style="font-size:.7rem;vertical-align:middle;">' + el.dataset.code + '</span></span>');
                }
                // Equipment type options — show code chip + ISO chip (if set) + description
                if (el.dataset.eqt !== undefined) {
                    var name = el.dataset.name || opt.text;
                    var isoHtml = el.dataset.iso ? '<span style="display:inline-block;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;border-radius:3px;padding:0 4px;font-family:monospace;font-size:.68rem;font-weight:600;flex-shrink:0;">' + el.dataset.iso + '</span>' : '';
                    return $('<span class="s2-opt-row"><span class="' + chipCls + '">' + el.dataset.code + '</span>' + isoHtml + '<span class="s2-code-label">' + name + '</span></span>');
                }
                // Reefer (or other chip-class variants) — show coloured chip only
                if (el.dataset.chipClass) {
                    return $('<span class="' + chipCls + '">' + el.dataset.code + '</span>');
                }
                return el.dataset.code;
            };
            window.initS2Code = function($el, extraOpts) {
                var $modal = $el.closest('.modal');
                $el.select2($.extend({
                    theme: 'bootstrap-5',
                    templateResult: window.s2CodeResult,
                    templateSelection: window.s2CodeSelection,
                    dropdownAutoWidth: true,
                    dropdownParent: $modal.length ? $modal : $('body'),
                    width: '100%',
                }, extraOpts || {}));
            };
            // Grade colour-dot dropdown
            window.s2GradeResult = function(opt) {
                if (!opt.id) return opt.text;
                var el = opt.element;
                var code  = el && el.dataset.code  ? el.dataset.code  : opt.text;
                var name  = el && el.dataset.name  ? el.dataset.name  : '';
                var color = el && el.dataset.color ? el.dataset.color : 'secondary';
                if (!name) return opt.text;
                return $('<span class="s2-opt-row">' +
                    '<span class="s2-grade-dot bg-' + color + '"></span>' +
                    '<span class="s2-code-chip">' + code + '</span>' +
                    '<span class="s2-code-label">' + name + '</span>' +
                    '</span>');
            };
            window.s2GradeSelection = function(opt) {
                if (!opt.id) return opt.text;
                var el = opt.element;
                if (!el || !el.dataset.code) return opt.text;
                var color = el.dataset.color || 'secondary';
                var name  = el.dataset.name  || opt.text;
                return $('<span class="s2-opt-row">' +
                    '<span class="s2-grade-dot bg-' + color + '"></span>' +
                    '<span class="s2-code-chip">' + el.dataset.code + '</span>' +
                    '<span class="s2-code-label">' + name + '</span>' +
                    '</span>');
            };
            window.initS2Grade = function($el, extraOpts) {
                var $modal = $el.closest('.modal');
                $el.select2($.extend({
                    theme: 'bootstrap-5',
                    templateResult:    window.s2GradeResult,
                    templateSelection: window.s2GradeSelection,
                    dropdownAutoWidth: true,
                    dropdownParent: $modal.length ? $modal : $('body'),
                    width: '100%',
                }, extraOpts || {}));
            };
            $('.select2').select2({ theme: 'bootstrap-5' });
            $('.s2-code').each(function()  { window.initS2Code($(this));  });
            $('.s2-grade').each(function() { window.initS2Grade($(this)); });
        }

        // Bootstrap Datepicker — convert type="date" inputs to text and init.
        // 'bottom left' forces the calendar to always open BELOW the input;
        // without this, Bootstrap Datepicker's auto-orientation can decide to
        // open ABOVE and cover the input when it miscalculates available space.
        if (typeof $.fn.datepicker !== 'undefined') {
            $('input[type="date"]').each(function () {
                var $el  = $(this);
                var prev = $el.val();
                $el.attr('type', 'text').attr('autocomplete', 'off');
                $el.datepicker({
                    format:         'yyyy-mm-dd',
                    autoclose:      true,
                    todayHighlight: true,
                    weekStart:      1,
                    orientation:    'bottom left',
                    container:      'body',
                });
                if (prev) $el.datepicker('update', prev);
            });
        }
        // Air Datepicker v3 for datetime-local inputs.
        // Uses its own viewport-aware positioning engine; appends the widget to
        // document.body via a global container, avoiding fixed-sidebar z-index
        // and coordinate-calculation issues that broke Tempus Dominus v6.
        if (typeof AirDatepicker !== 'undefined') {
            $('input[type="datetime-local"]').each(function () {
                var $el  = $(this);
                var raw  = ($el.val() || '').replace('T', ' ');
                $el.attr('type', 'text').attr('autocomplete', 'off');

                var initDates = [];
                if (raw && /\d{4}-\d{2}-\d{2} \d{2}:\d{2}/.test(raw)) {
                    initDates = [new Date(raw.replace(' ', 'T'))];
                }

                var dp = new AirDatepicker($el[0], {
                    locale:            window.ADP_EN,
                    timepicker:        true,
                    autoClose:         false,
                    dateFormat:        'yyyy-MM-dd',
                    timeFormat:        'HH:mm',
                    dateTimeSeparator: ' ',
                    position:          'bottom left',
                    selectedDates:     initDates,
                    container:         'body',
                    onSelect: function () {
                        $el.trigger('change');
                    },
                });
                // Auto-close when the AM/PM toggle is clicked (last step in 12h selection).
                // Uses capture phase so Air Datepicker's own stopPropagation cannot block it.
                var ampmEl = dp.$datepicker.querySelector('.air-datepicker-time--current-ampm');
                if (ampmEl) {
                    ampmEl.addEventListener('click', function () {
                        setTimeout(function () { dp.hide(); $el.trigger('change'); }, 150);
                    }, true);
                }
            });
        }
    });
</script>
{{-- ══════════════════════════════════════════════════════════════════════
     GLOBAL CONFIRM MODAL
     Usage (HTML): data-confirm="Question?" data-confirm-title="Title"
                   data-confirm-class="btn-danger" data-confirm-label="Delete"
     Usage (JS):   confirmAction('Question?', () => doSomething(), { title, confirmClass, confirmLabel })
══════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow">
            <div class="modal-header py-2 border-bottom-0">
                <h6 class="modal-title fw-semibold" id="confirmModalTitle">Please Confirm</h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-2 small" id="confirmModalBody"></div>
            <div class="modal-footer py-2 border-top-0 gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="confirmModalOk">Confirm</button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     TOAST CONTAINER (top-right, fixed)
     Usage (JS): showToast('Message', 'warning')  — types: success|danger|warning|info
══════════════════════════════════════════════════════════════════════ --}}
<div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1090;"></div>

<script>
(function () {
    'use strict';

    // ── Confirm modal ────────────────────────────────────────────────────────
    var modalEl = document.getElementById('confirmModal');
    var bsModal  = modalEl ? new bootstrap.Modal(modalEl) : null;
    var titleEl  = modalEl ? modalEl.querySelector('#confirmModalTitle') : null;
    var bodyEl   = modalEl ? modalEl.querySelector('#confirmModalBody')  : null;
    var okBtn    = modalEl ? modalEl.querySelector('#confirmModalOk')    : null;

    window.confirmAction = function (message, onConfirm, options) {
        if (!bsModal) { if (window.confirm(message) && onConfirm) onConfirm(); return; }
        options = options || {};
        titleEl.textContent = options.title        || 'Please Confirm';
        bodyEl.textContent  = message;
        okBtn.textContent   = options.confirmLabel || 'Confirm';
        okBtn.className     = 'btn btn-sm ' + (options.confirmClass || 'btn-primary');
        okBtn.onclick = function () { bsModal.hide(); if (typeof onConfirm === 'function') onConfirm(); };
        bsModal.show();
    };

    // Delegate — buttons with data-confirm (capture phase so we win over onclick)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-confirm], a[data-confirm]');
        if (!btn) return;
        e.preventDefault();
        var msg   = btn.getAttribute('data-confirm');
        var title = btn.getAttribute('data-confirm-title') || 'Please Confirm';
        var cls   = btn.getAttribute('data-confirm-class') || 'btn-primary';
        var label = btn.getAttribute('data-confirm-label') || 'Confirm';
        window.confirmAction(msg, function () {
            btn.removeAttribute('data-confirm');
            var form = btn.closest('form');
            if (form) { try { form.requestSubmit(btn); } catch (_) { form.submit(); } }
            else { btn.click(); }
        }, { title: title, confirmClass: cls, confirmLabel: label });
    }, true);

    // Delegate — forms with data-confirm (catches onsubmit-style patterns)
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.hasAttribute('data-confirm')) return;
        e.preventDefault();
        var msg   = form.getAttribute('data-confirm');
        var title = form.getAttribute('data-confirm-title') || 'Please Confirm';
        var cls   = form.getAttribute('data-confirm-class') || 'btn-primary';
        var label = form.getAttribute('data-confirm-label') || 'Confirm';
        window.confirmAction(msg, function () {
            form.removeAttribute('data-confirm');
            try { form.requestSubmit(); } catch (_) { form.submit(); }
        }, { title: title, confirmClass: cls, confirmLabel: label });
    }, true);

    // ── Toast ────────────────────────────────────────────────────────────────
    var toastContainer = document.getElementById('toastContainer');
    var toastIcons = { success: 'bi-check-circle-fill', danger: 'bi-exclamation-circle-fill',
                       warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };

    window.showToast = function (message, type) {
        type = type || 'info';
        if (!toastContainer) { window.alert(message); return; }
        var id   = 'toast_' + Date.now();
        var icon = toastIcons[type] || toastIcons.info;
        var html = '<div id="' + id + '" class="toast align-items-center text-bg-' + type +
                   ' border-0" role="alert" data-bs-autohide="true" data-bs-delay="5000">' +
                   '<div class="d-flex"><div class="toast-body"><i class="bi ' + icon + ' me-2"></i>' +
                   message + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto"' +
                   ' data-bs-dismiss="toast"></button></div></div>';
        toastContainer.insertAdjacentHTML('beforeend', html);
        var el    = document.getElementById(id);
        var toast = new bootstrap.Toast(el);
        toast.show();
        el.addEventListener('hidden.bs.toast', function () { el.remove(); });
    };
})();
</script>
@stack('scripts')
</body>
</html>
