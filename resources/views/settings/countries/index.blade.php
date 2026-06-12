@extends('layouts.app')

@section('title', 'Country List')

@section('breadcrumb')
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item">Configuration</li>
    <li class="breadcrumb-item active">Country List</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-globe me-2 text-primary"></i>Country List</h4>
        <p class="text-muted mb-0 small">
            Manage countries used across customers, company settings and currencies. Toggle active to include/exclude from dropdowns.
        </p>
    </div>
    @can('masters.countries.create')
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle me-1"></i>Add Country
    </button>
    @endcan
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
        <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
        <i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- ── Stats strip ── --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-primary">{{ $countries->count() }}</div>
            <div class="text-muted small">Total Countries</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-success">{{ $countries->where('is_active', true)->count() }}</div>
            <div class="text-muted small">Active</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-secondary">{{ $countries->where('is_active', false)->count() }}</div>
            <div class="text-muted small">Inactive</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-info">{{ $regions->count() }}</div>
            <div class="text-muted small">Regions</div>
        </div>
    </div>
</div>

{{-- ── Region filter ── --}}
<div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
    <a href="{{ route('settings.countries.index') }}"
       class="btn btn-sm {{ !request('region') ? 'btn-dark' : 'btn-outline-secondary' }}">
        All <span class="badge bg-white text-dark ms-1">{{ $countries->count() }}</span>
    </a>
    @foreach($regions as $region)
        @php $count = $countries->where('region', $region)->count(); @endphp
        <a href="{{ route('settings.countries.index', ['region' => $region]) }}"
           class="btn btn-sm {{ request('region') === $region ? 'btn-primary' : 'btn-outline-secondary' }}">
            {{ $region }}
            <span class="badge {{ request('region') === $region ? 'bg-white text-primary' : 'bg-secondary' }} ms-1">{{ $count }}</span>
        </a>
    @endforeach
</div>

