<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — {{ $companySetting?->company_name ?? 'CYM System' }}</title>
    @if($companySetting?->icon_url)
    <link rel="icon" type="image/png" href="{{ $companySetting->icon_url }}">
    @endif
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: #fff;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Segoe UI', sans-serif;
            padding: 1rem;
        }

        /* ── Card ── */
        .auth-card {
            width: 100%;
            max-width: 460px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 20px 60px rgba(0,0,0,.3);
            overflow: hidden;
        }

        /* ── Logo banner ── */
        .logo-banner {
            display: flex; justify-content: center; align-items: center;
            padding: 1.4rem 2rem 1rem;
            background: #fff;
            border-bottom: 1px solid #e9ecef;
        }
        .logo-banner img { max-width: 200px; max-height: 90px; object-fit: contain; }

        /* ── Blue gradient header ── */
        .auth-header {
            background: linear-gradient(135deg, #1565C0, #2196F3);
            color: #fff;
            padding: .9rem 2rem 1.5rem;
            text-align: center;
        }

        /* ── Step progress bar — sits between header and body as its own stripe ── */
        .step-bar {
            display: flex;
            gap: 6px;
            margin: 10px 28px 18px;
            padding: 0;
        }
        .step-seg {
            flex: 1; height: 4px;
            border-radius: 2px;
            background: #e5e7eb;
            transition: background .3s;
        }
        .step-seg.active { background: #2196F3; }

        /* ── Body ── */
        .auth-body { padding: 1.8rem 2rem 1.8rem; }

        /* ── Input groups ── */
        .auth-input-group {
            display: flex;
            align-items: center;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
            transition: border-color .2s, box-shadow .2s;
        }
        .auth-input-group:focus-within {
            border-color: #2196F3;
            box-shadow: 0 0 0 3px rgba(33,150,243,.15);
        }
        .auth-input-group .ig-icon {
            padding: 0 .75rem;
            color: #6b7280;
            font-size: 1rem;
            flex-shrink: 0;
            background: #f3f4f6;
            align-self: stretch;
            display: flex;
            align-items: center;
            border-right: 1px solid #e2e8f0;
        }
        .auth-input-group input {
            flex: 1; border: none; outline: none;
            padding: .65rem .5rem .65rem .6rem;
            font-size: .95rem;
            background: transparent;
            color: #1e293b;
        }
        .auth-input-group .ig-btn {
            padding: 0 .75rem;
            background: none; border: none; outline: none;
            color: #1565C0; cursor: pointer; font-size: 1rem;
            flex-shrink: 0;
        }
        .auth-input-group .ig-btn:hover { color: #1565C0; }

        /* ── Labels ── */
        .auth-label {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .07em;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: .4rem;
            display: block;
        }

        /* ── Username pill (step 2) ── */
        .user-pill {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 20px;
            padding: .3rem .75rem .3rem .5rem;
            font-size: .85rem;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 1.1rem;
        }
        .user-pill .pill-icon {
            width: 26px; height: 26px; border-radius: 50%;
            background: #dbeafe; color: #1e40af;
            display: flex; align-items: center; justify-content: center;
            font-size: .8rem;
        }
        .user-pill .change-link {
            color: #2196F3;
            font-size: .78rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            margin-left: .25rem;
        }
        .user-pill .change-link:hover { text-decoration: underline; }

        /* ── CAPTCHA box ── */
        .captcha-display {
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            padding: 8px 5px 8px 11px;
            letter-spacing: 6px;
            font-size: 18px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            color: #2196F3;
            background: #f8fafc;
            user-select: none;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .captcha-refresh {
            background: none; border: none; outline: none;
            color: #2196F3; font-size: 1.15rem; cursor: pointer;
            padding: .25rem .4rem;
            border-radius: 50%;
            transition: transform .3s;
        }
        .captcha-refresh:hover { transform: rotate(180deg); color: #1d4ed8; }

        /* ── Primary button ── */
        .btn-login {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            border: none; color: #fff;
            padding: .75rem; font-weight: 700;
            font-size: .95rem;
            border-radius: 10px;
            width: 100%;
            letter-spacing: .03em;
            transition: opacity .2s;
        }
        .btn-login:hover { opacity: .9; color: #fff; }
        .btn-login:disabled { opacity: .7; cursor: not-allowed; }

        /* ── Error alert ── */
        .auth-alert {
            border-radius: 10px;
            padding: .55rem .85rem;
            font-size: .85rem;
            margin-bottom: 1rem;
        }

        /* ── Footer ── */
        .auth-footer {
            background: #f8fafc;
            border-top: 1px solid #f1f5f9;
            padding: .85rem 2rem;
            text-align: center;
            font-size: .72rem;
            color: #94a3b8;
        }
        .auth-footer a { color: #2196F3; text-decoration: none; }

        /* ── Small phones: tighten side padding so fields aren't cramped ── */
        @media (max-width: 400px) {
            .auth-body    { padding: 1.4rem 1.1rem; }
            .auth-header  { padding: .9rem 1.1rem 1.4rem; }
            .logo-banner  { padding: 1.1rem 1.1rem .9rem; }
            .auth-footer  { padding: .85rem 1.1rem; }
            .step-bar     { margin: 10px 1.1rem 18px; }
            .captcha-display { letter-spacing: 4px; font-size: 16px; }
        }
    </style>
</head>
<body>

<div class="auth-card">

    {{-- Logo banner --}}
    @if($companySetting?->logo_url)
    <div class="logo-banner">
        <img src="{{ $companySetting->logo_url }}" alt="{{ $companySetting->company_name }} Logo">
    </div>
    @endif

    {{-- Blue gradient header ── company name + tagline only --}}
    <div class="auth-header">
        <h4 class="fw-bold mb-0">{{ $companySetting?->company_name ?? 'CYM System' }}</h4>
        <p class="mb-0 opacity-75 small">{{ $companySetting?->tagline ?? 'Container Yard Management' }}</p>
    </div>

    {{-- Step progress bar — sits as its own stripe below the header --}}
    <div class="step-bar">
        <div class="step-seg active" id="seg1"></div>
        <div class="step-seg"        id="seg2"></div>
    </div>

    <div class="auth-body">

        {{-- Server-side error (shown when page reloads after bad credentials) --}}
        @if($errors->any())
        <div class="alert auth-alert alert-danger" id="serverError">
            <i class="bi bi-exclamation-triangle me-1"></i>{{ $errors->first() }}
        </div>
        @endif

        @if(session('status'))
        <div class="alert auth-alert alert-success">{{ session('status') }}</div>
        @endif

        {{-- Client-side error placeholder --}}
        <div class="alert auth-alert alert-danger d-none" id="clientError"></div>

        {{-- ══ STEP 1: Username ══ --}}
        <div id="step1">
            <label class="auth-label">Username or Email</label>
            <div class="auth-input-group mb-4">
                <span class="ig-icon"><i class="bi bi-person"></i></span>
                <input type="text" id="emailInput" placeholder="Enter your username or email"
                       value="{{ old('login') }}" autocomplete="username"
                       autocapitalize="none" autocorrect="off" spellcheck="false" autofocus>
            </div>

            <button type="button" class="btn-login" id="continueBtn">
                Continue
            </button>
        </div>

        {{-- ══ STEP 2: Password + CAPTCHA ══ --}}
        <form method="POST" action="{{ route('login') }}" id="loginForm" class="d-none">
            @csrf
            <input type="hidden" name="login" id="emailHidden">
            <input type="hidden" id="captchaAnswer">

            {{-- Username pill --}}
            <div>
                <div class="user-pill">
                    <span class="pill-icon"><i class="bi bi-person-fill"></i></span>
                    <span id="pillEmail"></span>
                    <a class="change-link" id="changeUser">Change</a>
                </div>
            </div>

            {{-- Password --}}
            <label class="auth-label">Password</label>
            <div class="auth-input-group mb-4">
                <span class="ig-icon"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" id="passwordInput"
                       placeholder="••••••••••" autocomplete="current-password" required>
                <button type="button" class="ig-btn" id="togglePwd" title="Show/hide password">
                    <i class="bi bi-eye"></i>
                </button>
            </div>

            {{-- CAPTCHA --}}
            <label class="auth-label">Image Text</label>
            <div class="d-flex align-items-center gap-2 mb-3">
                <div class="auth-input-group" style="flex:1; min-width:0;">
                    <span class="ig-icon"><i class="bi bi-shield-check"></i></span>
                    <input type="text" id="captchaInput" placeholder="Type digits"
                           inputmode="numeric" maxlength="6" autocomplete="off">
                </div>
                <div class="captcha-display" id="captchaDisplay"></div>
                <button type="button" class="captcha-refresh" id="captchaRefresh" title="Refresh code">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>

            <button type="submit" class="btn-login mt-1" id="loginBtn">
                <i class="bi bi-box-arrow-in-right me-2"></i>LOGIN
            </button>
        </form>

    </div>

    <div class="auth-footer">
        &copy; {{ date('Y') }} {{ $companySetting?->software_provider ?? 'CYM System' }}
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    // ── Elements ──────────────────────────────────────────────────────────────
    const step1       = document.getElementById('step1');
    const loginForm   = document.getElementById('loginForm');
    const seg1        = document.getElementById('seg1');
    const seg2        = document.getElementById('seg2');
    const emailInput  = document.getElementById('emailInput');
    const emailHidden = document.getElementById('emailHidden');
    const pillEmail   = document.getElementById('pillEmail');
    const continueBtn = document.getElementById('continueBtn');
    const changeUser  = document.getElementById('changeUser');
    const togglePwd   = document.getElementById('togglePwd');
    const passwordInput  = document.getElementById('passwordInput');
    const captchaInput   = document.getElementById('captchaInput');
    const captchaDisplay = document.getElementById('captchaDisplay');
    const captchaAnswer  = document.getElementById('captchaAnswer');
    const captchaRefresh = document.getElementById('captchaRefresh');
    const clientError    = document.getElementById('clientError');
    const serverError    = document.getElementById('serverError');

    // ── If server returned an error, jump straight to step 2 ─────────────────
    @if($errors->any())
    const savedEmail = @json(old('login'));
    if (savedEmail) {
        emailInput.value = savedEmail;
        goToStep2(savedEmail);
    }
    @endif

    // ── CAPTCHA ───────────────────────────────────────────────────────────────
    function generateCaptcha() {
        const code = Math.floor(1000 + Math.random() * 9000).toString();
        captchaAnswer.value = code;
        captchaDisplay.textContent = code;
    }
    generateCaptcha();
    captchaRefresh.addEventListener('click', function () {
        generateCaptcha();
        captchaInput.value = '';
        captchaInput.focus();
        // Spin animation
        this.querySelector('i').style.transition = 'transform .4s';
    });

    // ── Step 1 → Step 2 ──────────────────────────────────────────────────────
    function showError(msg) {
        clientError.textContent = msg;
        clientError.classList.remove('d-none');
    }
    function hideError() {
        clientError.classList.add('d-none');
        clientError.textContent = '';
    }

    function goToStep2(email) {
        emailHidden.value = email;
        pillEmail.textContent = email;
        step1.classList.add('d-none');
        loginForm.classList.remove('d-none');
        seg2.classList.add('active');
        hideError();
        generateCaptcha();
        setTimeout(() => passwordInput.focus(), 50);
    }

    continueBtn.addEventListener('click', function () {
        const login = emailInput.value.trim();
        if (!login) {
            showError('Please enter your username or email.');
            emailInput.focus();
            return;
        }
        // Accept a username or an email. Only validate the format when it looks
        // like an email (contains '@'); plain usernames pass through.
        if (login.includes('@') && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(login)) {
            showError('Please enter a valid email address.');
            emailInput.focus();
            return;
        }
        hideError();
        goToStep2(login);
    });

    // Allow Enter key on email field to continue
    emailInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); continueBtn.click(); }
    });

    // ── "Change" link → back to step 1 ───────────────────────────────────────
    changeUser.addEventListener('click', function () {
        loginForm.classList.add('d-none');
        step1.classList.remove('d-none');
        seg2.classList.remove('active');
        passwordInput.value = '';
        captchaInput.value = '';
        hideError();
        setTimeout(() => emailInput.focus(), 50);
    });

    // ── Password show/hide ────────────────────────────────────────────────────
    togglePwd.addEventListener('click', function () {
        const icon = this.querySelector('i');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    });

    // ── Form submit: validate CAPTCHA before sending ──────────────────────────
    loginForm.addEventListener('submit', function (e) {
        const entered = captchaInput.value.trim().replace(/\s/g, '');
        const expected = captchaAnswer.value.trim();

        if (!entered) {
            e.preventDefault();
            showError('Please enter the image text (CAPTCHA) to continue.');
            captchaInput.focus();
            return;
        }
        if (entered !== expected) {
            e.preventDefault();
            showError('The image text you entered is incorrect. Please try again.');
            captchaInput.value = '';
            generateCaptcha();
            captchaInput.focus();
            return;
        }
        hideError();
        // Disable button to prevent double submit
        document.getElementById('loginBtn').disabled = true;
        document.getElementById('loginBtn').innerHTML =
            '<span class="spinner-border spinner-border-sm me-2"></span>Signing in…';
    });
})();
</script>
</body>
</html>
