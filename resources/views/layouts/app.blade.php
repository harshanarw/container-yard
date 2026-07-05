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

        /* ── Side notification popup stack ──────────────────────────────── */
        #notifStack {
            position: fixed; right: 0; top: 70px;
            z-index: 1100;
            display: flex; flex-direction: column; gap: 8px;
            padding: 0 12px;
            pointer-events: none;
            max-width: 340px; width: 100%;
        }
        .notif-popup {
            pointer-events: all;
            background: #fff;
            border-left: 4px solid #0d6efd;
            border-radius: 6px 0 0 6px;
            box-shadow: -3px 4px 18px rgba(0,0,0,.14);
            padding: 11px 14px 11px 13px;
            transform: translateX(110%);
            opacity: 0;
            transition: transform .28s ease, opacity .28s ease;
            cursor: default;
        }
        .notif-popup.np-show  { transform: translateX(0); opacity: 1; }
        .notif-popup.np-hide  { transform: translateX(110%); opacity: 0; }
        .notif-popup.np-info    { border-left-color: #0d6efd; }
        .notif-popup.np-success { border-left-color: #198754; }
        .notif-popup.np-warning { border-left-color: #e6a817; }
        .notif-popup.np-danger  { border-left-color: #dc3545; }
        .notif-popup-title  { font-size: .88rem; font-weight: 600; color: #111; margin-bottom: 3px; padding-right: 20px; }
        .notif-popup-body   { font-size: .80rem; color: #555; line-height: 1.4; }
        .notif-popup-actor  { font-size: .73rem; font-weight: 600; color: #6366f1; margin-top: 5px; }
        .notif-popup-close  {
            position: absolute; top: 8px; right: 10px;
            background: none; border: none; padding: 0; cursor: pointer;
            font-size: .85rem; color: #999; line-height: 1;
        }
        .notif-popup-close:hover { color: #333; }
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
        @if(Auth::user()->can('settings.users.view') || Auth::user()->can('customers.view'))
        <button class="nav-section-label"
                data-bs-toggle="collapse" data-bs-target="#nav-section-admin"
                aria-expanded="false" aria-controls="nav-section-admin">
            <i class="bi bi-person-lock section-icon"></i><span>Administration</span><i class="bi bi-chevron-down section-chevron"></i>
        </button>
        <div class="collapse" id="nav-section-admin">
            <ul class="nav flex-column">
                @can('settings.users.view')
                <li class="nav-item">
                    <a href="{{ route('users.index') }}"
                       class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <i class="bi bi-people"></i><span>User Management</span>
                    </a>
                </li>
                @endcan
                @can('customers.view')
                <li class="nav-item">
                    <a href="{{ route('customers.index') }}"
                       class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}">
                        <i class="bi bi-person-badge"></i><span>Customers</span>
                    </a>
                </li>
                @endcan
            </ul>
        </div>
        @endif

        {{-- ── GUARD POST (optional feature) ── --}}
        @if($companySetting?->enable_guard_post && Auth::user()->can('guard-post.view'))
        @php $guardPostActive = request()->routeIs('guard-post.*'); @endphp
        <button class="nav-section-label"
                data-bs-toggle="collapse" data-bs-target="#nav-section-guard-post"
                aria-expanded="{{ $guardPostActive ? 'true' : 'false' }}"
                aria-controls="nav-section-guard-post">
            <i class="bi bi-shield-check section-icon"></i><span>Guard Post</span><i class="bi bi-chevron-down section-chevron"></i>
        </button>
        <div class="collapse {{ $guardPostActive ? 'show' : '' }}" id="nav-section-guard-post">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="{{ route('guard-post.index') }}"
                       class="nav-link {{ request()->routeIs('guard-post.index') || request()->routeIs('guard-post.status') ? 'active' : '' }}">
                        <i class="bi bi-list-check"></i><span>Capture Queue</span>
                        @php $pendingCaptures = \App\Models\GuardCapture::where('status','pending')->count(); @endphp
                        @if($pendingCaptures > 0)
                            <span class="badge bg-warning text-dark ms-auto">{{ $pendingCaptures }}</span>
                        @endif
                    </a>
                </li>
                @can('guard-post.create')
                <li class="nav-item">
                    <a href="{{ route('guard-post.create') }}"
                       class="nav-link {{ request()->routeIs('guard-post.create') ? 'active' : '' }}">
                        <i class="bi bi-camera"></i><span>New Capture</span>
                    </a>
                </li>
                @endcan
            </ul>
        </div>
        @endif

        {{-- ── OPERATIONS ── --}}
        @if(Auth::user()->can('yard.view') || Auth::user()->can('yard.reefer.view') || Auth::user()->can('yard.hire.view') || Auth::user()->can('surveys.view') || Auth::user()->can('estimates.view') || Auth::user()->can('work-orders.view') || Auth::user()->can('billing.repair.view'))
        <button class="nav-section-label"
                data-bs-toggle="collapse" data-bs-target="#nav-section-operations"
                aria-expanded="false" aria-controls="nav-section-operations">
            <i class="bi bi-lightning-charge section-icon"></i><span>Operations</span><i class="bi bi-chevron-down section-chevron"></i>
        </button>
        <div class="collapse" id="nav-section-operations">
            {{-- Yard sub-group --}}
            @if(Auth::user()->can('yard.view') || Auth::user()->can('yard.jobs.view') || Auth::user()->can('yard.reefer.view') || Auth::user()->can('yard.hire.view'))
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-ops-yard"
                    aria-expanded="false" aria-controls="nav-sub-ops-yard">
                <i class="bi bi-geo nav-sub-icon"></i>
                <span>Yard</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse" id="nav-sub-ops-yard">
                <ul class="nav flex-column">
                    @can('yard.view')
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
                    @endcan
                    @can('yard.hire.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('yard.hires.index') }}"
                           class="nav-link {{ request()->routeIs('yard.hires.*') ? 'active' : '' }}">
                            <i class="bi bi-arrow-left-right"></i><span>Container Hires</span>
                        </a>
                    </li>
                    @endcan
                    @can('yard.jobs.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('yard.jobs.index') }}"
                           class="nav-link {{ request()->routeIs('yard.jobs.*') ? 'active' : '' }}">
                            <i class="bi bi-briefcase"></i><span>Yard Jobs</span>
                        </a>
                    </li>
                    @endcan
                    @can('yard.reefer.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('yard.reefer.index') }}"
                           class="nav-link {{ request()->routeIs('yard.reefer.*') ? 'active' : '' }}">
                            <i class="bi bi-plug-fill"></i><span>Reefer Plug Sessions</span>
                        </a>
                    </li>
                    @endcan
                </ul>
            </div>
            @endif

            {{-- Containers sub-group --}}
            @if(Auth::user()->can('surveys.view'))
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-ops-containers"
                    aria-expanded="false" aria-controls="nav-sub-ops-containers">
                <i class="bi bi-boxes nav-sub-icon"></i>
                <span>Containers</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse" id="nav-sub-ops-containers">
                <ul class="nav flex-column">
                    @can('surveys.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('surveys.index') }}"
                           class="nav-link {{ request()->routeIs('surveys.*') ? 'active' : '' }}">
                            <i class="bi bi-card-checklist"></i><span>Surveys</span>
                        </a>
                    </li>
                    @endcan
                </ul>
            </div>
            @endif

            {{-- M&R sub-group --}}
            @if(Auth::user()->can('estimates.view') || Auth::user()->can('work-orders.view') || Auth::user()->can('billing.repair.view'))
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
                    @can('estimates.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('estimates.index') }}"
                           class="nav-link {{ request()->routeIs('estimates.*') ? 'active' : '' }}">
                            <i class="bi bi-file-earmark-ruled"></i><span>Repair Estimates</span>
                        </a>
                    </li>
                    @endcan
                    @can('work-orders.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('work-orders.index') }}"
                           class="nav-link {{ request()->routeIs('work-orders.*') ? 'active' : '' }}">
                            <i class="bi bi-hammer"></i><span>Work Orders</span>
                        </a>
                    </li>
                    @endcan
                    @can('billing.repair.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('repair-invoices.index') }}"
                           class="nav-link {{ request()->routeIs('repair-invoices.*') ? 'active' : '' }}">
                            <i class="bi bi-receipt"></i><span>Repair Invoices</span>
                        </a>
                    </li>
                    @endcan
                </ul>
            </div>
            @endif
        </div>
        @endif

        {{-- ── FINANCE ── --}}
        @if(Auth::user()->can('finance.setup.view') || Auth::user()->can('finance.coa.view') || Auth::user()->can('finance.mappings.view') || Auth::user()->can('finance.gl.view') || Auth::user()->can('finance.ar.view') || Auth::user()->can('finance.ap.view') || Auth::user()->can('finance.receipts.view') || Auth::user()->can('finance.vouchers.view'))
        <button class="nav-section-label"
                data-bs-toggle="collapse" data-bs-target="#nav-section-finance"
                aria-expanded="{{ request()->routeIs('finance.*') ? 'true' : 'false' }}"
                aria-controls="nav-section-finance">
            <i class="bi bi-bank section-icon"></i><span>Finance</span><i class="bi bi-chevron-down section-chevron"></i>
        </button>
        <div class="collapse {{ request()->routeIs('finance.*') ? 'show' : '' }}" id="nav-section-finance">

            {{-- ── Setup ──────────────────────────────────────────────────── --}}
            @if(Auth::user()->can('finance.setup.view') || Auth::user()->can('finance.coa.view') || Auth::user()->can('finance.mappings.view'))
            @php $finSetupActive = request()->routeIs('finance.setup.*'); @endphp
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-fin-setup"
                    aria-expanded="{{ $finSetupActive ? 'true' : 'false' }}"
                    aria-controls="nav-sub-fin-setup">
                <i class="bi bi-sliders nav-sub-icon"></i>
                <span>Setup</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse {{ $finSetupActive ? 'show' : '' }}" id="nav-sub-fin-setup">
                <ul class="nav flex-column">
                    @can('finance.setup.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.setup.fiscal-years.index') }}"
                           class="nav-link {{ request()->routeIs('finance.setup.fiscal-years.*') ? 'active' : '' }}">
                            <i class="bi bi-calendar3"></i><span>Fiscal Years</span>
                        </a>
                    </li>
                    @endcan
                    @can('finance.coa.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.setup.accounts.index') }}"
                           class="nav-link {{ request()->routeIs('finance.setup.accounts.*') ? 'active' : '' }}">
                            <i class="bi bi-diagram-3"></i><span>Chart of Accounts</span>
                        </a>
                    </li>
                    @endcan
                    @can('finance.mappings.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.setup.mappings.index') }}"
                           class="nav-link {{ request()->routeIs('finance.setup.mappings.*') ? 'active' : '' }}">
                            <i class="bi bi-arrow-left-right"></i><span>Account Mappings</span>
                        </a>
                    </li>
                    @endcan
                </ul>
            </div>
            @endif

            {{-- ── General Ledger ───────────────────────────────────────────── --}}
            @can('finance.gl.view')
            @php $finGlActive = request()->routeIs('finance.gl.journals.*') || request()->routeIs('finance.gl.account-ledger'); @endphp
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-fin-gl"
                    aria-expanded="{{ $finGlActive ? 'true' : 'false' }}"
                    aria-controls="nav-sub-fin-gl">
                <i class="bi bi-journal-bookmark nav-sub-icon"></i>
                <span>General Ledger</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse {{ $finGlActive ? 'show' : '' }}" id="nav-sub-fin-gl">
                <ul class="nav flex-column">
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.gl.journals.index') }}"
                           class="nav-link {{ request()->routeIs('finance.gl.journals.*') ? 'active' : '' }}">
                            <i class="bi bi-journal-text"></i><span>GL Journals</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.gl.account-ledger') }}"
                           class="nav-link {{ request()->routeIs('finance.gl.account-ledger') ? 'active' : '' }}">
                            <i class="bi bi-list-columns-reverse"></i><span>Account Ledger</span>
                        </a>
                    </li>
                </ul>
            </div>
            @endcan

            {{-- ── Accounts Receivable ──────────────────────────────────────── --}}
            @can('finance.ar.view')
            @php $finArActive = request()->routeIs('finance.ar.*'); @endphp
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-fin-ar"
                    aria-expanded="{{ $finArActive ? 'true' : 'false' }}"
                    aria-controls="nav-sub-fin-ar">
                <i class="bi bi-arrow-down-circle nav-sub-icon"></i>
                <span>Receivables</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse {{ $finArActive ? 'show' : '' }}" id="nav-sub-fin-ar">
                <ul class="nav flex-column">
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.ar.postings.index') }}"
                           class="nav-link {{ request()->routeIs('finance.ar.postings*') ? 'active' : '' }}">
                            <i class="bi bi-receipt-cutoff"></i><span>AR Postings</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.ar.aging') }}"
                           class="nav-link {{ request()->routeIs('finance.ar.aging') ? 'active' : '' }}">
                            <i class="bi bi-clock-history"></i><span>AR Aging</span>
                        </a>
                    </li>
                </ul>
            </div>
            @endcan

            {{-- ── Accounts Payable ─────────────────────────────────────────── --}}
            @can('finance.ap.view')
            @php $finApActive = request()->routeIs('finance.ap.*'); @endphp
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-fin-ap"
                    aria-expanded="{{ $finApActive ? 'true' : 'false' }}"
                    aria-controls="nav-sub-fin-ap">
                <i class="bi bi-arrow-up-circle nav-sub-icon"></i>
                <span>Payables</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse {{ $finApActive ? 'show' : '' }}" id="nav-sub-fin-ap">
                <ul class="nav flex-column">
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.ap.invoices.index') }}"
                           class="nav-link {{ request()->routeIs('finance.ap.invoices.*') ? 'active' : '' }}">
                            <i class="bi bi-receipt-cutoff"></i><span>Supplier Invoices</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.ap.aging') }}"
                           class="nav-link {{ request()->routeIs('finance.ap.aging') ? 'active' : '' }}">
                            <i class="bi bi-clock-history"></i><span>AP Aging</span>
                        </a>
                    </li>
                </ul>
            </div>
            @endcan

            {{-- ── Cash & Bank ───────────────────────────────────────────────── --}}
            @if(Auth::user()->can('finance.receipts.view') || Auth::user()->can('finance.vouchers.view') || Auth::user()->can('finance.ar-credit-notes.view') || Auth::user()->can('finance.ap-credit-notes.view'))
            @php $finCashActive = request()->routeIs('finance.bank-accounts.*') || request()->routeIs('finance.receipts.*') || request()->routeIs('finance.vouchers.*') || request()->routeIs('finance.ar-credit-notes.*') || request()->routeIs('finance.ap-credit-notes.*'); @endphp
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-fin-cash"
                    aria-expanded="{{ $finCashActive ? 'true' : 'false' }}"
                    aria-controls="nav-sub-fin-cash">
                <i class="bi bi-bank2 nav-sub-icon"></i>
                <span>Cash &amp; Bank</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse {{ $finCashActive ? 'show' : '' }}" id="nav-sub-fin-cash">
                <ul class="nav flex-column">
                    @can('finance.receipts.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.bank-accounts.index') }}"
                           class="nav-link {{ request()->routeIs('finance.bank-accounts.*') ? 'active' : '' }}">
                            <i class="bi bi-building-fill"></i><span>Bank Accounts</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.receipts.index') }}"
                           class="nav-link {{ request()->routeIs('finance.receipts.*') && !request()->routeIs('finance.receipts.receive') ? 'active' : '' }}">
                            <i class="bi bi-receipt"></i><span>Receipts</span>
                        </a>
                    </li>
                    @can('finance.receipts.create')
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.receipts.receive') }}"
                           class="nav-link {{ request()->routeIs('finance.receipts.receive') ? 'active' : '' }}">
                            <i class="bi bi-cash-coin"></i><span>Receive Payment</span>
                        </a>
                    </li>
                    @endcan
                    @endcan
                    @can('finance.vouchers.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.vouchers.index') }}"
                           class="nav-link {{ request()->routeIs('finance.vouchers.*') && !request()->routeIs('finance.vouchers.pay') ? 'active' : '' }}">
                            <i class="bi bi-cash-coin"></i><span>Payment Vouchers</span>
                        </a>
                    </li>
                    @can('finance.vouchers.create')
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.vouchers.pay') }}"
                           class="nav-link {{ request()->routeIs('finance.vouchers.pay') ? 'active' : '' }}">
                            <i class="bi bi-cash-stack"></i><span>Pay Bills</span>
                        </a>
                    </li>
                    @endcan
                    @endcan
                    @can('finance.ar-credit-notes.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.ar-credit-notes.index') }}"
                           class="nav-link {{ request()->routeIs('finance.ar-credit-notes.*') ? 'active' : '' }}">
                            <i class="bi bi-arrow-counterclockwise"></i><span>AR Credit Notes</span>
                        </a>
                    </li>
                    @endcan
                    @can('finance.ap-credit-notes.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.ap-credit-notes.index') }}"
                           class="nav-link {{ request()->routeIs('finance.ap-credit-notes.*') ? 'active' : '' }}">
                            <i class="bi bi-arrow-clockwise"></i><span>AP Credit Notes</span>
                        </a>
                    </li>
                    @endcan
                </ul>
            </div>
            @endif

            {{-- ── Reports ────────────────────────────────────────────────────── --}}
            @can('finance.gl.view')
            @php $finRptActive = request()->routeIs('finance.gl.trial-balance') || request()->routeIs('finance.reports.*'); @endphp
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-fin-reports"
                    aria-expanded="{{ $finRptActive ? 'true' : 'false' }}"
                    aria-controls="nav-sub-fin-reports">
                <i class="bi bi-bar-chart-line nav-sub-icon"></i>
                <span>Reports</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse {{ $finRptActive ? 'show' : '' }}" id="nav-sub-fin-reports">
                <ul class="nav flex-column">
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.gl.trial-balance') }}"
                           class="nav-link {{ request()->routeIs('finance.gl.trial-balance') ? 'active' : '' }}">
                            <i class="bi bi-table"></i><span>Trial Balance</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.reports.income-statement') }}"
                           class="nav-link {{ request()->routeIs('finance.reports.income-statement') ? 'active' : '' }}">
                            <i class="bi bi-graph-up-arrow"></i><span>Income Statement</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.reports.balance-sheet') }}"
                           class="nav-link {{ request()->routeIs('finance.reports.balance-sheet') ? 'active' : '' }}">
                            <i class="bi bi-bar-chart-line"></i><span>Balance Sheet</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.reports.fx-gain-loss') }}"
                           class="nav-link {{ request()->routeIs('finance.reports.fx-gain-loss') ? 'active' : '' }}">
                            <i class="bi bi-currency-exchange"></i><span>FX Gain/Loss</span>
                        </a>
                    </li>
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.reports.fx-revaluation') }}"
                           class="nav-link {{ request()->routeIs('finance.reports.fx-revaluation') ? 'active' : '' }}">
                            <i class="bi bi-arrow-repeat"></i><span>FX Revaluation</span>
                        </a>
                    </li>
                    @can('finance.ar.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.reports.customer-statement') }}"
                           class="nav-link {{ request()->routeIs('finance.reports.customer-statement') ? 'active' : '' }}">
                            <i class="bi bi-person-lines-fill"></i><span>Customer Statement</span>
                        </a>
                    </li>
                    @endcan
                    @can('finance.ap.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.reports.supplier-statement') }}"
                           class="nav-link {{ request()->routeIs('finance.reports.supplier-statement') ? 'active' : '' }}">
                            <i class="bi bi-truck"></i><span>Supplier Statement</span>
                        </a>
                    </li>
                    @endcan
                    <li class="nav-item sub-item">
                        <a href="{{ route('finance.reports.vat-sscl-return') }}"
                           class="nav-link {{ request()->routeIs('finance.reports.vat-sscl-return') ? 'active' : '' }}">
                            <i class="bi bi-percent"></i><span>VAT / SSCL Return</span>
                        </a>
                    </li>
                </ul>
            </div>
            @endcan

        </div>
        @endif

        {{-- ── BILLING ── --}}
        @if(Auth::user()->can('billing.storage.view') || Auth::user()->can('billing.storage-handling.view') || Auth::user()->can('billing.reefer.view'))
        <button class="nav-section-label"
                data-bs-toggle="collapse" data-bs-target="#nav-section-billing"
                aria-expanded="false" aria-controls="nav-section-billing">
            <i class="bi bi-receipt-cutoff section-icon"></i><span>Billing</span><i class="bi bi-chevron-down section-chevron"></i>
        </button>
        <div class="collapse" id="nav-section-billing">
            <ul class="nav flex-column">
                @can('billing.storage.view')
                <li class="nav-item">
                    <a href="{{ route('billing.index') }}"
                       class="nav-link {{ request()->routeIs('billing.*') && !request()->routeIs('billing.storage-handling.*') && !request()->routeIs('billing.reefer.*') ? 'active' : '' }}">
                        <i class="bi bi-file-earmark-text"></i><span>Storage Invoices (Archive)</span>
                    </a>
                </li>
                @endcan
                @can('billing.storage-handling.view')
                <li class="nav-item">
                    <a href="{{ route('billing.storage-handling.index') }}"
                       class="nav-link {{ request()->routeIs('billing.storage-handling.*') ? 'active' : '' }}">
                        <i class="bi bi-file-earmark-richtext"></i><span>Storage &amp; Handling</span>
                    </a>
                </li>
                @endcan
                @can('billing.reefer.view')
                <li class="nav-item">
                    <a href="{{ route('billing.reefer.index') }}"
                       class="nav-link {{ request()->routeIs('billing.reefer.*') ? 'active' : '' }}">
                        <i class="bi bi-lightning-charge-fill"></i><span>Reefer Electricity</span>
                    </a>
                </li>
                @endcan
            </ul>
        </div>
        @endif

        {{-- ── SETUP ── --}}
        @php
            $canViewSetup = Auth::user()->can('masters.job-types.view')
                         || Auth::user()->can('containers.view')
                         || Auth::user()->can('masters.equipment-types.view')
                         || Auth::user()->can('masters.container-grades.view')
                         || Auth::user()->can('masters.storage-zones.view')
                         || Auth::user()->can('masters.checklist-items.view')
                         || Auth::user()->can('masters.damage-rules.view')
                         || Auth::user()->can('masters.repair-categories.view')
                         || Auth::user()->can('masters.mr-codes.view')
                         || Auth::user()->can('masters.storage-tariff.view')
                         || Auth::user()->can('masters.handling-tariff.view')
                         || Auth::user()->can('masters.mr-tariff.view')
                         || Auth::user()->can('masters.reefer-tariff.view')
                         || Auth::user()->can('masters.customer-types.view')
                         || Auth::user()->can('masters.charge-codes.view')
                         || Auth::user()->can('masters.tax-codes.view')
                         || Auth::user()->can('masters.currencies.view')
                         || Auth::user()->can('masters.exchange-rates.view');
        @endphp
        @if($canViewSetup)
        <button class="nav-section-label"
                data-bs-toggle="collapse" data-bs-target="#nav-section-setup"
                aria-expanded="false" aria-controls="nav-section-setup">
            <i class="bi bi-tools section-icon"></i><span>Setup</span><i class="bi bi-chevron-down section-chevron"></i>
        </button>
        <div class="collapse" id="nav-section-setup">
            {{-- Gate Operations sub-group --}}
            @if(Auth::user()->can('masters.job-types.view'))
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-setup-gate-ops"
                    aria-expanded="{{ request()->routeIs('masters.job-types.*') ? 'true' : 'false' }}"
                    aria-controls="nav-sub-setup-gate-ops">
                <i class="bi bi-signpost-split nav-sub-icon"></i>
                <span>Gate Operations</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse {{ request()->routeIs('masters.job-types.*') ? 'show' : '' }}" id="nav-sub-setup-gate-ops">
                <ul class="nav flex-column">
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.job-types.index') }}"
                           class="nav-link {{ request()->routeIs('masters.job-types.*') ? 'active' : '' }}">
                            <i class="bi bi-signpost-split"></i><span>Job Types</span>
                        </a>
                    </li>
                </ul>
            </div>
            @endif

            {{-- Containers (Equipment) sub-group --}}
            @if(Auth::user()->can('containers.view') || Auth::user()->can('masters.equipment-types.view') || Auth::user()->can('masters.container-grades.view'))
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-setup-containers"
                    aria-expanded="false" aria-controls="nav-sub-setup-containers">
                <i class="bi bi-box-seam nav-sub-icon"></i>
                <span>Containers</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse" id="nav-sub-setup-containers">
                <ul class="nav flex-column">
                    @can('containers.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('containers.index') }}"
                           class="nav-link {{ request()->routeIs('containers.*') ? 'active' : '' }}">
                            <i class="bi bi-boxes"></i><span>Container Master</span>
                        </a>
                    </li>
                    @endcan
                    @can('masters.equipment-types.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.equipment-types.index') }}"
                           class="nav-link {{ request()->routeIs('masters.equipment-types.*') ? 'active' : '' }}">
                            <i class="bi bi-box-seam"></i><span>Equipment Types</span>
                        </a>
                    </li>
                    @endcan
                    @can('masters.container-grades.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.container-grades.index') }}"
                           class="nav-link {{ request()->routeIs('masters.container-grades.*') ? 'active' : '' }}">
                            <i class="bi bi-award"></i><span>Container Grades</span>
                        </a>
                    </li>
                    @endcan
                </ul>
            </div>
            @endif

            {{-- Yard Configuration sub-group --}}
            @if(Auth::user()->can('masters.storage-zones.view'))
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-setup-yard"
                    aria-expanded="false" aria-controls="nav-sub-setup-yard">
                <i class="bi bi-grid-3x3-gap nav-sub-icon"></i>
                <span>Yard Configuration</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse" id="nav-sub-setup-yard">
                <ul class="nav flex-column">
                    @can('masters.storage-zones.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.zones.index') }}"
                           class="nav-link {{ request()->routeIs('masters.zones.*') ? 'active' : '' }}">
                            <i class="bi bi-grid-3x3-gap"></i><span>Storage Zones</span>
                        </a>
                    </li>
                    @endcan
                </ul>
            </div>
            @endif

            {{-- Inspection sub-group --}}
            @if(Auth::user()->can('masters.checklist-items.view') || Auth::user()->can('masters.damage-rules.view'))
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
                    @can('masters.checklist-items.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.checklist.index') }}"
                           class="nav-link {{ request()->routeIs('masters.checklist.*') ? 'active' : '' }}">
                            <i class="bi bi-list-check"></i><span>Checklist Items</span>
                        </a>
                    </li>
                    @endcan
                    @can('masters.damage-rules.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.damage-assessment-rules.index') }}"
                           class="nav-link {{ request()->routeIs('masters.damage-assessment-rules.*') ? 'active' : '' }}">
                            <i class="bi bi-journal-check"></i><span>Assessment Rules</span>
                        </a>
                    </li>
                    @endcan
                </ul>
            </div>
            @endif

            {{-- Repair Categories sub-group --}}
            @if(Auth::user()->can('masters.repair-categories.view'))
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
                    @can('masters.repair-categories.view')
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
                    @endcan
                </ul>
            </div>
            @endif

            @if(Auth::user()->can('masters.mr-codes.view'))
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
                    @can('masters.mr-codes.view')
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
                    @endcan
                </ul>
            </div>
            @endif

            {{-- Tariffs sub-group --}}
            @if(Auth::user()->can('masters.storage-tariff.view') || Auth::user()->can('masters.handling-tariff.view') || Auth::user()->can('masters.mr-tariff.view') || Auth::user()->can('masters.reefer-tariff.view'))
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
                    @can('masters.storage-tariff.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.storage-tariff.index') }}"
                           class="nav-link {{ request()->routeIs('masters.storage-tariff.*') ? 'active' : '' }}">
                            <i class="bi bi-hdd-stack"></i><span>Storage</span>
                        </a>
                    </li>
                    @endcan
                    @can('masters.handling-tariff.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.handling-tariff.index') }}"
                           class="nav-link {{ request()->routeIs('masters.handling-tariff.*') ? 'active' : '' }}">
                            <i class="bi bi-wrench"></i><span>Handling</span>
                        </a>
                    </li>
                    @endcan
                    @can('masters.mr-tariff.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.mr-tariff.index') }}"
                           class="nav-link {{ request()->routeIs('masters.mr-tariff.*') ? 'active' : '' }}">
                            <i class="bi bi-tools"></i><span>M&amp;R</span>
                        </a>
                    </li>
                    @endcan
                    @can('masters.reefer-tariff.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.reefer-tariff.index') }}"
                           class="nav-link {{ request()->routeIs('masters.reefer-tariff.*') ? 'active' : '' }}">
                            <i class="bi bi-plug-fill"></i><span>Reefer Electricity</span>
                        </a>
                    </li>
                    @endcan
                </ul>
            </div>
            @endif

            {{-- Customer sub-group --}}
            @if(Auth::user()->can('masters.customer-types.view'))
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
                    @can('masters.customer-types.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.customer-types.index') }}"
                           class="nav-link {{ request()->routeIs('masters.customer-types.*') ? 'active' : '' }}">
                            <i class="bi bi-tags"></i><span>Customer Types</span>
                        </a>
                    </li>
                    @endcan
                </ul>
            </div>
            @endif

            {{-- Invoice sub-group --}}
            @if(Auth::user()->can('masters.charge-codes.view') || Auth::user()->can('masters.tax-codes.view') || Auth::user()->can('masters.currencies.view') || Auth::user()->can('masters.banks.view') || Auth::user()->can('masters.exchange-rates.view'))
            @php $invoiceActive = request()->routeIs('masters.tax-codes.*') || request()->routeIs('masters.charge-codes.*') || request()->routeIs('masters.currencies.*') || request()->routeIs('masters.banks.*') || request()->routeIs('masters.exchange-rates.*'); @endphp
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
                    @can('masters.charge-codes.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.charge-codes.index') }}"
                           class="nav-link {{ request()->routeIs('masters.charge-codes.*') ? 'active' : '' }}">
                            <i class="bi bi-tag"></i><span>Charge Codes</span>
                        </a>
                    </li>
                    @endcan
                    @can('masters.tax-codes.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.tax-codes.index') }}"
                           class="nav-link {{ request()->routeIs('masters.tax-codes.*') ? 'active' : '' }}">
                            <i class="bi bi-percent"></i><span>Tax Codes</span>
                        </a>
                    </li>
                    @endcan
                    @can('masters.currencies.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.currencies.index') }}"
                           class="nav-link {{ request()->routeIs('masters.currencies.*') ? 'active' : '' }}">
                            <i class="bi bi-currency-exchange"></i><span>Currency Types</span>
                        </a>
                    </li>
                    @endcan
                    @can('masters.banks.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.banks.index') }}"
                           class="nav-link {{ request()->routeIs('masters.banks.*') ? 'active' : '' }}">
                            <i class="bi bi-bank"></i><span>Banks</span>
                        </a>
                    </li>
                    @endcan
                    @can('masters.exchange-rates.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('masters.exchange-rates.index') }}"
                           class="nav-link {{ request()->routeIs('masters.exchange-rates.*') ? 'active' : '' }}">
                            <i class="bi bi-arrow-left-right"></i><span>Exchange Rates</span>
                        </a>
                    </li>
                    @endcan
                </ul>
            </div>
            @endif
        </div>
        @endif

        {{-- ── REPORTS ── --}}
        @if(Auth::user()->can('reports.view') || Auth::user()->can('container-inquiry.view'))
        @php $reportsOpen = request()->routeIs('reports.*') || request()->routeIs('container-inquiry.*'); @endphp
        <button class="nav-section-label"
                data-bs-toggle="collapse" data-bs-target="#nav-section-reports"
                aria-expanded="{{ $reportsOpen ? 'true' : 'false' }}" aria-controls="nav-section-reports">
            <i class="bi bi-graph-up-arrow section-icon"></i><span>Reports</span><i class="bi bi-chevron-down section-chevron"></i>
        </button>
        <div class="collapse {{ $reportsOpen ? 'show' : '' }}" id="nav-section-reports">
            <ul class="nav flex-column">
                @can('reports.view')
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
                @endcan
                @can('container-inquiry.view')
                <li class="nav-item">
                    <a href="{{ route('container-inquiry.index') }}"
                       class="nav-link {{ request()->routeIs('container-inquiry.*') ? 'active' : '' }}">
                        <i class="bi bi-search"></i><span>Container Inquiry</span>
                    </a>
                </li>
                @endcan
            </ul>
        </div>
        @endif

        {{-- ── SETTINGS ── --}}
        @if(Auth::user()->isSuperUser() || Auth::user()->can('access-control.view') || Auth::user()->can('audit-log.view') || Auth::user()->can('settings.company.view') || Auth::user()->can('settings.approval-workflows.view'))
        @php $settingsOpen = request()->routeIs('settings.*') || request()->routeIs('access-control.*') || request()->routeIs('audit-log.*'); @endphp
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

            @can('audit-log.view')
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="{{ route('audit-log.index') }}"
                       class="nav-link {{ request()->routeIs('audit-log.*') ? 'active' : '' }}">
                        <i class="bi bi-journal-text"></i><span>Audit Log</span>
                    </a>
                </li>
            </ul>
            @endcan

            {{-- Configuration sub-group --}}
            @if(Auth::user()->isSuperUser() || Auth::user()->can('settings.company.view') || Auth::user()->can('settings.approval-workflows.view'))
            @php
            $configSubActive = request()->routeIs('settings.index')
                            || request()->routeIs('settings.update')
                            || request()->routeIs('settings.company.*')
                            || request()->routeIs('settings.email-config.*')
                            || request()->routeIs('settings.internal-emails.*')
                            || request()->routeIs('settings.countries.*')
                            || request()->routeIs('settings.cloud-storage.*')
                            || request()->routeIs('settings.approval-workflows.*');
            @endphp
            <button class="nav-sub-toggle"
                    data-bs-toggle="collapse" data-bs-target="#nav-sub-settings-config"
                    aria-expanded="{{ $configSubActive ? 'true' : 'false' }}"
                    aria-controls="nav-sub-settings-config">
                <i class="bi bi-sliders nav-sub-icon"></i>
                <span>Configuration</span>
                <i class="bi bi-chevron-down sub-chevron"></i>
            </button>
            <div class="collapse {{ $configSubActive ? 'show' : '' }}" id="nav-sub-settings-config">
                <ul class="nav flex-column">
                    @if(auth()->user()->isSuperUser())
                    <li class="nav-item sub-item">
                        <a href="{{ route('settings.index') }}"
                           class="nav-link {{ request()->routeIs('settings.index') || request()->routeIs('settings.update') ? 'active' : '' }}">
                            <i class="bi bi-gear"></i><span>System Settings</span>
                        </a>
                    </li>
                    @endif
                    @can('settings.company.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('settings.company.index') }}"
                           class="nav-link {{ request()->routeIs('settings.company.*') ? 'active' : '' }}">
                            <i class="bi bi-building"></i><span>Company Settings</span>
                        </a>
                    </li>
                    @endcan
                    @if(auth()->user()->isSystemAdmin())
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
                    @endif
                    @can('settings.approval-workflows.view')
                    <li class="nav-item sub-item">
                        <a href="{{ route('settings.approval-workflows.index') }}"
                           class="nav-link {{ request()->routeIs('settings.approval-workflows.*') ? 'active' : '' }}">
                            <i class="bi bi-diagram-3"></i><span>Approval Workflows</span>
                        </a>
                    </li>
                    @endcan
                </ul>
            </div>
            @endif
        </div>
        @endif

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
            <button class="btn btn-sm btn-light border-0 position-relative"
                    id="notifBellBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                <i class="bi bi-bell fs-5"></i>
                <span id="notifBadge"
                      class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"
                      style="font-size:.6rem;">0</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow p-0" style="min-width:320px;max-height:420px;overflow-y:auto;">
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom sticky-top bg-white">
                    <h6 class="mb-0 fw-semibold">Notifications</h6>
                    <button id="notifMarkAllBtn" class="btn btn-link btn-sm p-0 text-muted text-decoration-none"
                            style="font-size:.78rem;">Mark all read</button>
                </div>
                <ul id="notifList" class="list-unstyled mb-0">
                    <li id="notifEmpty" class="text-center py-4 text-muted">
                        <i class="bi bi-bell-slash d-block fs-3 mb-1"></i>
                        <span style="font-size:.82rem;">No new notifications</span>
                    </li>
                </ul>
                <div class="border-top">
                    <a class="dropdown-item text-center py-2 small" href="{{ route('notifications.index') }}">
                        View all notifications
                    </a>
                </div>
            </div>
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
    {{-- Each server-side flash is also saved to sessionStorage so that one --}}
    {{-- deliberate page refresh still shows the message (clears after that). --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3 js-flash-alert" role="alert"
             data-flash-type="success" data-flash-msg="{{ addslashes(session('success')) }}">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('success_html'))
        <div class="alert alert-success alert-dismissible fade show mb-3 js-flash-alert" role="alert"
             data-flash-type="success_html" data-flash-msg="{{ addslashes(session('success_html')) }}">
            <i class="bi bi-check-circle-fill me-2"></i>{!! session('success_html') !!}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-3 js-flash-alert" role="alert"
             data-flash-type="error" data-flash-msg="{{ addslashes(session('error')) }}">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show mb-3 js-flash-alert" role="alert"
             data-flash-type="warning" data-flash-msg="{{ addslashes(session('warning')) }}">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    {{-- Restored flash (from sessionStorage after a single refresh) --}}
    <div id="restoredFlashContainer"></div>

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
<!-- Pusher JS (Reverb uses the Pusher WebSocket protocol) -->
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

<script>
// ── Flash alert persistence + auto-dismiss ────────────────────────────────
// Flash alerts saved to sessionStorage survive one page refresh. After that
// they are cleared. Each alert also auto-dismisses after 10 seconds.
(function () {
    'use strict';

    var STORAGE_KEY  = '_flashAlerts';
    var TTL_MS       = 30000; // 30 s — only restore within this window
    var AUTO_HIDE_MS = 10000; // 10 s auto-dismiss

    var typeConfig = {
        success:      { classes: 'alert-success',  icon: 'bi-check-circle-fill me-2' },
        success_html: { classes: 'alert-success',  icon: 'bi-check-circle-fill me-2' },
        error:        { classes: 'alert-danger',   icon: 'bi-exclamation-triangle-fill me-2' },
        warning:      { classes: 'alert-warning',  icon: 'bi-exclamation-triangle-fill me-2' },
    };

    // 1. Save any server-rendered flash alerts to sessionStorage.
    document.querySelectorAll('.js-flash-alert[data-flash-type]').forEach(function (el) {
        var type = el.getAttribute('data-flash-type');
        var msg  = el.getAttribute('data-flash-msg');
        if (!type || !msg) return;

        var stored = [];
        try { stored = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]'); } catch (e) {}
        // Remove any previously stored entry for the same message to avoid duplicates.
        stored = stored.filter(function (s) { return s.msg !== msg; });
        stored.push({ type: type, msg: msg, ts: Date.now() });
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(stored));
    });

    // 2. On pages with NO server-rendered flash, restore from sessionStorage
    //    (handles the one-refresh case). Clear after restoration.
    var hasServerFlash = document.querySelectorAll('.js-flash-alert').length > 0;
    if (!hasServerFlash) {
        var stored = [];
        try { stored = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]'); } catch (e) {}
        sessionStorage.removeItem(STORAGE_KEY);

        var container = document.getElementById('restoredFlashContainer');
        if (container) {
            stored.forEach(function (item) {
                if (!item.type || !item.msg || (Date.now() - item.ts) > TTL_MS) return;
                var cfg = typeConfig[item.type];
                if (!cfg) return;
                var div = document.createElement('div');
                div.className = 'alert ' + cfg.classes + ' alert-dismissible fade show mb-3';
                div.setAttribute('role', 'alert');
                // Use textContent for plain messages; success_html uses innerHTML.
                if (item.type === 'success_html') {
                    div.innerHTML = '<i class="bi ' + cfg.icon + '"></i>' + item.msg +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                } else {
                    var icon = document.createElement('i');
                    icon.className = 'bi ' + cfg.icon;
                    var text = document.createTextNode(item.msg);
                    var btn  = document.createElement('button');
                    btn.type = 'button'; btn.className = 'btn-close';
                    btn.setAttribute('data-bs-dismiss', 'alert');
                    div.appendChild(icon);
                    div.appendChild(text);
                    div.appendChild(btn);
                }
                container.appendChild(div);
            });
        }
    }

    // 3. Auto-dismiss ALL visible flash alerts after AUTO_HIDE_MS.
    function scheduleAutoDismiss() {
        var all = document.querySelectorAll(
            '.js-flash-alert, #restoredFlashContainer .alert'
        );
        all.forEach(function (el) {
            setTimeout(function () {
                if (!el.parentNode) return;
                try {
                    bootstrap.Alert.getOrCreateInstance(el).close();
                } catch (e) {
                    el.style.display = 'none';
                }
            }, AUTO_HIDE_MS);
        });
    }

    // Run after Bootstrap JS is ready (it's loaded before this block).
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleAutoDismiss);
    } else {
        scheduleAutoDismiss();
    }
}());
</script>

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

