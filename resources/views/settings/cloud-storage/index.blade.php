@extends('layouts.app')

@section('title', 'Cloud Storage Settings')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Settings</a></li>
    <li class="breadcrumb-item active">Cloud Storage</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-cloud me-2 text-primary"></i>Cloud Storage Settings</h4>
        <p class="text-muted mb-0 small">Configure where uploaded files and documents are stored. Exactly one storage option must be active.</p>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2">
    {{ session('success') }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2">
    {{ session('error') }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@php
    $isInternal = $settings->provider === 'local';
    $isExternal = !$isInternal;
    // Default external sub-provider: current if external is active, otherwise detect from saved credentials
    $extProvider = $isExternal
        ? $settings->provider
        : ($settings->gdrive_client_id ? 'gdrive' : 'dropbox');
@endphp

<div class="row g-3">

    {{-- ── Left column: storage option cards ──────────────────────────── --}}
    <div class="col-lg-7">

        {{-- ─── Card 1: Internal Storage ─────────────────────────────── --}}
        <div class="card content-card mb-3 {{ $isInternal ? 'storage-card-active' : '' }}">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">
                    <i class="bi bi-hdd me-2 text-primary"></i>Internal Storage
                </span>
                @if($isInternal)
                    <span class="badge bg-success fs-badge">
                        <i class="bi bi-check-circle me-1"></i>Active
                    </span>
                @else
                    <span class="badge bg-secondary fs-badge">Inactive</span>
                @endif
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2">
                    Files are stored on the web server's local disk using Laravel's
                    <code>public</code> storage disk (<code>storage/app/public</code>),
                    served via the storage symlink.
                </p>
                <p class="small mb-0">
                    <i class="bi bi-info-circle me-1 text-info"></i>
                    <span class="text-muted">No extra packages or external accounts required.
                    Best for single-server or local development setups.</span>
                </p>
            </div>
            <div class="card-footer d-flex gap-2 align-items-center">
                @if($isInternal)
                    <button type="button" class="btn btn-success btn-sm" disabled>
                        <i class="bi bi-check-circle me-1"></i>Currently Active
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="testBtnInternal">
                        <i class="bi bi-plug me-1"></i>Test Connection
                    </button>
                @else
                    <form method="POST" action="{{ route('settings.cloud-storage.save') }}">
                        @csrf
                        <input type="hidden" name="provider" value="local">
                        <input type="hidden" name="activate" value="1">
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-hdd me-1"></i>Activate Internal Storage
                        </button>
                    </form>
                    <span class="text-muted small ms-1">Switching will not delete existing files.</span>
                @endif
            </div>
        </div>

        {{-- ─── Card 2: External Cloud Storage ───────────────────────── --}}
        <div class="card content-card {{ $isExternal ? 'storage-card-active' : '' }}">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">
                    <i class="bi bi-cloud-arrow-up me-2 text-primary"></i>External Cloud Storage
                </span>
                @if($isExternal)
                    <span class="badge bg-success fs-badge">
                        <i class="bi bi-check-circle me-1"></i>Active
                    </span>
                @else
                    <span class="badge bg-secondary fs-badge">Inactive</span>
                @endif
            </div>
            <div class="card-body">

                <form method="POST" action="{{ route('settings.cloud-storage.save') }}" id="extForm">
                    @csrf

                    {{-- Provider sub-selector --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:.05em;">
                            Select Cloud Provider
                        </label>
                        <div class="d-flex gap-3 flex-wrap">

                            @foreach([
                                ['dropbox', 'bi-dropbox',  'Dropbox',      'Free &amp; paid accounts'],
                                ['gdrive',  'bi-google',   'Google Drive', 'Personal Gmail or G Suite'],
                            ] as [$val, $icon, $label, $desc])
                            <label class="ext-provider-card {{ $extProvider === $val ? 'selected' : '' }}"
                                   for="ext_{{ $val }}">
                                <input type="radio" name="provider" id="ext_{{ $val }}"
                                       value="{{ $val }}"
                                       class="d-none ext-provider-radio"
                                       {{ $extProvider === $val ? 'checked' : '' }}>
                                <div class="card border-2 p-3 text-center" style="min-width:120px; cursor:pointer;">
                                    <i class="bi {{ $icon }} fs-2 mb-1
                                        {{ $extProvider === $val ? 'text-primary' : 'text-muted' }}"></i>
                                    <div class="fw-semibold small">{{ $label }}</div>
                                    <div class="text-muted" style="font-size:10px;">{!! $desc !!}</div>
                                </div>
                            </label>
                            @endforeach

                        </div>
                    </div>

                    {{-- ── Dropbox configuration ──────────────────────── --}}
                    <div id="ext_section_dropbox"
                         class="ext-provider-section {{ $extProvider !== 'dropbox' ? 'd-none' : '' }}">
                        <div class="alert alert-warning py-2 small mb-3">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Requires: <code>composer require spatie/flysystem-dropbox</code>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">
                                    App Key <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="dropbox_app_key" class="form-control"
                                       value="{{ $settings->dropbox_app_key }}"
                                       placeholder="App key from Dropbox Console">
                                <div class="form-text">
                                    <a href="https://www.dropbox.com/developers/apps" target="_blank">Dropbox App Console</a>
                                    → your app → Settings → App key.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">
                                    App Secret <span class="text-danger">*</span>
                                </label>
                                <input type="password" name="dropbox_app_secret" class="form-control"
                                       placeholder="{{ $settings->dropbox_app_secret ? '(saved — leave blank to keep)' : 'App secret' }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small">Root Folder</label>
                                <input type="text" name="dropbox_root_folder" class="form-control"
                                       value="{{ $settings->dropbox_root_folder ?? '/container-yard' }}"
                                       placeholder="/container-yard">
                                <div class="form-text">All files are stored under this path in your Dropbox.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small">OAuth2 Authorization</label>
                                @if($settings->hasDropboxRefreshToken())
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-check-circle me-1"></i>Connected
                                        </span>
                                        <a href="{{ route('settings.cloud-storage.dropbox.auth') }}"
                                           class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-arrow-repeat me-1"></i>Re-authorize
                                        </a>
                                    </div>
                                @else
                                    <div class="mt-1">
                                        <p class="text-muted small mb-2">
                                            Save App Key &amp; Secret first, then click <em>Connect with Dropbox</em>
                                            to get a permanent refresh token (avoids 4-hour expiry).
                                        </p>
                                        <a href="{{ route('settings.cloud-storage.dropbox.auth') }}"
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-dropbox me-1"></i>Connect with Dropbox
                                        </a>
                                    </div>
                                @endif
                            </div>
                            <div class="col-12">
                                <details class="small">
                                    <summary class="text-muted" style="cursor:pointer;">
                                        Legacy access token (fallback, not recommended)
                                    </summary>
                                    <div class="mt-2">
                                        <input type="password" name="dropbox_access_token" class="form-control"
                                               placeholder="{{ $settings->dropbox_access_token ? '(saved — leave blank to keep)' : 'Long-lived access token from App Console' }}">
                                        <div class="form-text">
                                            Only use if OAuth2 above is not possible. Tokens from the App Console
                                            expire in 4 hours.
                                        </div>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </div>

                    {{-- ── Google Drive configuration ──────────────────── --}}
                    <div id="ext_section_gdrive"
                         class="ext-provider-section {{ $extProvider !== 'gdrive' ? 'd-none' : '' }}">
                        <div class="alert alert-warning py-2 small mb-3">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Requires: <code>composer require masbug/flysystem-google-drive-ext google/apiclient</code>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">
                                    Client ID <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="gdrive_client_id" class="form-control"
                                       value="{{ $settings->gdrive_client_id }}"
                                       placeholder="xxxxx.apps.googleusercontent.com">
                                <div class="form-text">
                                    <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a>
                                    → Credentials → OAuth 2.0 Client ID.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">
                                    Client Secret <span class="text-danger">*</span>
                                </label>
                                <input type="password" name="gdrive_client_secret" class="form-control"
                                       placeholder="{{ $settings->gdrive_client_secret ? '(saved — leave blank to keep)' : 'Client secret' }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small">Target Folder ID</label>
                                <input type="text" name="gdrive_folder_id" class="form-control"
                                       value="{{ $settings->gdrive_folder_id }}"
                                       placeholder="Folder ID from Google Drive URL">
                                <div class="form-text">
                                    Open your target folder in Google Drive → copy the ID from the URL
                                    (<code>drive.google.com/drive/folders/<strong>THIS_PART</strong></code>).
                                    Leave blank for root.
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small">OAuth2 Authorization</label>
                                @if($settings->gdrive_refresh_token)
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-check-circle me-1"></i>Connected
                                        </span>
                                        <a href="{{ route('settings.cloud-storage.gdrive.auth') }}"
                                           class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-arrow-repeat me-1"></i>Re-authorize
                                        </a>
                                    </div>
                                @else
                                    <div class="mt-1">
                                        <p class="text-muted small mb-2">
                                            Save Client ID &amp; Secret first, then authorize to get a refresh token.
                                        </p>
                                        <a href="{{ route('settings.cloud-storage.gdrive.auth') }}"
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-google me-1"></i>Connect with Google
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                </form>{{-- #extForm --}}
            </div>

            <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
                {{-- Save credentials only (does not change active provider) --}}
                <button type="submit" form="extForm" name="activate" value="0"
                        class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-save me-1"></i>Save Configuration
                </button>

                {{-- Save + switch active provider to selected external --}}
                <button type="submit" form="extForm" name="activate" value="1"
                        class="btn btn-primary btn-sm">
                    <i class="bi bi-cloud-check me-1"></i>Save &amp; Activate External Storage
                </button>

                @if($isExternal)
                    <button type="button" class="btn btn-outline-info btn-sm ms-auto" id="testBtnExt">
                        <i class="bi bi-plug me-1"></i>Test Connection
                    </button>
                @endif
            </div>
        </div>

    </div>

    {{-- ── Right column: status + help ────────────────────────────────── --}}
    <div class="col-lg-5">
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-activity me-2 text-primary"></i>Connection Status
            </div>
            <div class="card-body">
                <div class="mb-2 d-flex justify-content-between">
                    <span class="text-muted small">Active Storage</span>
                    <span class="badge bg-primary-subtle text-primary fw-semibold">
                        @switch($settings->provider)
                            @case('local')   Internal (Local Disk) @break
                            @case('dropbox') External — Dropbox @break
                            @case('gdrive')  External — Google Drive @break
                        @endswitch
                    </span>
                </div>
                @if($settings->tested_at)
                <div class="mb-2 d-flex justify-content-between">
                    <span class="text-muted small">Last Tested</span>
                    <span class="small">{{ $settings->tested_at->format('d M Y H:i') }}</span>
                </div>
                <div class="mb-2 d-flex justify-content-between">
                    <span class="text-muted small">Test Result</span>
                    @if($settings->last_test_ok)
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <i class="bi bi-check-circle me-1"></i>OK
                        </span>
                    @else
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                            <i class="bi bi-x-circle me-1"></i>Failed
                        </span>
                    @endif
                </div>
                @else
                <div class="text-muted small">No connection test run yet.</div>
                @endif

                <div id="testResult" class="mt-3"></div>
            </div>
        </div>

        <div class="card content-card">
            <div class="card-header">
                <i class="bi bi-question-circle me-2 text-primary"></i>How It Works
            </div>
            <div class="card-body small text-muted">
                <p class="mb-2">
                    <strong>One storage option is active at a time.</strong>
                    New file uploads go to whichever option is marked <em>Active</em>.
                    Switching does not move existing files — they remain on the original storage.
                </p>
                <p class="mb-2">
                    <strong>Internal Storage</strong> — Local server disk. Zero configuration.
                    Best for development or single-server deployments.
                </p>
                <p class="mb-2">
                    <strong>Dropbox</strong> — Free or paid Dropbox account. Create an app in the
                    <a href="https://www.dropbox.com/developers/apps" target="_blank">App Console</a>,
                    enter App Key &amp; Secret, then click <em>Connect with Dropbox</em> to authorize.
                    Requires <code>spatie/flysystem-dropbox</code>.
                </p>
                <p class="mb-0">
                    <strong>Google Drive</strong> — Personal Gmail or G Suite account.
                    Requires an OAuth2 Client ID from
                    <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a>.
                    Requires <code>masbug/flysystem-google-drive-ext</code> + <code>google/apiclient</code>.
                </p>
            </div>
        </div>
    </div>

</div>

@endsection

@push('styles')
<style>
.storage-card-active { border: 2px solid #1a56db !important; }
.ext-provider-card .card { border-color: #dee2e6 !important; transition: border-color .15s, box-shadow .15s; }
.ext-provider-card.selected .card,
.ext-provider-card:hover .card { border-color: #1a56db !important; box-shadow: 0 0 0 3px rgba(26,86,219,.15); }
.fs-badge { font-size: .78rem; }
</style>
@endpush

@push('scripts')
<script>
// External cloud provider sub-selection
document.querySelectorAll('.ext-provider-radio').forEach(radio => {
    radio.addEventListener('change', () => {
        document.querySelectorAll('.ext-provider-card').forEach(c => {
            c.classList.remove('selected');
            c.querySelector('i.fs-2')?.classList.replace('text-primary', 'text-muted');
        });
        const card = radio.closest('.ext-provider-card');
        card.classList.add('selected');
        card.querySelector('i.fs-2')?.classList.replace('text-muted', 'text-primary');
        showExtSection(radio.value);
    });
});

function showExtSection(provider) {
    document.querySelectorAll('.ext-provider-section').forEach(s => s.classList.add('d-none'));
    document.getElementById('ext_section_' + provider)?.classList.remove('d-none');
}

// Test connection (shared handler)
function setupTestBtn(btnId) {
    const btn = document.getElementById(btnId);
    if (!btn) return;
    btn.addEventListener('click', function () {
        const result = document.getElementById('testResult');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Testing…';
        if (result) result.innerHTML = '';

        fetch('{{ route('settings.cloud-storage.test') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({}),
        })
        .then(r => r.json())
        .then(data => {
            if (result) {
                const cls = data.ok ? 'alert-success' : 'alert-danger';
                const ico = data.ok ? 'bi-check-circle' : 'bi-x-circle';
                result.innerHTML = `<div class="alert ${cls} py-2 small mb-0">
                    <i class="bi ${ico} me-1"></i>${data.message}
                </div>`;
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-plug me-1"></i>Test Connection';
        });
    });
}

setupTestBtn('testBtnInternal');
setupTestBtn('testBtnExt');
</script>
@endpush
