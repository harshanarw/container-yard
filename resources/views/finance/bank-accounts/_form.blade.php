<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold small">Account Name <span class="text-danger">*</span></label>
        <input type="text" name="account_name" class="form-control form-control-sm @error('account_name') is-invalid @enderror"
               value="{{ old('account_name', $bankAccount->account_name ?? '') }}" required maxlength="100">
        @error('account_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold small">Bank Name <span class="text-danger">*</span></label>
        <input type="text" name="bank_name" class="form-control form-control-sm @error('bank_name') is-invalid @enderror"
               value="{{ old('bank_name', $bankAccount->bank_name ?? '') }}" required maxlength="100">
        @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold small">Account Number <span class="text-danger">*</span></label>
        <input type="text" name="account_number" class="form-control form-control-sm @error('account_number') is-invalid @enderror"
               value="{{ old('account_number', $bankAccount->account_number ?? '') }}" required maxlength="50">
        @error('account_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold small">Currency <span class="text-danger">*</span></label>
        <input type="text" name="currency" class="form-control form-control-sm @error('currency') is-invalid @enderror"
               value="{{ old('currency', $bankAccount->currency ?? 'USD') }}" required maxlength="10" placeholder="USD">
        @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold small">Active</label>
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                   {{ old('is_active', ($bankAccount->is_active ?? true) ? '1' : '0') == '1' ? 'checked' : '' }}>
            <label class="form-check-label small" for="is_active">Account is active</label>
        </div>
    </div>
    <div class="col-md-12">
        <label class="form-label fw-semibold small">GL Account (Cash/Bank)</label>
        <select name="gl_account_id" class="form-select form-select-sm select2 @error('gl_account_id') is-invalid @enderror">
            <option value="">— None (manual posting) —</option>
            @foreach($glAccounts as $acc)
            <option value="{{ $acc->id }}" {{ old('gl_account_id', $bankAccount->gl_account_id ?? '') == $acc->id ? 'selected' : '' }}>
                {{ $acc->code }} — {{ $acc->name }}
            </option>
            @endforeach
        </select>
        <div class="form-text text-muted small">Must be a Cash/Bank type account from your Chart of Accounts.</div>
        @error('gl_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold small">Notes</label>
        <textarea name="notes" class="form-control form-control-sm" rows="2" maxlength="500">{{ old('notes', $bankAccount->notes ?? '') }}</textarea>
    </div>
</div>
