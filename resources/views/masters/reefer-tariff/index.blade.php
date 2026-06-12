@extends('layouts.app')
@section('title', 'Reefer Electricity Tariffs')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="mb-0 fw-semibold"><i class="bi bi-plug-fill text-primary me-2"></i>Reefer Electricity Tariffs</h4>
        <p class="text-muted small mb-0">Define electricity billing rates for laden reefer containers.</p>
    </div>
    @can('masters.reefer-tariff.create')
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg me-1"></i>Add Tariff
    </button>
    @endcan
</div>


<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Customer</th>
                    <th>Tariff Name</th>
                    <th>Mode</th>
                    <th>Rate</th>
                    <th>Validity</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tariffs as $tariff)
                <tr>
                    <td>
                        @if($tariff->customer)
                            <span class="fw-medium">{{ $tariff->customer->name }}</span>
                            <div class="text-muted small">{{ $tariff->customer->code }}</div>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary border">Default</span>
                        @endif
                    </td>
                    <td>{{ $tariff->tariff_name }}</td>
                    <td>
                        @if($tariff->billing_mode === 'hourly')
                            <span class="badge bg-info-subtle text-info border border-info-subtle"><i class="bi bi-clock me-1"></i>Hourly</span>
                        @else
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-calendar-day me-1"></i>Daily</span>
                        @endif
                    </td>
                    <td>
                        <span class="font-monospace">{{ $tariff->rate_label }}</span>
                        @if($tariff->minimum_charge > 0)
                            <div class="text-muted small">Min: {{ $tariff->currency }} {{ number_format($tariff->minimum_charge, 2) }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="text-nowrap small">{{ $tariff->validity_label }}</span>
                    </td>
                    <td>
                        @if($tariff->is_active)
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('masters.reefer-tariff.show', $tariff) }}" class="btn btn-sm btn-outline-primary me-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @can('masters.reefer-tariff.edit')
                        <form action="{{ route('masters.reefer-tariff.toggle', $tariff) }}" method="POST" class="d-inline">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ $tariff->is_active ? 'Deactivate' : 'Activate' }}">
                                <i class="bi {{ $tariff->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                            </button>
                        </form>
                        @endcan
                        @can('masters.reefer-tariff.delete')
                        <form action="{{ route('masters.reefer-tariff.destroy', $tariff) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Delete this tariff?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger ms-1">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <i class="bi bi-plug fs-2 d-block mb-2 opacity-25"></i>
                        No reefer electricity tariffs defined yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Add Modal --}}
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('masters.reefer-tariff.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Reefer Electricity Tariff</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Customer <small class="text-muted">(leave blank for default)</small></label>
                            <select name="customer_id" class="form-select select2">
                                <option value="">— System Default —</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Tariff Name <span class="text-danger">*</span></label>
                            <input type="text" name="tariff_name" class="form-control" required placeholder="e.g. Standard Reefer Electricity">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Billing Mode <span class="text-danger">*</span></label>
                            <select name="billing_mode" class="form-select" id="addBillingMode">
                                <option value="daily">Daily (calendar days)</option>
                                <option value="hourly">Hourly (ceiling)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Currency <span class="text-danger">*</span></label>
                            <input type="text" name="currency" class="form-control font-monospace" value="LKR" maxlength="3" required>
                        </div>
                        <div class="col-md-4" id="addHourlyRateWrap" style="display:none">
                            <label class="form-label">Hourly Rate</label>
                            <input type="number" name="hourly_rate" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-4" id="addDailyRateWrap">
                            <label class="form-label">Daily Rate</label>
                            <input type="number" name="daily_rate" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-4" id="addFreeHoursWrap" style="display:none">
                            <label class="form-label">Free Hours</label>
                            <input type="number" name="free_hours" class="form-control" value="0" min="0" max="168">
                        </div>
                        <div class="col-md-4" id="addFreeDaysWrap">
                            <label class="form-label">Free Days</label>
                            <input type="number" name="free_days" class="form-control" value="0" min="0" max="365">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Minimum Charge</label>
                            <input type="number" name="minimum_charge" class="form-control" value="0" step="0.01" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Valid From <span class="text-danger">*</span></label>
                            <input type="date" name="valid_from" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Valid To</label>
                            <input type="date" name="valid_to" class="form-control">
                        </div>
                        <div class="col-md-4 d-flex align-items-end pb-1">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="addIsActive" value="1" checked>
                                <label class="form-check-label" for="addIsActive">Active</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    @can('masters.reefer-tariff.create')
                    <button type="submit" class="btn btn-primary">Save Tariff</button>
                    @endcan
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const modeSelect = document.getElementById('addBillingMode');
    const hourlyRate = document.getElementById('addHourlyRateWrap');
    const dailyRate  = document.getElementById('addDailyRateWrap');
    const freeHours  = document.getElementById('addFreeHoursWrap');
    const freeDays   = document.getElementById('addFreeDaysWrap');

    function toggleMode() {
        const isHourly = modeSelect.value === 'hourly';
        hourlyRate.style.display = isHourly ? '' : 'none';
        dailyRate.style.display  = isHourly ? 'none' : '';
        freeHours.style.display  = isHourly ? '' : 'none';
        freeDays.style.display   = isHourly ? 'none' : '';
    }

    modeSelect?.addEventListener('change', toggleMode);
    toggleMode();
})();
</script>
@endpush