{{-- ── Country table ── --}}
<div class="card content-card">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <span><i class="bi bi-table me-2 text-primary"></i>Countries</span>
        <span class="badge bg-primary-subtle text-primary">{{ $countries->count() }} record(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="countryTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:50px;">#</th>
                        <th style="width:50px;" class="text-center">Flag</th>
                        <th>Name</th>
                        <th style="width:60px;" class="text-center">ISO2</th>
                        <th style="width:60px;" class="text-center">ISO3</th>
                        <th style="width:90px;">Phone Code</th>
                        <th style="width:160px;">Capital</th>
                        <th style="width:80px;" class="text-center">Currency</th>
                        <th style="width:130px;">Region</th>
                        <th style="width:90px;" class="text-center">Status</th>
                        <th style="width:90px;" class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($countries as $country)
                    <tr class="{{ $country->is_active ? '' : 'table-secondary text-muted' }}">
                        <td class="ps-3 text-muted small fw-semibold">{{ $country->id }}</td>
                        <td class="text-center" style="font-size:1.4rem;">{{ $country->flag_emoji }}</td>
                        <td>
                            <div class="fw-semibold small">{{ $country->name }}</div>
                            @if($country->subregion)
                                <div class="text-muted" style="font-size:.7rem;">{{ $country->subregion }}</div>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary fw-bold">{{ $country->iso2 }}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary-subtle text-secondary border">{{ $country->iso3 ?? '—' }}</span>
                        </td>
                        <td class="small">
                            @if($country->phone_code)
                                <span class="text-muted">+</span>{{ $country->phone_code }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="small">{{ $country->capital ?? '—' }}</td>
                        <td class="text-center">
                            @if($country->currency_code)
                                <span class="badge bg-info-subtle text-info border border-info-subtle" title="{{ $country->currency_name }}">
                                    {{ $country->currency_code }}
                                </span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            @if($country->region)
                                <span class="badge bg-light border text-muted small">{{ $country->region }}</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @can('masters.countries.edit')
                            <form method="POST" action="{{ route('settings.countries.toggle', $country) }}">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="btn btn-sm {{ $country->is_active ? 'btn-success' : 'btn-outline-secondary' }}"
                                        title="{{ $country->is_active ? 'Active – click to deactivate' : 'Inactive – click to activate' }}">
                                    <i class="bi {{ $country->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                </button>
                            </form>
                            @endcan
                        </td>
                        <td class="text-end pe-3">
                            <div class="d-flex flex-wrap justify-content-end gap-1">
                                @can('masters.countries.edit')
                                <button type="button" class="btn btn-sm btn-outline-primary btn-edit"
                                        data-id="{{ $country->id }}"
                                        data-name="{{ $country->name }}"
                                        data-iso2="{{ $country->iso2 }}"
                                        data-iso3="{{ $country->iso3 ?? '' }}"
                                        data-phone_code="{{ $country->phone_code ?? '' }}"
                                        data-capital="{{ $country->capital ?? '' }}"
                                        data-currency_code="{{ $country->currency_code ?? '' }}"
                                        data-currency_name="{{ $country->currency_name ?? '' }}"
                                        data-currency_symbol="{{ $country->currency_symbol ?? '' }}"
                                        data-region="{{ $country->region ?? '' }}"
                                        data-subregion="{{ $country->subregion ?? '' }}"
                                        title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                @endcan
                                @can('masters.countries.delete')
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete"
                                        data-id="{{ $country->id }}"
                                        data-label="{{ $country->name }}"
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="text-center text-muted py-5">
                            <i class="bi bi-globe fs-3 d-block mb-2"></i>
                            No countries found. Click <strong>Add Country</strong> or run the seeder.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-2">
        <span class="text-muted small">
            {{ $countries->count() }} countries
            · {{ $countries->where('is_active', true)->count() }} active
        </span>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     ADD MODAL
══════════════════════════════════════════════════════ --}}
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('settings.countries.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title">
                        <i class="bi bi-plus-circle me-1 text-primary"></i>Add Country
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Country Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" maxlength="100" required placeholder="e.g. Sri Lanka">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">ISO2 Code <span class="text-danger">*</span></label>
                            <input type="text" name="iso2" class="form-control text-uppercase" maxlength="2" minlength="2" required placeholder="LK">
                            <div class="form-text">2-letter ISO 3166-1 code</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">ISO3 Code</label>
                            <input type="text" name="iso3" class="form-control text-uppercase" maxlength="3" minlength="3" placeholder="LKA">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Phone Code</label>
                            <div class="input-group">
                                <span class="input-group-text">+</span>
                                <input type="text" name="phone_code" class="form-control" maxlength="20" placeholder="94">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Capital</label>
                            <input type="text" name="capital" class="form-control" maxlength="100" placeholder="e.g. Colombo">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Region</label>
                            <select name="region" class="form-select">
                                <option value="">— Select Region —</option>
                                @foreach(['Africa','Americas','Asia','Europe','Oceania'] as $r)
                                    <option value="{{ $r }}">{{ $r }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Sub-region</label>
                            <input type="text" name="subregion" class="form-control" maxlength="100" placeholder="e.g. Southern Asia">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Currency Code</label>
                            <input type="text" name="currency_code" class="form-control text-uppercase" maxlength="10" placeholder="LKR">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Currency Name</label>
                            <input type="text" name="currency_name" class="form-control" maxlength="100" placeholder="Sri Lankan Rupee">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Currency Symbol</label>
                            <input type="text" name="currency_symbol" class="form-control" maxlength="20" placeholder="₨">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    @can('masters.countries.create')
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Add
                    </button>
                    @endcan
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     EDIT MODAL
══════════════════════════════════════════════════════ --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editForm">
                @csrf @method('PATCH')
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title">
                        <i class="bi bi-pencil me-1 text-primary"></i>Edit Country
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Country Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="editName" class="form-control" maxlength="100" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">ISO2 Code <span class="text-danger">*</span></label>
                            <input type="text" name="iso2" id="editIso2" class="form-control text-uppercase" maxlength="2" minlength="2" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">ISO3 Code</label>
                            <input type="text" name="iso3" id="editIso3" class="form-control text-uppercase" maxlength="3" minlength="3">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Phone Code</label>
                            <div class="input-group">
                                <span class="input-group-text">+</span>
                                <input type="text" name="phone_code" id="editPhoneCode" class="form-control" maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Capital</label>
                            <input type="text" name="capital" id="editCapital" class="form-control" maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Region</label>
                            <select name="region" id="editRegion" class="form-select">
                                <option value="">— Select Region —</option>
                                @foreach(['Africa','Americas','Asia','Europe','Oceania'] as $r)
                                    <option value="{{ $r }}">{{ $r }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Sub-region</label>
                            <input type="text" name="subregion" id="editSubregion" class="form-control" maxlength="100">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Currency Code</label>
                            <input type="text" name="currency_code" id="editCurrencyCode" class="form-control text-uppercase" maxlength="10">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Currency Name</label>
                            <input type="text" name="currency_name" id="editCurrencyName" class="form-control" maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Currency Symbol</label>
                            <input type="text" name="currency_symbol" id="editCurrencySymbol" class="form-control" maxlength="20">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    @can('masters.countries.edit')
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                    @endcan
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     DELETE MODAL
══════════════════════════════════════════════════════ --}}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title text-danger">
                    <i class="bi bi-exclamation-triangle me-1"></i>Delete Country
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1">
                <p class="small mb-0">Delete country <strong id="deleteLabel"></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                @can('masters.countries.delete')
                <form id="deleteForm" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
                @endcan
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if ($.fn.DataTable) {
        $('#countryTable').DataTable({
            pageLength: 25,
            order: [[2, 'asc']],
            columnDefs: [
                { orderable: false, targets: [1, 9, 10] },
            ],
        });
    }

    // Edit modal
    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('editName').value          = btn.dataset.name;
            document.getElementById('editIso2').value          = btn.dataset.iso2;
            document.getElementById('editIso3').value          = btn.dataset.iso3 || '';
            document.getElementById('editPhoneCode').value     = btn.dataset.phone_code || '';
            document.getElementById('editCapital').value       = btn.dataset.capital || '';
            document.getElementById('editRegion').value        = btn.dataset.region || '';
            document.getElementById('editSubregion').value     = btn.dataset.subregion || '';
            document.getElementById('editCurrencyCode').value  = btn.dataset.currency_code || '';
            document.getElementById('editCurrencyName').value  = btn.dataset.currency_name || '';
            document.getElementById('editCurrencySymbol').value = btn.dataset.currency_symbol || '';
            document.getElementById('editForm').action =
                '{{ url("settings/countries") }}/' + btn.dataset.id;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        });
    });

    // Delete modal
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('deleteLabel').textContent = btn.dataset.label;
            document.getElementById('deleteForm').action =
                '{{ url("settings/countries") }}/' + btn.dataset.id;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        });
    });
});
</script>
@endpush
