<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CYM') — Container Yard Management</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">

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

        .nav-section-label {
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .1em;
            color: rgba(255,255,255,.45);
            text-transform: uppercase;
            padding: 14px 20px 4px;
            white-space: nowrap;
            overflow: hidden;
        }
        #sidebar.collapsed .nav-section-label { visibility: hidden; }

        .nav-item a.nav-link {
            color: rgba(255,255,255,.7);
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px;
            border-radius: 0;
            transition: all .2s;
            white-space: nowrap;
            overflow: hidden;
        }
        .nav-item a.nav-link i { font-size: 1.1rem; flex-shrink: 0; min-width: 24px; text-align: center; }
        .nav-item a.nav-link span { font-size: .84rem; }
        .nav-item a.nav-link:hover,
        .nav-item a.nav-link.active {
            color: #fff;
            background: rgba(33,150,243,.15);
            border-left: 3px solid var(--primary);
        }
        .nav-item a.nav-link.active { color: #fff; }

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
            #sidebar { width: 0; }
            #sidebar.mobile-open { width: var(--sidebar-width); }
            #topbar { left: 0 !important; }
            #main-content { margin-left: 0 !important; }
        }
    </style>
    @stack('styles')
</head>
<body>

<!-- ══════════════════════════════════════
     SIDEBAR
══════════════════════════════════════ -->
<nav id="sidebar">

    <a href="{{ route('dashboard') }}" class="sidebar-brand">
        <i class="bi bi-grid-3x3 brand-icon"></i>
        <div class="brand-text">
            CYM System<br>
            <small>Container Yard Mgmt</small>
        </div>
    </a>

    <div class="sidebar-nav">

        <div class="nav-section-label">Overview</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="{{ route('dashboard') }}"
                   class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i><span>Dashboard</span>
                </a>
            </li>
        </ul>

        <div class="nav-section-label">Administration</div>
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

        <div class="nav-section-label">Operations</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="{{ route('inquiries.index') }}"
                   class="nav-link {{ request()->routeIs('inquiries.*') ? 'active' : '' }}">
                    <i class="bi bi-card-checklist"></i><span>Container Inquiries</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('estimates.index') }}"
                   class="nav-link {{ request()->routeIs('estimates.*') ? 'active' : '' }}">
                    <i class="bi bi-tools"></i><span>Repair Estimates</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('yard.gate') }}"
                   class="nav-link {{ request()->routeIs('yard.gate*') ? 'active' : '' }}">
                    <i class="bi bi-box-arrow-in-right"></i><span>Gate In / Gate Out</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('yard.storage') }}"
                   class="nav-link {{ request()->routeIs('yard.storage*') ? 'active' : '' }}">
                    <i class="bi bi-calculator"></i><span>Storage Calculator</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('yard.index') }}"
                   class="nav-link {{ request()->routeIs('yard.index') ? 'active' : '' }}">
                    <i class="bi bi-map"></i><span>Yard Overview</span>
                </a>
            </li>
        </ul>

        <div class="nav-section-label">Masters</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="{{ route('masters.equipment-types.index') }}"
                   class="nav-link {{ request()->routeIs('masters.equipment-types.*') ? 'active' : '' }}">
                    <i class="bi bi-box-seam"></i><span>Equipment Types</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('masters.storage-tariff.index') }}"
                   class="nav-link {{ request()->routeIs('masters.storage-tariff.*') ? 'active' : '' }}">
                    <i class="bi bi-calendar2-range"></i><span>Storage Tariff</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('masters.checklist.index') }}"
                   class="nav-link {{ request()->routeIs('masters.checklist.*') ? 'active' : '' }}">
                    <i class="bi bi-list-check"></i><span>Inspection Checklist</span>
                </a>
            </li>
        </ul>

        <div class="nav-section-label">Billing</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="{{ route('billing.index') }}"
                   class="nav-link {{ request()->routeIs('billing.*') ? 'active' : '' }}">
                    <i class="bi bi-receipt-cutoff"></i><span>Storage Invoices</span>
                </a>
            </li>
        </ul>

        <div class="nav-section-label">Reports</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="{{ route('reports.inventory') }}"
                   class="nav-link {{ request()->routeIs('reports.inventory') ? 'active' : '' }}">
                    <i class="bi bi-bar-chart-line"></i><span>Inventory Report</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('reports.billing') }}"
                   class="nav-link {{ request()->routeIs('reports.billing') ? 'active' : '' }}">
                    <i class="bi bi-receipt"></i><span>Billing Report</span>
                </a>
            </li>
        </ul>

        <div class="nav-section-label">Settings</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="{{ route('settings.index') }}"
                   class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                    <i class="bi bi-gear"></i><span>System Settings</span>
                </a>
            </li>
        </ul>

    </div><!-- /sidebar-nav -->

    <div class="sidebar-footer">
        <i class="bi bi-circle-fill text-success me-1" style="font-size:.5rem;"></i>
        v1.0.0 &nbsp;·&nbsp; CYM &copy; {{ date('Y') }}
    </div>

</nav>

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
                <span class="avatar-sm bg-primary text-white">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                </span>
                <span class="d-none d-md-block small fw-semibold text-dark">
                    {{ auth()->user()->name ?? 'User' }}
                </span>
                <i class="bi bi-chevron-down small text-muted"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><h6 class="dropdown-header">{{ auth()->user()->email ?? '' }}</h6></li>
                <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>My Profile</a></li>
                <li><a class="dropdown-item" href="#"><i class="bi bi-shield-lock me-2"></i>Change Password</a></li>
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

<script>
    // Sidebar toggle
    const sidebar      = document.getElementById('sidebar');
    const topbar       = document.getElementById('topbar');
    const mainContent  = document.getElementById('main-content');
    const toggleBtn    = document.getElementById('sidebarToggle');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        topbar.classList.toggle('expanded');
        mainContent.classList.toggle('expanded');
    });

    // Init Select2
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof $.fn.select2 !== 'undefined') {
            $('.select2').select2({ theme: 'bootstrap-5' });
        }
    });
</script>
@stack('scripts')
</body>
</html>