{{-- ── Side notification popup stack ─────────────────────────────────────── --}}
<div id="notifStack" aria-live="polite" aria-atomic="false"></div>

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

    // ── Side notification popup ──────────────────────────────────────────────
    var notifStack  = document.getElementById('notifStack');
    var notifColors = { info:'np-info', success:'np-success', warning:'np-warning', danger:'np-danger' };

    window.showSideNotification = function (title, body, type, url, autoHideMs, actor) {
        if (!notifStack) return;
        type       = type || 'info';
        autoHideMs = (autoHideMs === undefined) ? 6000 : autoHideMs;

        var el = document.createElement('div');
        el.className = 'notif-popup ' + (notifColors[type] || 'np-info');
        el.style.position = 'relative';
        el.innerHTML =
            '<button class="notif-popup-close" aria-label="Dismiss">&times;</button>' +
            '<div class="notif-popup-title">' + _npEsc(title) + '</div>' +
            (body ? '<div class="notif-popup-body">' + _npEsc(body) + '</div>' : '') +
            (actor ? '<div class="notif-popup-actor"><i class="bi bi-person-fill me-1"></i>' + _npEsc(actor) + '</div>' : '');

        if (url) {
            el.style.cursor = 'pointer';
            el.addEventListener('click', function (e) {
                if (e.target.classList.contains('notif-popup-close')) return;
                window.location.href = url;
            });
        }

        el.querySelector('.notif-popup-close').addEventListener('click', function () {
            _npDismiss(el);
        });

        notifStack.appendChild(el);
        requestAnimationFrame(function () {
            requestAnimationFrame(function () { el.classList.add('np-show'); });
        });

        if (autoHideMs) {
            setTimeout(function () { _npDismiss(el); }, autoHideMs);
        }
    };

    // Queue a side-notification to be shown on the NEXT full page load. Used by
    // AJAX actions that immediately reload/navigate the window (e.g. gate-in/out,
    // which open the gate pass in a popup and reload the gate page): their
    // real-time broadcast fires while the page is navigating, so the live
    // listener misses it. Queueing surfaces the popup deterministically after the
    // reload instead of relying on the (delayed, backgrounded) polling fallback.
    window.queueSideNotification = function (title, body, type, url, actor) {
        try {
            var q = JSON.parse(sessionStorage.getItem('_npQueue') || '[]');
            q.push({ title: title, body: body, type: type, url: url, actor: actor });
            sessionStorage.setItem('_npQueue', JSON.stringify(q));
        } catch (e) { /* storage unavailable — ignore */ }
    };

    // Render a "missing tariff rates" block into panelEl from a billing preview's
    // missing_rates array. Returns true when there are blocking misses so callers
    // can disable the save button. Shared by the billing preview screens.
    window.renderTariffMissing = function (panelEl, missing) {
        if (!panelEl) return false;
        missing = missing || [];
        if (!missing.length) { panelEl.className = 'd-none'; panelEl.innerHTML = ''; return false; }

        var opLabel = { 'storage': 'Storage', 'lift-off': 'Lift-Off', 'lift-on': 'Lift-On', 'reefer': 'Reefer', 'repair': 'Repair' };
        var rows = missing.map(function (m) {
            var combo = ((m.equipment || '') + ' ' + (m.cargo_status ? String(m.cargo_status).toUpperCase() : '')).trim();
            var conts = m.containers || [];
            var contStr = conts.length
                ? conts.slice(0, 6).join(', ') + (conts.length > 6 ? ' +' + (conts.length - 6) + ' more' : '')
                : '';
            var fix = m.fix_url
                ? '<a href="' + m.fix_url + '" target="_blank" class="btn btn-sm btn-outline-danger py-0">' + _npEsc(m.fix_label || 'Fix tariff') + ' &rarr;</a>'
                : '';
            return '<tr>' +
                '<td class="fw-semibold">' + _npEsc(opLabel[m.operation] || m.operation || '') + '</td>' +
                '<td>' + _npEsc(combo || '—') + '</td>' +
                '<td class="text-danger">' + _npEsc(m.reason || '') + '</td>' +
                '<td class="small text-muted">' + _npEsc(contStr) + '</td>' +
                '<td class="text-end">' + fix + '</td>' +
                '</tr>';
        }).join('');

        panelEl.className = 'alert alert-danger mb-3';
        panelEl.innerHTML =
            '<div class="d-flex align-items-start gap-2 mb-2">' +
            '<i class="bi bi-exclamation-octagon-fill mt-1"></i>' +
            '<div><strong>Cannot generate invoice &mdash; missing tariff rates.</strong> ' +
            'Add the rate line(s) below to the tariff, then preview again.</div></div>' +
            '<div class="table-responsive"><table class="table table-sm mb-0 align-middle small">' +
            '<thead><tr><th>Charge</th><th>Combination</th><th>Issue</th><th>Containers</th><th></th></tr></thead>' +
            '<tbody>' + rows + '</tbody></table></div>';
        return true;
    };

    function _npDismiss(el) {
        el.classList.replace('np-show', 'np-hide');
        setTimeout(function () { el && el.parentNode && el.parentNode.removeChild(el); }, 320);
    }

    function _npEsc(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Notification polling ─────────────────────────────────────────────────
    @auth
    (function () {
        var badgeEl       = document.getElementById('notifBadge');
        var listEl        = document.getElementById('notifList');
        var emptyEl       = document.getElementById('notifEmpty');
        var markAllBtn    = document.getElementById('notifMarkAllBtn');
        var csrfToken     = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Track which notification IDs have already been toasted, by ID rather
        // than timestamp — comparing client Date.now() against server-issued
        // created_at timestamps breaks silently whenever the two clocks drift
        // (badge count still updates from the DB, but the toast filter quietly
        // discards every item because n.ts never exceeds the skewed lastPollTs).
        var _seenRaw         = sessionStorage.getItem('_npSeenIds');
        var seenIds          = new Set();
        var seenInitialized   = false;
        try {
            if (_seenRaw) {
                JSON.parse(_seenRaw).forEach(function (id) { seenIds.add(id); });
                seenInitialized = true;
            }
        } catch (e) { /* corrupt storage — treat as first run */ }

        function persistSeen() {
            var ids = Array.from(seenIds);
            if (ids.length > 200) { ids = ids.slice(-200); seenIds = new Set(ids); }
            sessionStorage.setItem('_npSeenIds', JSON.stringify(ids));
        }

        var POLL_INTERVAL = 5000;      // 5 s fallback; overridden to 60 s when Reverb WS is active

        // When a queued cross-navigation popup is shown on load, suppress polling
        // (and the live channel) from re-toasting the matching DB notification for
        // a short window. The badge still updates; we just absorb the id silently
        // so the user sees exactly one popup, not a duplicate.
        var suppressToastsUntil = 0;

        var npIcons = {
            info:    'bi-info-circle-fill text-primary',
            success: 'bi-check-circle-fill text-success',
            warning: 'bi-exclamation-triangle-fill text-warning',
            danger:  'bi-exclamation-circle-fill text-danger',
        };
        var npBgs = {
            info: 'bg-primary-subtle', success: 'bg-success-subtle',
            warning: 'bg-warning-subtle', danger: 'bg-danger-subtle',
        };

        function renderDropdown(items, count) {
            if (!listEl) return;
            // Remove previous notification items (keep #notifEmpty)
            Array.from(listEl.querySelectorAll('.notif-item')).forEach(function (n) { n.remove(); });

            if (!items.length) {
                emptyEl && (emptyEl.style.display = '');
                return;
            }
            emptyEl && (emptyEl.style.display = 'none');

            items.forEach(function (n) {
                var t    = n.type || 'info';
                var icon = npIcons[t] || npIcons.info;
                var bg   = npBgs[t]   || npBgs.info;
                var li   = document.createElement('li');
                li.className = 'notif-item';
                li.innerHTML =
                    '<a class="dropdown-item py-2 notif-item-link" href="' + (n.url ? _npEsc(n.url) : '#') + '" ' +
                    'data-notif-id="' + _npEsc(n.id) + '" style="white-space:normal;">' +
                    '<div class="d-flex gap-2 align-items-start">' +
                    '<span class="avatar-sm ' + bg + ' flex-shrink-0" style="margin-top:2px;">' +
                    '<i class="bi ' + icon + '"></i></span>' +
                    '<div><div class="small fw-semibold" style="line-height:1.3;">' + _npEsc(n.title) + '</div>' +
                    (n.body ? '<div class="text-muted" style="font-size:.72rem;line-height:1.3;">' + _npEsc(n.body) + '</div>' : '') +
                    (n.actor ? '<div style="font-size:.68rem;color:#6366f1;margin-top:2px;font-weight:500;"><i class="bi bi-person-fill me-1"></i>' + _npEsc(n.actor) + '</div>' : '') +
                    '<div class="text-muted" style="font-size:.68rem;margin-top:1px;">' + _npEsc(n.at) + '</div>' +
                    '</div></div></a>';
                listEl.insertBefore(li, emptyEl);
            });
        }

        function updateBadge(count) {
            if (!badgeEl) return;
            badgeEl.textContent = count > 99 ? '99+' : count;
            badgeEl.classList.toggle('d-none', count === 0);
        }

        async function fetchUnread() {
            try {
                var res  = await fetch('{{ route("notifications.unread") }}', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                var data = await res.json();

                updateBadge(data.count);
                renderDropdown(data.items, data.count);

                var items = data.items || [];
                if (!seenInitialized) {
                    // First poll this tab/session — record existing unread
                    // without toasting them (they predate this page view).
                    items.forEach(function (n) { seenIds.add(n.id); });
                    seenInitialized = true;
                } else {
                    var _suppress = Date.now() < suppressToastsUntil;
                    items
                        .filter(function (n) { return !seenIds.has(n.id); })
                        .reverse()
                        .forEach(function (n) {
                            if (!_suppress) {
                                showSideNotification(n.title, n.body, n.type, n.url, undefined, n.actor);
                            }
                            seenIds.add(n.id);
                        });
                }
                persistSeen();
            } catch (e) { /* silent — never break the page */ }
        }

        // Delegate: clicking a notification item marks it read
        document.addEventListener('click', function (e) {
            var link = e.target.closest('.notif-item-link');
            if (!link) return;
            var id = link.getAttribute('data-notif-id');
            if (!id) return;
            fetch('{{ url("notifications") }}/' + id + '/read', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }
            }).catch(function () {});
        });

        // Mark all read button
        markAllBtn && markAllBtn.addEventListener('click', function () {
            fetch('{{ route("notifications.readAll") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function () {
                updateBadge(0);
                renderDropdown([], 0);
            }).catch(function () {});
        });

        // ── WebSocket real-time subscription (Reverb or Pusher) ──────────────
        // Activated by BROADCAST_DRIVER=reverb or BROADCAST_DRIVER=pusher in .env.
        // When WebSocket connects successfully, polling drops to 60 s (recovery only).
        // Falls back to 5 s polling automatically if WebSocket is unavailable.
        @php
            $bcastDriver  = config('broadcasting.default');
            $bcastKey     = match($bcastDriver) {
                'reverb' => config('broadcasting.connections.reverb.key'),
                'pusher' => config('broadcasting.connections.pusher.key'),
                default  => null,
            };
            $bcastCfg = null;
            if ($bcastKey && in_array($bcastDriver, ['reverb', 'pusher'])) {
                // Use client_port (443 = TLS via Nginx) OR explicit scheme=https.
                // This keeps REVERB_SCHEME=http for the PHP→Reverb internal connection (plain HTTP on localhost)
                // while still telling the browser to connect via wss:// when Nginx terminates TLS on port 443.
                $isTls    = (int) config('broadcasting.connections.reverb.options.client_port', 80) === 443
                         || config('broadcasting.connections.reverb.options.scheme', 'http') === 'https';
                $bcastCfg = [
                    'driver'   => $bcastDriver,
                    'key'      => $bcastKey,
                    'cluster'  => config('broadcasting.connections.pusher.options.cluster', 'mt1'),
                    'wsHost'   => config('broadcasting.connections.reverb.options.client_host', '127.0.0.1'),
                    'wsPort'   => (int) config('broadcasting.connections.reverb.options.client_port', 443),
                    'forceTLS' => $isTls || $bcastDriver === 'pusher',
                    'userId'   => auth()->id(),
                ];
            }
        @endphp
        @if($bcastCfg)
        if (window.Pusher) {
            var _bcastCfg = @json($bcastCfg);

            var _pusherOpts = {
                cluster:  _bcastCfg.cluster,
                forceTLS: _bcastCfg.forceTLS,
                channelAuthorization: {
                    endpoint: '/broadcasting/auth',
                    headers: {
                        'X-CSRF-TOKEN':     csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            };

            // Reverb needs explicit host/port; Pusher uses its own infrastructure
            if (_bcastCfg.driver === 'reverb') {
                _pusherOpts.wsHost            = _bcastCfg.wsHost;
                _pusherOpts.wsPort            = _bcastCfg.wsPort;
                _pusherOpts.wssPort           = _bcastCfg.wsPort;
                _pusherOpts.enabledTransports = ['ws', 'wss'];
            }

            var _pusher = new Pusher(_bcastCfg.key, _pusherOpts);

            var _chan = _pusher.subscribe('private-App.Models.User.' + _bcastCfg.userId);
            _chan.bind('notification.new', function (data) {
                if (data.id) { seenIds.add(data.id); persistSeen(); }
                if (Date.now() >= suppressToastsUntil) {
                    showSideNotification(data.title, data.body, data.type, data.url, undefined, data.actor);
                }
                fetchUnread(); // sync badge + dropdown
            });

            _pusher.connection.bind('connected', function () {
                POLL_INTERVAL = 60000; // WS active — poll only for recovery
                console.info('[CYMS] Real-time notifications active via ' + _bcastCfg.driver + '.');
            });

            _pusher.connection.bind('unavailable', function () {
                POLL_INTERVAL = 5000; // WS lost — revert to fast polling
            });
        }
        @endif

        // Drain any popups queued by an AJAX action right before it reloaded this
        // page. Show them immediately and open a short suppression window so the
        // matching DB notification (picked up by the poll below or the live
        // channel) is absorbed silently instead of toasted a second time.
        try {
            var _queued = JSON.parse(sessionStorage.getItem('_npQueue') || '[]');
            if (_queued.length) {
                sessionStorage.removeItem('_npQueue');
                suppressToastsUntil = Date.now() + 8000;
                _queued.forEach(function (n) {
                    showSideNotification(n.title, n.body, n.type, n.url, undefined, n.actor);
                });
            }
        } catch (e) { /* ignore */ }

        fetchUnread();
        setInterval(fetchUnread, POLL_INTERVAL);
    })();
    @endauth

})();
</script>
@stack('modals')
@stack('scripts')
</body>
</html>
