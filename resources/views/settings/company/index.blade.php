@extends('layouts.app')

@section('title', 'Company Settings')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('settings.index') }}" class="text-decoration-none">Settings</a></li>
    <li class="breadcrumb-item active">Company Settings</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-building me-2 text-primary"></i>Company Settings</h4>
        <p class="text-muted mb-0 small">Manage your company profile, branding and contact details</p>
    </div>
</div>

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

{{-- Branding row: Logo + Icon + Product Icon --}}
<div class="row g-4 mb-4">

    {{-- Logo Card --}}
    <div class="col-md-4">
        <div class="card content-card h-100">
            <div class="card-header py-2">
                <i class="bi bi-image me-2 text-primary"></i>Company Logo
                <small class="text-muted fw-normal ms-1">— shown on login screen</small>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    @if($settings->logo_url)
                        <img id="logoPreview" src="{{ $settings->logo_url }}" alt="Company Logo"
                             style="max-height:100px; max-width:100%; object-fit:contain; border:1px solid #dee2e6; border-radius:8px; padding:8px; background:#f8f9fa;">
                    @else
                        <div id="logoPreview" class="d-flex align-items-center justify-content-center bg-light border rounded mx-auto"
                             style="height:100px; max-width:220px;">
                            <span class="text-muted small">No logo uploaded</span>
                        </div>
                    @endif
                </div>

                <form method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="company_name" value="{{ $settings->company_name }}">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Upload New Logo</label>
                        <input type="file" name="logo" id="logoInput" class="form-control form-control-sm @error('logo') is-invalid @enderror"
                               accept="image/*" onchange="previewImage(this,'logoPreview')">
                        @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">JPG, PNG, SVG. Max 2 MB.</div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-upload me-1"></i>Upload Logo
                    </button>
                </form>

                @if($settings->logo_path)
                <form method="POST" action="{{ route('settings.company.logo.delete') }}" class="mt-2"
                      onsubmit="return confirm('Remove the current logo?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash me-1"></i>Remove Logo
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Icon Card --}}
    <div class="col-md-4">
        <div class="card content-card h-100">
            <div class="card-header py-2">
                <i class="bi bi-badge me-2 text-primary"></i>Company Icon
                <small class="text-muted fw-normal ms-1">— browser tab favicon</small>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    @if($settings->icon_url)
                        <img id="iconPreview" src="{{ $settings->icon_url }}" alt="Company Icon"
                             style="width:80px; height:80px; object-fit:contain; border:1px solid #dee2e6; border-radius:8px; padding:6px; background:#f8f9fa;">
                    @else
                        <div id="iconPreview" class="d-flex align-items-center justify-content-center bg-light border rounded mx-auto"
                             style="width:80px; height:80px;">
                            <i class="bi bi-boxes text-muted fs-3"></i>
                        </div>
                    @endif
                    <div class="text-muted mt-2" style="font-size:.75rem;">Recommended: 64×64 or 128×128 px</div>
                </div>

                <form method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="company_name" value="{{ $settings->company_name }}">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Upload New Icon</label>
                        <input type="file" name="icon" id="iconInput" class="form-control form-control-sm @error('icon') is-invalid @enderror"
                               accept=".jpg,.jpeg,.png,.ico,.svg,.webp" onchange="previewImage(this,'iconPreview')">
                        @error('icon')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">JPG, PNG, ICO, SVG, WebP. Max 512 KB.</div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-upload me-1"></i>Upload Icon
                    </button>
                </form>

                @if($settings->icon_path)
                <form method="POST" action="{{ route('settings.company.icon.delete') }}" class="mt-2"
                      onsubmit="return confirm('Remove the current icon?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash me-1"></i>Remove Icon
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Product Icon Card --}}
    <div class="col-md-4">
        <div class="card content-card h-100">
            <div class="card-header py-2">
                <i class="bi bi-grid me-2 text-primary"></i>Product Icon
                <small class="text-muted fw-normal ms-1">— sidebar brand area</small>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    @if($settings->product_icon_url)
                        <img id="productIconPreview" src="{{ $settings->product_icon_url }}" alt="Product Icon"
                             style="width:80px; height:80px; object-fit:contain; border:1px solid #dee2e6; border-radius:8px; padding:6px; background:#f8f9fa;">
                    @else
                        <div id="productIconPreview" class="d-flex align-items-center justify-content-center bg-light border rounded mx-auto"
                             style="width:80px; height:80px;">
                            <i class="bi bi-grid text-muted fs-3"></i>
                        </div>
                    @endif
                    <div class="text-muted mt-2" style="font-size:.75rem;">Shown next to company name in the left sidebar</div>
                </div>

                <form method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="company_name" value="{{ $settings->company_name }}">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Upload Product Icon</label>
                        <input type="file" name="product_icon" id="productIconInput"
                               class="form-control form-control-sm @error('product_icon') is-invalid @enderror"
                               accept=".jpg,.jpeg,.png,.ico,.svg,.webp"
                               onchange="previewImage(this,'productIconPreview')">
                        @error('product_icon')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">JPG, PNG, ICO, SVG, WebP. Max 512 KB. Square works best.</div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-upload me-1"></i>Upload Product Icon
                    </button>
                </form>

                @if($settings->product_icon_path)
                <form method="POST" action="{{ route('settings.company.product-icon.delete') }}" class="mt-2"
                      onsubmit="return confirm('Remove the current product icon?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash me-1"></i>Remove Product Icon
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>

