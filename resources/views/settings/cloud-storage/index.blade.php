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
        <p class="text-muted mb-0 small">Configure where uploaded files (photos, documents) are stored.</p>
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

<div class="row g-3">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('settings.cloud-storage.save') }}" id="csForm">
            @csrf
            <div class="card content-card">
                <div class="card-header">
                    <i class="bi bi-hdd-network me-2 text-primary"></i>Storage Provider
                </div>
                <div class="card-body">

                    {{-- Provider selector --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Active Provider</label>
                        <div class="d-flex gap-3 flex-wrap">

                            @foreach([
                                ['local',   'bi-hdd',        'Local Server',   'Files stored on the web server disk (default)'],
                                ['dropbox', 'bi-dropbox',    'Dropbox',        'Store files in your Dropbox Business account'],
                                ['gdrive',  'bi-google',     'Google Drive',   'Store files in Google Drive'],
                            ] as [$val, $icon, $label, $desc])
                            <label class="provider-card {{ $settings->provider === $val ? 'selected' : '' }}"
                                   for="prov_{{ $val }}">
                                <input type="radio" name="provider" id="prov_{{ $val }}"
                                       value="{{ $val }}"
                                       {{ $settings->provider === $val ? 'checked' : '' }}
                                       class="d-none provider-radio">
                                <div class="card border-2 p-3 text-center" style="min-width:120px; cursor:pointer;">
                                    <i class="bi {{ $icon }} fs-2 mb-1
                                        {{ $settings->provider === $val ? 'text-primary' : 'text-muted' }}"></i>
                                    <div class="fw-semibold small">{{ $label }}</div>
                                    <div class="text-muted" style="font-size:10px;">{{ $desc }}</div>
                                </div>
                            </label>
                            @endforeach

                        </div>
                    </div>

                    {{-- Local (no extra config) --}}
                    <div id="section_local" class="provider-section">
                        <div class="alert alert-info py-2 small mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Local storage uses Laravel's <code>public</code> disk (<code>storage/app/public</code>).
                            Files are served via the storage symlink. No additional configuration required.
                        </div>
                    </div>

                    {{-- Dropbox --}}
                    <div id="section_dropbox" class="provider-section d-none">
                        <div class="alert alert-warning py-2 small mb-3">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Requires: <code>composer require spatie/flysystem-dropbox</code>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">App Key <span class="text-danger">*</span></label>
                                <input type="text" name="dropbox_app_key" class="form-control"
                                       value="{{ $settings->dropbox_app_key }}"
                                       placeholder="e.g. abc123xyz456">
                                <div class="form-text">
                                    From <a href="https://www.dropbox.com/developers/apps" target="_blank">Dropbox App Console</a>
                                    → your app → Settings → App key.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">App Secret <span class="text-danger">*</span></label>
                                <input type="password" name="dropbox_app_secret" class="form-control"
                                       placeholder="{{ $settings->dropbox_app_secret ? '(saved — leave blank to keep)' : 'App secret' }}">
                                <div class="form-text">From App Console → Settings → App secret.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Root Folder</label>
                                <input type="text" name="dropbox_root_folder" class="form-control"
                                       value="{{ $settings->dropbox_root_folder ?? '/container-yard' }}"
                                       placeholder="/container-yard">
                                <div class="form-text">All files will be stored under this folder in your Dropbox.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">OAuth2 Authorization</label>
                                @if($settings->hasDropboxRefreshToken())
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-check-circle me-1"></i>Connected
                                        </span>
                                        <a href="{{ route('settings.cloud-storage.dropbox.auth') }}"
                                           class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-arrow-repeat me-1"></i>Re-authorize
                                        </a>
                                    </div>
                                @else
                                    <div>
                                        <div class="text-muted small mb-2">
                                            Save App Key &amp; Secret first, then authorize to get a long-lived refresh token.
                                            This avoids the 4-hour expiry on manually generated tokens.
                                        </div>
                                        <a href="{{ route('settings.cloud-storage.dropbox.auth') }}"
                                           class="btn btn-primary btn-sm">
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
                                        <input type="password" name="dropbox_access_token" class="form-control form-control-sm"
                                               placeholder="{{ $settings->dropbox_access_token ? '(saved — leave blank to keep)' : 'Long-lived access token from App Console' }}">
                                        <div class="form-text">
                                            Only use this if you cannot complete the OAuth2 flow above.
                                            Tokens generated in the App Console expire after 4 hours unless your app requests
                                            <code>token_access_type=offline</code>.
                                        </div>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </div>

                    {{-- Google Drive --}}
                    <div id="section_gdrive" class="provider-section d-none">
                        <div class="alert alert-warning py-2 small mb-3">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Requires: <code>composer require masbug/flysystem-google-drive-ext google/apiclient</code>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Client ID <span class="text-danger">*</span></label>
                                <input type="text" name="gdrive_client_id" class="form-control"
                                       value="{{ $settings->gdrive_client_id }}"
                                       placeholder="xxxxx.apps.googleusercontent.com">
                                <div class="form-text">
                                    From <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a>
                                    → Credentials → OAuth 2.0 Client ID.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Client Secret <span class="text-danger">*</span></label>
                                <input type="password" name="gdrive_client_secret" class="form-control"
                                       placeholder="{{ $settings->gdrive_client_secret ? '(saved — leave blank to keep)' : 'Client secret' }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Target Folder ID</label>
                                <input type="text" name="gdrive_folder_id" class="form-control"
                                       value="{{ $settings->gdrive_folder_id }}"
                                       placeholder="Folder ID from the Google Drive URL">
                                <div class="form-text">
                                    Open the target Google Drive folder → copy the ID from the URL
                                    (<code>drive.google.com/drive/folders/<strong>THIS_PART</strong></code>).
                                    Leave blank to use root.
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">OAuth2 Authorization</label>
                                @if($settings->gdrive_refresh_token)
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-check-circle me-1"></i>Connected
                                        </span>
                                        <a href="{{ route('settings.cloud-storage.gdrive.auth') }}"
                                           class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-arrow-repeat me-1"></i>Re-authorize
                                        </a>
                                    </div>
                                @else
                                    <div>
                                        <div class="text-muted small mb-2">
                                            Save Client ID &amp; Secret first, then authorize to get a refresh token.
                                        </div>
                                        <a href="{{ route('settings.cloud-storage.gdrive.auth') }}"
                                           class="btn btn-primary btn-sm">
                                            <i class="bi bi-google me-1"></i>Connect with Google
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
                <div class="card-footer d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Settings
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="testBtn">
                        <i class="bi bi-plug me-1"></i>Test Connection
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Status panel --}}
    <div class="col-lg-5">
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-activity me-2 text-primary"></i>Connection Status
            </div>
            <div class="card-body">
                <div class="mb-2 d-flex justify-content-between">
                    <span class="text-muted small">Active Provider</span>
                    <span class="badge bg-primary-subtle text-primary fw-semibold">
                        {{ strtoupper($settings->provider) }}
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
                <i class="bi bi-question-circle me-2 text-primary"></i>Quick Guide
            </div>
            <div class="card-body small text-muted">
                <p class="mb-2"><strong>Local</strong> — Default. Files saved inside the server's storage folder.
                Best for single-server setups.</p>
                <p class="mb-2"><strong>Dropbox</strong> — Files stored in your Dropbox account (free or paid).
                Create a Dropbox App in the App Console, enter App Key &amp; Secret, then click
                <em>Connect with Dropbox</em> to complete OAuth2 and get a permanent refresh token.</p>
                <p class="mb-0"><strong>Google Drive</strong> — Files stored in a Google Drive folder.
                Good for G-Suite organizations. Requires OAuth2 setup in Google Cloud Console.</p>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
