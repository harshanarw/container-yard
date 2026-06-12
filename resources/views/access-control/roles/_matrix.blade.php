{{--
  Shared permission matrix partial.
  Expects: $sections (from AccessController::buildRoleMatrix())
  Name convention: permissions[] checkboxes
--}}
<div class="mb-2 d-flex justify-content-between align-items-center">
    <span class="text-muted small">Check the permissions to assign to this role.</span>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-xs btn-outline-primary" id="selectAllPerms">Select All</button>
        <button type="button" class="btn btn-xs btn-outline-secondary" id="clearAllPerms">Clear All</button>
    </div>
</div>

@foreach($sections as $sectionName => $modules)
@php $sectionSlug = Str::slug($sectionName); @endphp
<div class="card mb-3 shadow-none border">
    <div class="card-header d-flex justify-content-between align-items-center py-2 bg-light">
        <div class="fw-semibold small text-uppercase text-secondary tracking-wider">
            <i class="bi bi-grid-3x2-gap me-1"></i>{{ $sectionName }}
        </div>
        <label class="form-check mb-0 d-flex align-items-center gap-1 user-select-none" style="cursor:pointer;">
            <input type="checkbox" class="form-check-input section-master" data-section="{{ $sectionSlug }}" style="cursor:pointer;">
            <span class="form-check-label small text-muted">All</span>
        </label>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <tbody>
            @foreach($modules as $module)
            <tr data-section="{{ $sectionSlug }}">
                <td class="ps-3 text-nowrap" style="width:200px;">
                    <span class="small fw-medium">{{ $module['label'] }}</span>
                </td>
                <td>
                    <div class="d-flex flex-wrap gap-3">
                    @foreach($module['perms'] as $perm)
                    @php $id = 'perm_' . str_replace(['.', '-'], '_', $perm['name']); @endphp
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input perm-check"
                               type="checkbox"
                               name="permissions[]"
                               value="{{ $perm['name'] }}"
                               id="{{ $id }}"
                               data-section="{{ $sectionSlug }}"
                               {{ $perm['checked'] ? 'checked' : '' }}>
                        <label class="form-check-label small" for="{{ $id }}">{{ $perm['display'] }}</label>
                    </div>
                    @endforeach
                    </div>
                </td>
                <td class="pe-3 text-end" style="width:50px;">
                    <button type="button" class="btn btn-xs btn-link text-muted p-0 row-all-btn"
                            title="Toggle row">All</button>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endforeach

@push('scripts')
<script>
(function () {
    // ── Row "All" button ───────────────────────────────────────────────────────
    document.querySelectorAll('.row-all-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const row     = this.closest('tr');
            const checks  = row.querySelectorAll('.perm-check');
            const allOn   = [...checks].every(c => c.checked);
            checks.forEach(c => { c.checked = !allOn; });
            syncSectionMaster(row.dataset.section);
        });
    });

    // ── Section master checkbox ────────────────────────────────────────────────
    document.querySelectorAll('.section-master').forEach(master => {
        master.addEventListener('change', function () {
            document.querySelectorAll(`.perm-check[data-section="${this.dataset.section}"]`)
                    .forEach(c => { c.checked = this.checked; });
        });
    });

    // ── Individual checkbox updates section master ─────────────────────────────
    document.querySelectorAll('.perm-check').forEach(c => {
        c.addEventListener('change', () => syncSectionMaster(c.dataset.section));
    });

    function syncSectionMaster(sectionSlug) {
        const all     = document.querySelectorAll(`.perm-check[data-section="${sectionSlug}"]`);
        const checked = [...all].filter(c => c.checked).length;
        const master  = document.querySelector(`.section-master[data-section="${sectionSlug}"]`);
        if (!master) return;
        master.checked       = checked === all.length;
        master.indeterminate = checked > 0 && checked < all.length;
    }

    // ── Select / Clear all ─────────────────────────────────────────────────────
    document.getElementById('selectAllPerms')?.addEventListener('click', () => {
        document.querySelectorAll('.perm-check').forEach(c => { c.checked = true; });
        document.querySelectorAll('.section-master').forEach(m => { m.checked = true; m.indeterminate = false; });
    });
    document.getElementById('clearAllPerms')?.addEventListener('click', () => {
        document.querySelectorAll('.perm-check').forEach(c => { c.checked = false; });
        document.querySelectorAll('.section-master').forEach(m => { m.checked = false; m.indeterminate = false; });
    });

    // ── Init section master states on load ────────────────────────────────────
    document.querySelectorAll('.section-master').forEach(m => syncSectionMaster(m.dataset.section));
})();
</script>
@endpush
