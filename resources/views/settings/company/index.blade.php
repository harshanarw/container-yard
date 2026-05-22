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

{{-- Logo Card (standalone, outside the main form) --}}
<div class="card content-card mb-4">
    <div class="card-header py-2">
        <i class="bi bi-image me-2 text-primary"></i>Company Logo
    </div>
    <div class="card-body">
        <div class="row align-items-center g-4">
            <div class="col-md-4 text-center">
                @if($settings->logo_url)
                    <img id="logoPreview" src="{{ $settings->logo_url }}" alt="Company Logo"
                         style="max-height:120px; max-width:100%; object-fit:contain; border:1px solid #dee2e6; border-radius:8px; padding:8px; background:#f8f9fa;">
                @else
                    <div id="logoPreview" class="d-flex align-items-center justify-content-center bg-light border rounded"
                         style="height:120px; max-width:240px; margin:0 auto;">
                        <span class="text-muted small">No logo uploaded</span>
                    </div>
                @endif
            </div>

            <div class="col-md-8">
                {{-- Upload logo form --}}
                <form method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data" id="logoUploadForm">
                    @csrf
                    @method('POST')
                    {{-- Hidden fields to preserve required company_name --}}
                    <input type="hidden" name="company_name" value="{{ $settings->company_name }}">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Upload New Logo</label>
                        <input type="file" name="logo" id="logoInput" class="form-control @error('logo') is-invalid @enderror"
                               accept="image/*" onchange="previewLogo(this)">
                        @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Accepted: JPG, PNG, GIF, SVG. Max 2 MB.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-upload me-1"></i>Upload Logo
                        </button>

                        @if($settings->logo_path)
                        <form method="POST" action="{{ route('settings.company.logo.delete') }}" class="d-inline"
                              onsubmit="return confirm('Remove the current logo?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-trash me-1"></i>Remove Logo
                            </button>
                        </form>
                        @endif
                    </div>
                </form>
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
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Company Name <span class="text-danger">*</span></label>
                    <input type="text" name="company_name"
                           class="form-control @error('company_name') is-invalid @enderror"
                           value="{{ old('company_name', $settings->company_name) }}"
                           maxlength="200" required>
                    @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
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
                    <input type="text" name="country"
                           class="form-control @error('country') is-invalid @enderror"
                           value="{{ old('country', $settings->country) }}"
                           maxlength="100">
                    @error('country')<div class="invalid-feedback">{{ $message }}</div>@enderror
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

@endsection

@push('scripts')
<script>
function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            const preview = document.getElementById('logoPreview');
            if (preview.tagName === 'IMG') {
                preview.src = e.target.result;
            } else {
                // Replace placeholder div with an img
                const img = document.createElement('img');
                img.id = 'logoPreview';
                img.src = e.target.result;
                img.alt = 'Logo preview';
                img.style.cssText = 'max-height:120px; max-width:100%; object-fit:contain; border:1px solid #dee2e6; border-radius:8px; padding:8px; background:#f8f9fa;';
                preview.replaceWith(img);
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endpush
