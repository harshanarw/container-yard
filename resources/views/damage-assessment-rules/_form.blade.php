<div class="row g-3">

    <div class="col-12">
        <label class="form-label fw-semibold">Rule Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $rule?->name) }}"
               placeholder="e.g. Left Door Hinge — Broken / Replace"
               maxlength="150" required>
        <div class="form-text text-muted">A clear, descriptive label shown in the Pull From Rules picker.</div>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Location <span class="text-muted fw-normal">(optional)</span></label>
        <select name="location_code_id" class="form-select select2 @error('location_code_id') is-invalid @enderror">
            <option value="">— Any / Not Specified —</option>
            @foreach($locations as $c)
            <option value="{{ $c->id }}" {{ old('location_code_id', $rule?->location_code_id) == $c->id ? 'selected' : '' }}>
                {{ $c->code }} — {{ $c->name }}
            </option>
            @endforeach
        </select>
        @error('location_code_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Component <span class="text-danger">*</span></label>
        <select name="component_code_id" class="form-select select2 @error('component_code_id') is-invalid @enderror" required>
            <option value="">— Select Component —</option>
            @foreach($components as $c)
            <option value="{{ $c->id }}" {{ old('component_code_id', $rule?->component_code_id) == $c->id ? 'selected' : '' }}>
                {{ $c->code }} — {{ $c->name }}
            </option>
            @endforeach
        </select>
        @error('component_code_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Damage Type <span class="text-danger">*</span></label>
        <select name="damage_code_id" class="form-select select2 @error('damage_code_id') is-invalid @enderror" required>
            <option value="">— Select Damage —</option>
            @foreach($damages as $c)
            <option value="{{ $c->id }}" {{ old('damage_code_id', $rule?->damage_code_id) == $c->id ? 'selected' : '' }}>
                {{ $c->code }} — {{ $c->name }}
            </option>
            @endforeach
        </select>
        @error('damage_code_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Repair Action <span class="text-danger">*</span></label>
        <select name="repair_code_id" class="form-select select2 @error('repair_code_id') is-invalid @enderror" required>
            <option value="">— Select Repair —</option>
            @foreach($repairs as $c)
            <option value="{{ $c->id }}" {{ old('repair_code_id', $rule?->repair_code_id) == $c->id ? 'selected' : '' }}>
                {{ $c->code }} — {{ $c->name }}
            </option>
            @endforeach
        </select>
        @error('repair_code_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Default Severity <span class="text-muted fw-normal">(optional)</span></label>
        <select name="default_severity" class="form-select @error('default_severity') is-invalid @enderror">
            <option value="">— Not set —</option>
            <option value="minor"    {{ old('default_severity', $rule?->default_severity) === 'minor'    ? 'selected' : '' }}>Minor</option>
            <option value="moderate" {{ old('default_severity', $rule?->default_severity) === 'moderate' ? 'selected' : '' }}>Moderate</option>
            <option value="severe"   {{ old('default_severity', $rule?->default_severity) === 'severe'   ? 'selected' : '' }}>Severe</option>
        </select>
        @error('default_severity')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Sort Order</label>
        <input type="number" name="sort_order" class="form-control @error('sort_order') is-invalid @enderror"
               value="{{ old('sort_order', $rule?->sort_order ?? 0) }}" min="0" max="9999">
        <div class="form-text text-muted">Lower numbers appear first in the picker.</div>
        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4 d-flex align-items-end pb-1">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="isActiveChk" value="1"
                   {{ old('is_active', $rule ? ($rule->is_active ? '1' : '') : '1') ? 'checked' : '' }}>
            <label class="form-check-label fw-semibold" for="isActiveChk">Active</label>
            <div class="text-muted" style="font-size:.75rem;">Inactive rules are hidden from the picker.</div>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Default Description <span class="text-muted fw-normal">(optional)</span></label>
        <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                  rows="2" maxlength="500"
                  placeholder="Brief description pre-filled into the damage line when this rule is applied…">{{ old('description', $rule?->description) }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

</div>
