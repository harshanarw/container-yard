<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold small">Configuration Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control form-control-sm"
               value="{{ old('name', $config->name ?? '') }}" required>
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold small">Email Driver <span class="text-danger">*</span></label>
        <select name="driver" class="form-select form-select-sm" data-driver-toggle>
            <option value="smtp"     {{ old('driver', $config->driver ?? 'smtp') === 'smtp'     ? 'selected' : '' }}>SMTP</option>
            <option value="mailgun"  {{ old('driver', $config->driver ?? '') === 'mailgun'  ? 'selected' : '' }}>Mailgun</option>
            <option value="sendgrid" {{ old('driver', $config->driver ?? '') === 'sendgrid' ? 'selected' : '' }}>SendGrid</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold small">Category <span class="text-danger">*</span></label>
        <select name="category" class="form-select form-select-sm">
            @foreach($categories as $val => $label)
            <option value="{{ $val }}" {{ old('category', $config->category ?? 'general') === $val ? 'selected' : '' }}>
                {{ $label }}
            </option>
            @endforeach
        </select>
    </div>

    {{-- SMTP fields --}}
    <div class="col-12" data-driver-section="smtp">
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label fw-semibold small">SMTP Host</label>
                <input type="text" name="smtp_host" class="form-control form-control-sm"
                       value="{{ old('smtp_host', $config->smtp_host ?? '') }}" placeholder="smtp.example.com">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small">Port</label>
                <input type="number" name="smtp_port" class="form-control form-control-sm"
                       value="{{ old('smtp_port', $config->smtp_port ?? 587) }}" placeholder="587">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Encryption</label>
                <select name="smtp_encryption" class="form-select form-select-sm">
                    <option value="tls" {{ ($config->smtp_encryption ?? 'tls') === 'tls' ? 'selected' : '' }}>TLS</option>
                    <option value="ssl" {{ ($config->smtp_encryption ?? '') === 'ssl' ? 'selected' : '' }}>SSL</option>
                    <option value="none" {{ ($config->smtp_encryption ?? '') === 'none' ? 'selected' : '' }}>None</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold small">SMTP Username</label>
                <input type="text" name="smtp_username" class="form-control form-control-sm"
                       value="{{ old('smtp_username', $config->smtp_username ?? '') }}" autocomplete="off">
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold small">SMTP Password
                    @if($config) <span class="text-muted">(leave blank to keep existing)</span> @endif
                </label>
                <input type="password" name="smtp_password" class="form-control form-control-sm" autocomplete="new-password">
            </div>
        </div>
    </div>

    {{-- Mailgun fields --}}
    <div class="col-12" data-driver-section="mailgun" style="display:none">
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label fw-semibold small">Mailgun Domain</label>
                <input type="text" name="mailgun_domain" class="form-control form-control-sm"
                       value="{{ old('mailgun_domain', $config->mailgun_domain ?? '') }}" placeholder="mg.yourdomain.com">
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold small">API Secret
                    @if($config) <span class="text-muted">(leave blank to keep)</span> @endif
                </label>
                <input type="password" name="mailgun_secret" class="form-control form-control-sm" autocomplete="new-password">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">API Endpoint</label>
                <input type="text" name="mailgun_endpoint" class="form-control form-control-sm"
                       value="{{ old('mailgun_endpoint', $config->mailgun_endpoint ?? 'api.mailgun.net') }}">
            </div>
        </div>
    </div>

    {{-- SendGrid fields --}}
    <div class="col-12" data-driver-section="sendgrid" style="display:none">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label fw-semibold small">SendGrid API Key
                    @if($config) <span class="text-muted">(leave blank to keep)</span> @endif
                </label>
                <input type="password" name="sendgrid_api_key" class="form-control form-control-sm" autocomplete="new-password">
            </div>
        </div>
    </div>

    {{-- Sender Identity --}}
    <div class="col-12">
        <div class="border-top pt-3">
            <div class="text-muted small fw-semibold mb-2">Sender Identity (optional — overrides system defaults)</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">From Name</label>
                    <input type="text" name="from_name" class="form-control form-control-sm"
                           value="{{ old('from_name', $config->from_name ?? '') }}" placeholder="My Company">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">From Email</label>
                    <input type="email" name="from_email" class="form-control form-control-sm"
                           value="{{ old('from_email', $config->from_email ?? '') }}" placeholder="noreply@example.com">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Reply-To</label>
                    <input type="email" name="reply_to" class="form-control form-control-sm"
                           value="{{ old('reply_to', $config->reply_to ?? '') }}" placeholder="support@example.com">
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="d-flex gap-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="isDefault_{{ $config->id ?? 'new' }}"
                       {{ old('is_default', $config->is_default ?? false) ? 'checked' : '' }}>
                <label class="form-check-label small" for="isDefault_{{ $config->id ?? 'new' }}">
                    Set as default for this category
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive_{{ $config->id ?? 'new' }}"
                       {{ old('is_active', $config->is_active ?? true) ? 'checked' : '' }}>
                <label class="form-check-label small" for="isActive_{{ $config->id ?? 'new' }}">Active</label>
            </div>
        </div>
    </div>
</div>
