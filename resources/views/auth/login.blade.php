<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — CYM System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: #fff;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Segoe UI', sans-serif;
        }
        .auth-card { position: relative; z-index: 1; }
        .auth-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 20px 60px rgba(0,0,0,.3);
            overflow: hidden;
        }
        .logo-banner {
            display: flex; justify-content: center; align-items: center;
            padding: 1.4rem 2rem 1rem;
            background: #fff;
            border-bottom: 1px solid #e9ecef;
        }
        .logo-banner img {
            width: 155px; height: 90px;
            object-fit: contain;
        }
        .auth-header {
            background: linear-gradient(135deg, #1565C0, #2196F3);
            color: #fff;
            padding: 1.4rem 2rem;
            text-align: center;
        }
        .auth-body { padding: 2rem; }
        .form-control:focus { border-color: #2196F3; box-shadow: 0 0 0 .2rem rgba(33,150,243,.15); }
        .btn-login {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            border: none; color: #fff;
            padding: .7rem; font-weight: 600; border-radius: 10px;
            transition: opacity .2s;
        }
        .btn-login:hover { opacity: .9; color: #fff; }
        .divider { color: #adb5bd; font-size: .8rem; text-align: center; position: relative; margin: 1rem 0; }
        .divider::before, .divider::after {
            content:''; position:absolute; top:50%; width:42%; height:1px; background:#dee2e6;
        }
        .divider::before { left:0; }
        .divider::after  { right:0; }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="logo-banner">
        <img src="/images/demologo.jpg" alt="Company Logo">
    </div>
    <div class="auth-header">
        <h4 class="fw-bold mb-0">CYM System</h4>
        <p class="mb-0 opacity-75 small">Container Yard Management</p>
    </div>

    <div class="auth-body">
        <h5 class="fw-bold mb-1">Welcome back</h5>
        <p class="text-muted small mb-4">Sign in to your account to continue</p>

        @if($errors->any())
        <div class="alert alert-danger alert-sm small py-2">
            <i class="bi bi-exclamation-triangle me-1"></i>
            {{ $errors->first() }}
        </div>
        @endif

        @if(session('status'))
        <div class="alert alert-success small py-2">
            {{ session('status') }}
        </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label fw-semibold small">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                           placeholder="you@company.com"
                           value="{{ old('email') }}" required autofocus>
                </div>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label fw-semibold small mb-0">Password</label>
                    @if(Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="small text-primary text-decoration-none">
                            Forgot password?
                        </a>
                    @endif
                </div>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" id="passwordInput"
                           class="form-control @error('password') is-invalid @enderror"
                           placeholder="••••••••" required>
                    <button type="button" class="btn btn-outline-secondary" id="togglePwd">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label small" for="remember">Remember me</label>
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>
            </div>
        </form>

        <div class="divider">OR</div>

        <div class="text-center small text-muted">
            <i class="bi bi-shield-lock me-1"></i>
            Protected by enterprise-grade security
        </div>

        <hr class="my-3">

        <div class="text-center text-muted" style="font-size:.75rem;">
            &copy; {{ date('Y') }} CYM System. All rights reserved.
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('togglePwd').addEventListener('click', function () {
        const inp = document.getElementById('passwordInput');
        const icon = this.querySelector('i');
        if (inp.type === 'password') {
            inp.type = 'text';
            icon.classList.replace('bi-eye','bi-eye-slash');
        } else {
            inp.type = 'password';
            icon.classList.replace('bi-eye-slash','bi-eye');
        }
    });
</script>
</body>
</html>
