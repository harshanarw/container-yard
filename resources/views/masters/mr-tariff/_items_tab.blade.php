{{-- ── Slab Items Tab ── --}}

{{-- Add Item Form --}}
@can('masters.mr-tariff.create')
<div class="card content-card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <span><i class="bi bi-plus-circle me-2 text-primary"></i>Add Tariff Item</span>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('masters.mr-tariff.items.store', $mrTariff) }}">
            @csrf
            <div class="row g-2 mb-2">
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">Tariff Code</label>
                    <input type="text" name="tariff_code" class="form-control form-control-sm font-monospace"
                           maxlength="20" placeholder="e.g. GS-01-01">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">Operation Type <span class="text-danger">*</span></label>
                    <select name="operation_type" class="form-select form-select-sm" required>
                        <option value="">— Select —</option>
                        @foreach($operationTypes as $op)
                            <option value="{{ $op }}">{{ ucfirst($op) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Description <span class="text-danger">*</span></label>
                    <input type="text" name="description" class="form-control form-control-sm"
                           maxlength="150" placeholder="e.g. CROSS MEMBER" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">Unit Type <span class="text-danger">*</span></label>
                    <select name="unit_type" class="form-select form-select-sm" required>
                        @foreach($unitTypes as $ut)
                            <option value="{{ $ut }}">{{ strtoupper($ut) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">Notes</label>
                    <input type="text" name="notes" class="form-control form-control-sm" maxlength="500">
                </div>
            </div>
            <div class="text-end">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>Add Item
                </button>
            </div>
        </form>
    </div>
</div>
@endcan

{{-- Items List --}}
<div class="card content-card">
    <div class="card-header py-2 d-flex align-items-center justify-content-between">
        <span><i class="bi bi-list-ul me-2 text-primary"></i>Tariff Items
            <span class="badge bg-secondary ms-1">{{ $mrTariff->items->count() }}</span>
        </span>
        <div class="d-flex gap-2 align-items-center">
            <input type="text" id="itemSearch" class="form-control form-control-sm" style="width:200px"
                   placeholder="Filter items...">
        </div>
    </div>
    <div class="card-body p-0">
        @forelse($mrTariff->items->sortBy('sort_order') as $item)
        <div class="item-row border-bottom" data-desc="{{ strtolower($item->description) }}" data-code="{{ strtolower($item->tariff_code ?? '') }}">
            {{-- Item header row --}}
            <div class="d-flex align-items-center justify-content-between px-3 py-2 bg-light">
                <div class="d-flex align-items-center gap-2 flex-grow-1">
                    <button class="btn btn-xs btn-outline-secondary" type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#itemSlabs{{ $item->id }}"
                            title="Toggle slabs">
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    @if($item->tariff_code)
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">{{ $item->tariff_code }}</span>
                    @endif
                    <span class="badge bg-{{ match($item->operation_type) {
                        'straight' => 'info',
                        'insert'   => 'success',
                        'section'  => 'warning',
                        'replace'  => 'danger',
                        'weld'     => 'secondary',
                        'remove'   => 'dark',
                        'paint'    => 'primary',
                        'resecure' => 'info',
                        'free'     => 'light',
                        default    => 'secondary'
                    } }}-subtle text-{{ match($item->operation_type) {
                        'straight' => 'info',
                        'insert'   => 'success',
                        'section'  => 'warning',
                        'replace'  => 'danger',
                        'weld'     => 'secondary',
                        'remove'   => 'dark',
                        'paint'    => 'primary',
                        'resecure' => 'info',
                        'free'     => 'secondary',
                        default    => 'secondary'
                    } }} border text-uppercase small">{{ $item->operation_type }}</span>
                    <strong class="small">{{ $item->description }}</strong>
                    <span class="text-muted small">/ {{ strtoupper($item->unit_type) }}</span>
                    <span class="badge bg-light border text-muted small">{{ $item->slabs->count() }} slab(s)</span>
                    @if(!$item->is_active)
                        <span class="badge bg-secondary">Inactive</span>
                    @endif
                </div>
                <div class="d-flex gap-1">
                    @can('masters.mr-tariff.edit')
                    <button type="button" class="btn btn-xs btn-outline-primary btn-edit-item"
                            data-id="{{ $item->id }}"
                            data-tariff_code="{{ $item->tariff_code }}"
                            data-operation_type="{{ $item->operation_type }}"
                            data-description="{{ $item->description }}"
                            data-unit_type="{{ $item->unit_type }}"
                            data-notes="{{ $item->notes }}"
                            data-is_active="{{ $item->is_active ? '1' : '0' }}"
                            title="Edit Item">
                        <i class="bi bi-pencil"></i>
                    </button>
                    @endcan
                    @can('masters.mr-tariff.delete')
                    <form method="POST" action="{{ route('masters.mr-tariff.items.destroy', [$mrTariff, $item]) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-xs btn-outline-danger"
                                data-confirm="Delete item &quot;{{ $item->description }}&quot; and all its slabs?"
                                data-confirm-title="Delete Item"
                                data-confirm-class="btn-danger"
                                data-confirm-label="Delete"
                                title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                    @endcan
                </div>
            </div>

            {{-- Slabs accordion --}}
            <div class="collapse show" id="itemSlabs{{ $item->id }}">
                <div class="px-3 pb-2 pt-1">
                    {{-- Existing slabs table --}}
                    @if($item->slabs->count())
                    <table class="table table-sm align-middle mb-2 small">
                        <thead class="table-light">
                            <tr>
                                <th>Label</th>
                                <th class="text-end">Qty From</th>
                                <th class="text-center">Each Add.</th>
                                <th class="text-end">Labor Hrs</th>
                                <th class="text-end">Material $</th>
                                <th style="width:80px"></th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($item->slabs->sortBy('sort_order') as $slab)
                        <tr>
                            <td>
                                <span class="badge {{ $slab->is_additional ? 'bg-warning-subtle text-warning border border-warning-subtle' : 'bg-light border text-dark' }}">
                                    {{ $slab->slab_label }}
                                </span>
                            </td>
                            <td class="text-end font-monospace">{{ number_format($slab->qty_from, 3) }}</td>
                            <td class="text-center">
                                @if($slab->is_additional)
                                    <i class="bi bi-check-circle-fill text-warning"></i>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end font-monospace">{{ number_format($slab->labor_hours, 3) }}</td>
                            <td class="text-end font-monospace">{{ number_format($slab->material_cost, 2) }}</td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    @can('masters.mr-tariff.edit')
                                    <button type="button" class="btn btn-xs btn-outline-primary btn-edit-slab"
                                            data-slab-id="{{ $slab->id }}"
                                            data-item-id="{{ $item->id }}"
                                            data-slab_label="{{ $slab->slab_label }}"
                                            data-qty_from="{{ $slab->qty_from }}"
                                            data-is_additional="{{ $slab->is_additional ? '1' : '0' }}"
                                            data-labor_hours="{{ $slab->labor_hours }}"
                                            data-material_cost="{{ $slab->material_cost }}"
                                            title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @endcan
                                    @can('masters.mr-tariff.delete')
                                    <form method="POST"
                                          action="{{ route('masters.mr-tariff.items.slabs.destroy', [$mrTariff, $item, $slab]) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger"
                                                data-confirm="Delete this slab?"
                                                data-confirm-title="Delete Slab"
                                                data-confirm-class="btn-danger"
                                                data-confirm-label="Delete"
                                                title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @else
                    <p class="text-muted small mb-2"><i class="bi bi-info-circle me-1"></i>No slabs yet. Add one below.</p>
                    @endif

                    {{-- Add slab inline form --}}
                    @can('masters.mr-tariff.create')
                    <form method="POST"
                          action="{{ route('masters.mr-tariff.items.slabs.store', [$mrTariff, $item]) }}"
                          class="row g-2 align-items-end border-top pt-2">
                        @csrf
                        <div class="col-md-3">
                            <label class="form-label form-label-sm fw-semibold mb-1">Label</label>
                            <input type="text" name="slab_label" class="form-control form-control-sm"
                                   value="Base" maxlength="60" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm fw-semibold mb-1">Qty From</label>
                            <input type="number" name="qty_from" class="form-control form-control-sm"
                                   step="0.001" min="0" value="0" required>
                        </div>
                        <div class="col-md-1 text-center">
                            <label class="form-label form-label-sm fw-semibold mb-1 d-block">Each Add.</label>
                            <div class="form-check form-check-inline mt-1">
                                <input class="form-check-input" type="checkbox" name="is_additional" value="1">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm fw-semibold mb-1">Labor Hrs</label>
                            <input type="number" name="labor_hours" class="form-control form-control-sm"
                                   step="0.001" min="0" value="0" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm fw-semibold mb-1">Material $</label>
                            <input type="number" name="material_cost" class="form-control form-control-sm"
                                   step="0.01" min="0" value="0" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-sm btn-success w-100">
                                <i class="bi bi-plus-circle me-1"></i>Add Slab
                            </button>
                        </div>
                    </form>
                    @endcan
                </div>
            </div>
        </div>
        @empty
        <div class="text-center text-muted py-5">
            <i class="bi bi-grid-3x3-gap fs-2 d-block mb-2"></i>
            No slab items yet. Use the form above to add one.
        </div>
        @endforelse
    </div>
</div>

{{-- Edit Item Modal --}}
<div class="modal fade" id="editItemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editItemForm">
                @csrf @method('PATCH')
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-pencil me-1 text-primary"></i>Edit Tariff Item</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-2">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Tariff Code</label>
                            <input type="text" name="tariff_code" id="eiTariffCode"
                                   class="form-control form-control-sm font-monospace" maxlength="20">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Operation Type <span class="text-danger">*</span></label>
                            <select name="operation_type" id="eiOpType" class="form-select form-select-sm" required>
                                @foreach($operationTypes as $op)
                                    <option value="{{ $op }}">{{ ucfirst($op) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Description <span class="text-danger">*</span></label>
                            <input type="text" name="description" id="eiDescription"
                                   class="form-control form-control-sm" maxlength="150" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Unit Type</label>
                            <select name="unit_type" id="eiUnitType" class="form-select form-select-sm" required>
                                @foreach($unitTypes as $ut)
                                    <option value="{{ $ut }}">{{ strtoupper($ut) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-semibold small">Notes</label>
                            <input type="text" name="notes" id="eiNotes" class="form-control form-control-sm" maxlength="500">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="eiIsActive" value="1">
                                <label class="form-check-label small" for="eiIsActive">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    @can('masters.mr-tariff.edit')
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                    @endcan
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Slab Modal --}}
<div class="modal fade" id="editSlabModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editSlabForm">
                @csrf @method('PATCH')
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-pencil me-1 text-primary"></i>Edit Slab</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Label</label>
                            <input type="text" name="slab_label" id="esLabel"
                                   class="form-control form-control-sm" maxlength="60" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Qty From</label>
                            <input type="number" name="qty_from" id="esQtyFrom"
                                   class="form-control form-control-sm" step="0.001" min="0" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Labor Hours</label>
                            <input type="number" name="labor_hours" id="esLaborHours"
                                   class="form-control form-control-sm" step="0.001" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Material Cost</label>
                            <input type="number" name="material_cost" id="esMaterialCost"
                                   class="form-control form-control-sm" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 d-flex align-items-end pb-1">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_additional" id="esIsAdditional" value="1">
                                <label class="form-check-label small" for="esIsAdditional">Each Additional</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    @can('masters.mr-tariff.edit')
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-save me-1"></i>Save Slab
                    </button>
                    @endcan
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
// ── Edit Item Modal ──────────────────────────────────────────────────────────
document.querySelectorAll('.btn-edit-item').forEach(btn => {
    btn.addEventListener('click', () => {
        const d = btn.dataset;
        document.getElementById('eiTariffCode').value  = d.tariff_code  || '';
        document.getElementById('eiOpType').value      = d.operation_type || '';
        document.getElementById('eiDescription').value = d.description  || '';
        document.getElementById('eiUnitType').value    = d.unit_type    || '';
        document.getElementById('eiNotes').value       = d.notes        || '';
        document.getElementById('eiIsActive').checked  = d.is_active === '1';

        document.getElementById('editItemForm').action =
            '{{ url("masters/mr-tariff/" . $mrTariff->id . "/items") }}/' + d.id;

        new bootstrap.Modal(document.getElementById('editItemModal')).show();
    });
});

// ── Edit Slab Modal ──────────────────────────────────────────────────────────
document.querySelectorAll('.btn-edit-slab').forEach(btn => {
    btn.addEventListener('click', () => {
        const d = btn.dataset;
        document.getElementById('esLabel').value         = d.slab_label    || '';
        document.getElementById('esQtyFrom').value       = d.qty_from      || '0';
        document.getElementById('esLaborHours').value    = d.labor_hours   || '0';
        document.getElementById('esMaterialCost').value  = d.material_cost || '0';
        document.getElementById('esIsAdditional').checked = d.is_additional === '1';

        document.getElementById('editSlabForm').action =
            '{{ url("masters/mr-tariff/" . $mrTariff->id . "/items") }}/' + d.item_id + '/slabs/' + d.slab_id;

        new bootstrap.Modal(document.getElementById('editSlabModal')).show();
    });
});

// ── Item filter ──────────────────────────────────────────────────────────────
document.getElementById('itemSearch')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.item-row').forEach(row => {
        const desc = row.dataset.desc || '';
        const code = row.dataset.code || '';
        row.style.display = (!q || desc.includes(q) || code.includes(q)) ? '' : 'none';
    });
});
</script>
@endpush
