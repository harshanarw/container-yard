@php $company = $company ?? \App\Models\CompanySetting::current(); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Owner Portal Login — {{ $company->company_name }}</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body { background: linear-gradient(135deg, #0d6efd22 0%, #f0f2f5 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
  .login-card { max-width: 420px; width: 100%; padding: 40px 36px; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.10); }
  .brand-logo { max-height: 56px; margin-bottom: 16px; }
</style>
</head>
<body>
<div class="login-card text-center">
  @if($company->logo_url)
    <img src="{{ $company->logo_url }}" class="brand-logo" alt="{{ $company->company_name }}">
  @endif
  <h4 class="fw-bold mb-1">{{ $company->company_name }}</h4>
  <p class="text-muted small mb-4">Owner / Principal Portal</p>

  @if($errors->any())
    <div class="alert alert-danger py-2 small text-start">
      {{ $errors->first() }}
    </div>
  @endif

  <form method="POST" action="{{ route('portal.login.submit') }}">
    @csrf
    <div class="mb-3 text-start">
      <label class="form-label small fw-semibold">Email Address</label>
      <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
    </div>
    <div class="mb-4 text-start">
      <label class="form-label small fw-semibold">Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary w-100">
      <i class="bi bi-box-arrow-in-right me-1"></i>Login to Portal
    </button>
  </form>

  <p class="text-muted small mt-4">
    No account? Contact the depot to receive a secure link for your estimates.
  </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
