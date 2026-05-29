<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'Owner Portal') — {{ $company->company_name ?? 'Container Yard' }}</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body { background: #f0f2f5; min-height: 100vh; }
  .portal-nav { background: #fff; border-bottom: 1px solid #dee2e6; padding: 10px 24px; display: flex; align-items: center; gap: 16px; }
  .portal-nav .brand img { max-height: 40px; }
  .portal-nav .brand-text { font-weight: 700; font-size: 1.1rem; color: #212529; }
  .portal-nav .brand-sub { font-size: .78rem; color: #6c757d; }
  .portal-content { max-width: 960px; margin: 30px auto; padding: 0 16px; }
  .portal-footer { text-align: center; color: #adb5bd; font-size: .78rem; padding: 30px; }
</style>
@stack('head')
</head>
<body>

<nav class="portal-nav">
  <div class="brand d-flex align-items-center gap-3">
    @if(isset($company) && $company->logo_url)
      <img src="{{ $company->logo_url }}" alt="{{ $company->company_name }}">
    @endif
    <div>
      <div class="brand-text">{{ $company->company_name ?? 'Container Yard' }}</div>
      <div class="brand-sub">Owner Portal</div>
    </div>
  </div>
  <div class="ms-auto d-flex align-items-center gap-3">
    @if(session('portal_user_id'))
      <form method="POST" action="{{ route('portal.logout') }}">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-box-arrow-right me-1"></i>Logout
        </button>
      </form>
    @endif
  </div>
</nav>

<div class="portal-content">
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show py-2 small mt-3" role="alert">
      <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show py-2 small mt-3" role="alert">
      <i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if(session('info'))
    <div class="alert alert-info alert-dismissible fade show py-2 small mt-3" role="alert">
      <i class="bi bi-info-circle me-1"></i>{{ session('info') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @yield('content')
</div>

<footer class="portal-footer">
  {{ $company->company_name ?? 'Container Yard' }} · Owner Portal
  @if(isset($company) && $company->address) · {{ $company->address }} @endif
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