</div>

{{-- Main Details + Contact Form --}}
<form method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data" id="companyForm">
    @csrf

    {{-- Company Details --}}
    <div class="card content-card mb-4">
        <div class="card-header py-2">
            <i class="bi bi-info-circle me-2 text-primary"></i>Company Details
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Company Name <span class="text-danger">*</span></label>
                    <input type="text" name="company_name"
                           class="form-control @error('company_name') is-invalid @enderror"
                           value="{{ old('company_name', $settings->company_name) }}"
                           maxlength="200" required>
                    @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">
                        Company Prefix
                        <i class="bi bi-info-circle text-muted ms-1"
                           title="Short code used in auto-generated reference numbers. Letters and numbers only. e.g. ABC, CY01"></i>
                    </label>
                    <input type="text" name="company_prefix"
                           class="form-control text-uppercase @error('company_prefix') is-invalid @enderror"
                           value="{{ old('company_prefix', $settings->company_prefix) }}"
                           maxlength="10" placeholder="e.g. ABC">
                    <div class="form-text">Max 10 chars. Used in reference numbers.</div>
                    @error('company_prefix')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Tagline</label>
                    <input type="text" name="tagline"
                           class="form-control @error('tagline') is-invalid @enderror"
                           value="{{ old('tagline', $settings->tagline) }}"
                           maxlength="200"
                           placeholder="e.g. Container Yard Management System">
                    @error('tagline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Address</label>
                    <textarea name="address"
                              class="form-control @error('address') is-invalid @enderror"
                              rows="3"
                              placeholder="Street address">{{ old('address', $settings->address) }}</textarea>
                    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">City</label>
                    <input type="text" name="city"
                           class="form-control @error('city') is-invalid @enderror"
                           value="{{ old('city', $settings->city) }}"
                           maxlength="100">
                    @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Country</label>
                    <select name="country_id"
                            class="form-select select2 @error('country_id') is-invalid @enderror">
                        <option value="">— Select Country —</option>
                        @foreach($countries as $c)
                            <option value="{{ $c->id }}"
                                {{ old('country_id', $settings->country_id) == $c->id ? 'selected' : '' }}>
                                {{ $c->flag_emoji }} {{ $c->name }} ({{ $c->iso2 }})
                            </option>
                        @endforeach
                    </select>
                    @error('country_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Software Provider</label>
                    <input type="text" name="software_provider"
                           class="form-control @error('software_provider') is-invalid @enderror"
                           value="{{ old('software_provider', $settings->software_provider) }}"
                           maxlength="200"
                           placeholder="e.g. GenSoft Solutions (Pvt) Ltd">
                    <div class="form-text">Shown as copyright credit on the login screen and sidebar footer.</div>
                    @error('software_provider')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Contact & Identity --}}
    <div class="card content-card mb-4">
        <div class="card-header py-2">
            <i class="bi bi-telephone me-2 text-primary"></i>Contact &amp; Identity
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Telephone</label>
                    <input type="text" name="telephone"
                           class="form-control @error('telephone') is-invalid @enderror"
                           value="{{ old('telephone', $settings->telephone) }}"
                           maxlength="50"
                           placeholder="+94 11 234 5678">
                    @error('telephone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email"
                           class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email', $settings->email) }}"
                           maxlength="200"
                           placeholder="info@company.com">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Website</label>
                    <input type="text" name="website"
                           class="form-control @error('website') is-invalid @enderror"
                           value="{{ old('website', $settings->website) }}"
                           maxlength="200"
                           placeholder="https://www.company.com">
                    @error('website')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">VAT Registration No.</label>
                    <input type="text" name="vat_number"
                           class="form-control @error('vat_number') is-invalid @enderror"
                           value="{{ old('vat_number', $settings->vat_number) }}"
                           maxlength="100">
                    @error('vat_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">TIN / Tax ID No.</label>
                    <input type="text" name="tin_number"
                           class="form-control @error('tin_number') is-invalid @enderror"
                           value="{{ old('tin_number', $settings->tin_number) }}"
                           maxlength="100">
                    @error('tin_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle me-1"></i>Save Settings
        </button>
        <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>

</form>

{{-- Default Currency — standalone form, outside the company settings form to avoid nesting --}}
@php $defaultCurrency = $currencies->firstWhere('is_default', true); @endphp
<div class="card content-card mt-4">
    <div class="card-header py-2 d-flex align-items-center justify-content-between">
        <span><i class="bi bi-currency-exchange me-2 text-primary"></i>Default Currency</span>
        <small class="text-muted">The system-wide default currency for invoicing and reporting</small>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('settings.company.default-currency') }}" class="row g-3 align-items-end">
            @csrf @method('PATCH')
            <div class="col-md-5">
                <label class="form-label fw-semibold">Default Currency <span class="text-danger">*</span></label>
                <select name="currency_id" class="form-select select2 s2-code" required>
                    <option value="">— Select Currency —</option>
                    @foreach($currencies as $cur)
                        <option value="{{ $cur->id }}" data-code="{{ $cur->code }}" data-name="{{ $cur->name }}" {{ $defaultCurrency?->id === $cur->id ? 'selected' : '' }}>
                            [{{ $cur->code }}] {{ $cur->name }}{{ $cur->country ? ' — ' . $cur->country : '' }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Applies to all new invoices and financial records</div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-star me-1"></i>Set as Default
                </button>
            </div>
            @if($defaultCurrency)
                <div class="col-12">
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded"
                         style="background:#fffbea;border:1px solid #ffc107;">
                        <i class="bi bi-star-fill text-warning"></i>
                        <span class="small">Current default:
                            <strong>{{ $defaultCurrency->code }}</strong> — {{ $defaultCurrency->name }}
                            @if($defaultCurrency->country)
                                <span class="text-muted">({{ $defaultCurrency->country }})</span>
                            @endif
                            @if($defaultCurrency->symbol)
                                <span class="badge bg-warning text-dark ms-1">{{ $defaultCurrency->symbol }}</span>
                            @endif
                        </span>
                    </div>
                </div>
            @endif
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
function previewImage(input, previewId) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function (e) {
        const el = document.getElementById(previewId);
        if (el.tagName === 'IMG') {
            el.src = e.target.result;
        } else {
            const img = document.createElement('img');
            img.id = previewId;
            img.src = e.target.result;
            img.alt = 'Preview';
            img.style.cssText = el.style.cssText;
            el.replaceWith(img);
        }
    };
    reader.readAsDataURL(input.files[0]);
}
</script>
@endpush
