@extends('layouts.app')

@section('title', 'System Settings')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('settings.index') }}" class="text-decoration-none">Settings</a></li>
    <li class="breadcrumb-item active">System Settings</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-gear-wide me-2 text-primary"></i>System Settings</h4>
        <p class="text-muted mb-0 small">Configure operational defaults, document prefixes and billing parameters.</p>
    </div>
</div>

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

<form method="POST" action="{{ route('settings.update') }}">
    @csrf

    {{-- ── 1. Operational Defaults ── --}}
    <div class="card content-card mb-4">
        <div class="card-header py-2">
            <i class="bi bi-sliders me-2 text-primary"></i>Operational Defaults
        </div>
        <div class="card-body">
            <div class="row g-3">

                <div class="col-md-4">
                    <label class="form-label fw-semibold">
                        Yard Capacity <span class="text-danger">*</span>
                        <i class="bi bi-info-circle text-muted ms-1" title="Maximum number of containers the yard can hold"></i>
                    </label>
                    <div class="input-group">
                        <input type="number" name="yard_capacity"
                               class="form-control @error('yard_capacity') is-invalid @enderror"
                               value="{{ old('yard_capacity', $settings->yard_capacity ?? 440) }}"
                               min="1" max="99999" required>
                        <span class="input-group-text">containers</span>
                    </div>
                    @error('yard_capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">
                        Free Storage Days <span class="text-danger">*</span>
                        <i class="bi bi-info-circle text-muted ms-1" title="Number of days before storage billing begins"></i>
                    </label>
                    <div class="input-group">
                        <input type="number" name="free_storage_days"
                               class="form-control @error('free_storage_days') is-invalid @enderror"
                               value="{{ old('free_storage_days', $settings->free_storage_days ?? 7) }}"
                               min="0" max="365" required>
                        <span class="input-group-text">days</span>
                    </div>
                    <div class="form-text">Storage billing starts after this many days.</div>
                    @error('free_storage_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">
                        Timezone <span class="text-danger">*</span>
                    </label>
                    <select name="timezone" class="form-select @error('timezone') is-invalid @enderror" required>
                        @php
                            $current = old('timezone', $settings->timezone ?? 'Asia/Colombo');
                            $zones = [
                                'Asia/Colombo'    => 'Asia/Colombo (Sri Lanka, UTC+5:30)',
                                'Asia/Kolkata'    => 'Asia/Kolkata (India, UTC+5:30)',
                                'Asia/Dubai'      => 'Asia/Dubai (UAE, UTC+4)',
                                'Asia/Singapore'  => 'Asia/Singapore (UTC+8)',
                                'Asia/Kuala_Lumpur' => 'Asia/Kuala_Lumpur (Malaysia, UTC+8)',
                                'Asia/Bangkok'    => 'Asia/Bangkok (Thailand, UTC+7)',
                                'Asia/Jakarta'    => 'Asia/Jakarta (Indonesia, UTC+7)',
                                'Asia/Tokyo'      => 'Asia/Tokyo (Japan, UTC+9)',
                                'Asia/Shanghai'   => 'Asia/Shanghai (China, UTC+8)',
                                'Europe/London'   => 'Europe/London (UTC+0/+1)',
                                'UTC'             => 'UTC (Universal, UTC+0)',
                                'America/New_York' => 'America/New_York (UTC-5/-4)',
                                'America/Los_Angeles' => 'America/Los_Angeles (UTC-8/-7)',
                                'Australia/Sydney' => 'Australia/Sydney (UTC+10/+11)',
                            ];
                        @endphp
                        @foreach($zones as $value => $label)
                            <option value="{{ $value }}" {{ $current === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">
                        M&amp;R Dimension Input Unit
                        <i class="bi bi-info-circle text-muted ms-1"
                           title="Unit staff use when measuring damage dimensions (L × W) on M&amp;R estimates. The system converts to the tariff's unit (sqft or inches) automatically."></i>
                    </label>
                    @php $curDimUom = old('mr_dimension_uom', $settings->mr_dimension_uom ?? 'ft_in'); @endphp
                    <select name="mr_dimension_uom" class="form-select @error('mr_dimension_uom') is-invalid @enderror" required>
                        <option value="ft_in" {{ $curDimUom === 'ft_in' ? 'selected' : '' }}>Feet &amp; Inches (ft)</option>
                        <option value="cm"    {{ $curDimUom === 'cm'    ? 'selected' : '' }}>Centimetres (cm)</option>
                        <option value="m"     {{ $curDimUom === 'm'     ? 'selected' : '' }}>Metres (m)</option>
                    </select>
                    <div class="form-text">
                        Tariff items denominated in <code>sqft</code> or <code>inches</code> — dimensions are auto-converted on save.
                    </div>
                    @error('mr_dimension_uom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

            </div>
        </div>
    </div>

    {{-- ── 2. Document Number Prefixes ── --}}
    <div class="card content-card mb-4">
        <div class="card-header py-2">
            <i class="bi bi-hash me-2 text-primary"></i>Document Number Prefixes
            <small class="text-muted fw-normal ms-2">— used when auto-generating reference numbers</small>
        </div>
        <div class="card-body">
            <div class="row g-3">

                <div class="col-md-4 col-lg-2">
                    <label class="form-label fw-semibold">Storage Invoice</label>
                    <input type="text" name="prefix_invoice"
                           class="form-control text-uppercase @error('prefix_invoice') is-invalid @enderror"
                           value="{{ old('prefix_invoice', $settings->prefix_invoice ?? 'INV') }}"
                           maxlength="20" required placeholder="INV">
                    <div class="form-text">e.g. INV-00001</div>
                    @error('prefix_invoice')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4 col-lg-2">
                    <label class="form-label fw-semibold">S&amp;H Invoice</label>
                    <input type="text" name="prefix_sh_invoice"
                           class="form-control text-uppercase @error('prefix_sh_invoice') is-invalid @enderror"
                           value="{{ old('prefix_sh_invoice', $settings->prefix_sh_invoice ?? 'SH') }}"
                           maxlength="20" required placeholder="SH">
                    <div class="form-text">e.g. SH-00001</div>
                    @error('prefix_sh_invoice')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4 col-lg-2">
                    <label class="form-label fw-semibold">Survey</label>
                    <input type="text" name="prefix_survey"
                           class="form-control text-uppercase @error('prefix_survey') is-invalid @enderror"
                           value="{{ old('prefix_survey', $settings->prefix_survey ?? 'SRV') }}"
                           maxlength="20" required placeholder="SRV">
                    <div class="form-text">e.g. SRV-00001</div>
                    @error('prefix_survey')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4 col-lg-2">
                    <label class="form-label fw-semibold">Repair Estimate</label>
                    <input type="text" name="prefix_estimate"
                           class="form-control text-uppercase @error('prefix_estimate') is-invalid @enderror"
                           value="{{ old('prefix_estimate', $settings->prefix_estimate ?? 'RE') }}"
                           maxlength="20" required placeholder="RE">
                    <div class="form-text">e.g. RE-00001</div>
                    @error('prefix_estimate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4 col-lg-2">
                    <label class="form-label fw-semibold">Gate In</label>
                    <input type="text" name="prefix_gate_in"
                           class="form-control text-uppercase @error('prefix_gate_in') is-invalid @enderror"
                           value="{{ old('prefix_gate_in', $settings->prefix_gate_in ?? 'GIN') }}"
                           maxlength="20" required placeholder="GIN">
                    <div class="form-text">e.g. GIN-00001</div>
                    @error('prefix_gate_in')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4 col-lg-2">
                    <label class="form-label fw-semibold">Gate Out</label>
                    <input type="text" name="prefix_gate_out"
                           class="form-control text-uppercase @error('prefix_gate_out') is-invalid @enderror"
                           value="{{ old('prefix_gate_out', $settings->prefix_gate_out ?? 'GOUT') }}"
                           maxlength="20" required placeholder="GOUT">
                    <div class="form-text">e.g. GOUT-00001</div>
                    @error('prefix_gate_out')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

            </div>
        </div>
    </div>

    {{-- ── 3. Billing Defaults ── --}}
    <div class="card content-card mb-4">
        <div class="card-header py-2">
            <i class="bi bi-percent me-2 text-primary"></i>Billing Defaults
            <small class="text-muted fw-normal ms-2">— pre-filled values on new invoices and estimates</small>
        </div>
        <div class="card-body">
            <div class="row g-3">

                <div class="col-md-4">
                    <label class="form-label fw-semibold">
                        Default Tax Rate <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input type="number" name="default_tax_rate" step="0.01"
                               class="form-control @error('default_tax_rate') is-invalid @enderror"
                               value="{{ old('default_tax_rate', $settings->default_tax_rate ?? 0) }}"
                               min="0" max="100" required>
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">Applied to new invoices by default.</div>
                    @error('default_tax_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">
                        Overtime / After-Hours Surcharge <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input type="number" name="surcharge_overtime" step="0.01"
                               class="form-control @error('surcharge_overtime') is-invalid @enderror"
                               value="{{ old('surcharge_overtime', $settings->surcharge_overtime ?? 50) }}"
                               min="0" max="500" required>
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">Markup on labour charges after normal hours.</div>
                    @error('surcharge_overtime')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">
                        Night Shift Surcharge <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input type="number" name="surcharge_night" step="0.01"
                               class="form-control @error('surcharge_night') is-invalid @enderror"
                               value="{{ old('surcharge_night', $settings->surcharge_night ?? 75) }}"
                               min="0" max="500" required>
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">Markup on labour charges during night shift.</div>
                    @error('surcharge_night')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

            </div>
        </div>
    </div>

    {{-- ── 4. Gate Pass Print Defaults ── --}}
    <div class="card content-card mb-4">
        <div class="card-header py-2">
            <i class="bi bi-printer me-2 text-primary"></i>Gate Pass Print Defaults
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Select the default print format that opens automatically when a gate officer navigates to a gate pass.
                The format selector on the print page remains available so they can switch at any time.
            </p>
            @php
                $formatOptions = [
                    'full'        => 'Full A4 (portrait, 210 × 297 mm)',
                    'half'        => 'Landscape Half (A5 landscape, 210 × 148 mm)',
                    'half-custom' => 'Custom Half (A4 portrait, top half only)',
                ];
            @endphp
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Default Gate IN Pass Format</label>
                    <select name="default_gate_in_format" class="form-select">
                        @foreach($formatOptions as $val => $label)
                        <option value="{{ $val }}" {{ old('default_gate_in_format', $settings->default_gate_in_format ?? 'full') === $val ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Default Gate OUT Pass Format</label>
                    <select name="default_gate_out_format" class="form-select">
                        @foreach($formatOptions as $val => $label)
                        <option value="{{ $val }}" {{ old('default_gate_out_format', $settings->default_gate_out_format ?? 'full') === $val ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <div class="form-text">
                        <strong>Custom Half</strong> prints on A4 portrait paper but limits content to the top half of the page (~135 mm),
                        so the sheet can be cut in half. The bottom half of the page is intentionally left blank.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle me-1"></i>Save Settings
        </button>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>

</form>

@endsection