.provider-card .card { border-color: #dee2e6 !important; transition: border-color .15s, box-shadow .15s; }
.provider-card.selected .card,
.provider-card:hover .card { border-color: #1a56db !important; box-shadow: 0 0 0 3px rgba(26,86,219,.15); }
</style>
@endpush

@push('scripts')
<script>
// Provider card selection
document.querySelectorAll('.provider-radio').forEach(radio => {
    radio.addEventListener('change', () => {
        document.querySelectorAll('.provider-card').forEach(c => c.classList.remove('selected'));
        radio.closest('.provider-card').classList.add('selected');
        showSection(radio.value);
    });
});

function showSection(provider) {
    document.querySelectorAll('.provider-section').forEach(s => s.classList.add('d-none'));
    const el = document.getElementById('section_' + provider);
    if (el) el.classList.remove('d-none');
}

// Show correct section on load
showSection('{{ $settings->provider }}');

// Test connection
document.getElementById('testBtn').addEventListener('click', function () {
    const btn    = this;
    const result = document.getElementById('testResult');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Testing…';
    result.innerHTML = '';

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
        const cls = data.ok ? 'alert-success' : 'alert-danger';
        const ico = data.ok ? 'bi-check-circle' : 'bi-x-circle';
        result.innerHTML = `<div class="alert ${cls} py-2 small mb-0">
            <i class="bi ${ico} me-1"></i>${data.message}
        </div>`;
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plug me-1"></i>Test Connection';
    });
});
</script>
@endpush
