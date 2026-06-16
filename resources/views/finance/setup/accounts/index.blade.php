@extends('layouts.app')

@section('title', 'Chart of Accounts')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item active">Chart of Accounts</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-diagram-3 me-2 text-primary"></i>Chart of Accounts</h4>
        <p class="text-muted mb-0 small">Hierarchical ledger account structure for double-entry bookkeeping</p>
    </div>
    @can('finance.coa.create')
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccountModal">
        <i class="bi bi-plus-lg me-1"></i>New Account
    </button>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Legend --}}
<div class="d-flex gap-2 flex-wrap mb-3">
    @foreach(['asset'=>'primary','liability'=>'danger','equity'=>'success','income'=>'info','expense'=>'warning'] as $cls => $color)
    <span class="badge bg-{{ $color }}-subtle text-{{ $color }} px-3 py-2">{{ ucfirst($cls) }}</span>
    @endforeach
    <span class="badge bg-dark-subtle text-dark px-3 py-2 ms-2"><i class="bi bi-pencil-square me-1"></i>Posting Account</span>
    <span class="badge bg-secondary-subtle text-secondary px-3 py-2"><i class="bi bi-folder me-1"></i>Group / Header</span>
</div>

<div class="card content-card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle" id="coaTable">
            <thead class="table-light">
                <tr>
                    <th style="width:140px;">Code</th>
                    <th>Account Name</th>
                    <th class="text-center" style="width:110px;">Classification</th>
                    <th class="text-center" style="width:90px;">Type</th>
                    <th class="text-center" style="width:90px;">Balance</th>
                    <th class="text-center" style="width:70px;">Active</th>
                    <th class="text-end" style="width:80px;">Edit</th>
                </tr>
            </thead>
            <tbody>
                @forelse($roots as $root)
                    @include('finance.setup.accounts._row', ['account' => $root, 'depth' => 0])
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-diagram-3 d-block fs-2 mb-2"></i>
                        No accounts yet. Click <strong>New Account</strong> to begin.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Add Account Modal --}}
@can('finance.coa.create')
<div class="modal fade" id="addAccountModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('finance.setup.accounts.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2 text-primary"></i>New Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Account Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control form-control-sm" required maxlength="20" placeholder="e.g. 1101">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Parent Account</label>
                            <select name="parent_id" class="form-select form-select-sm">
                                <option value="">(No parent — top level)</option>
                                @foreach($allAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} — {{ $acc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold small">Account Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-sm" required maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Classification <span class="text-danger">*</span></label>
                            <select name="classification" class="form-select form-select-sm" required>
                                <option value="">Select…</option>
                                <option value="asset">Asset</option>
                                <option value="liability">Liability</option>
                                <option value="equity">Equity</option>
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Normal Balance <span class="text-danger">*</span></label>
                            <select name="normal_balance" class="form-select form-select-sm" required>
                                <option value="debit">Debit</option>
                                <option value="credit">Credit</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Sub-Type</label>
                            <input type="text" name="account_subtype" class="form-control form-control-sm" maxlength="50" placeholder="Optional">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Opening Balance</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="opening_balance" class="form-control" step="0.01" min="0" value="0">
                                <select name="opening_balance_type" class="form-select" style="max-width:90px;">
                                    <option value="debit">Dr</option>
                                    <option value="credit">Cr</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Account Flags</label>
                            <div class="d-flex flex-wrap gap-3 pt-1">
                                <div class="form-check form-check-inline mb-0">
                                    <input type="checkbox" name="is_posting" value="1" id="chk_posting" class="form-check-input">
                                    <label class="form-check-label small" for="chk_posting">Posting Account</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input type="checkbox" name="is_cash_bank" value="1" id="chk_cashbank" class="form-check-input">
                                    <label class="form-check-label small" for="chk_cashbank">Cash/Bank</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input type="checkbox" name="is_control" value="1" id="chk_control" class="form-check-input">
                                    <label class="form-check-label small" for="chk_control">Control Account</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input type="checkbox" name="is_receivable" value="1" id="chk_recv" class="form-check-input">
                                    <label class="form-check-label small" for="chk_recv">Receivable</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input type="checkbox" name="is_payable" value="1" id="chk_pay" class="form-check-input">
                                    <label class="form-check-label small" for="chk_pay">Payable</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

{{-- Edit Account Modal (populated via JS) --}}
@can('finance.coa.edit')
<div class="modal fade" id="editAccountModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editAccountForm">
                @csrf @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2 text-secondary"></i>Edit Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Code</label>
                            <input type="text" id="edit_code" class="form-control form-control-sm" disabled>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label fw-semibold small">Account Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="edit_name" class="form-control form-control-sm" required maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Normal Balance</label>
                            <select name="normal_balance" id="edit_normal_balance" class="form-select form-select-sm">
                                <option value="debit">Debit</option>
                                <option value="credit">Credit</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Sub-Type</label>
                            <input type="text" name="account_subtype" id="edit_subtype" class="form-control form-control-sm" maxlength="50">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Active</label>
                            <select name="is_active" id="edit_is_active" class="form-select form-select-sm">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Opening Balance</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="opening_balance" id="edit_opening_balance" class="form-control" step="0.01" min="0">
                                <select name="opening_balance_type" id="edit_opening_balance_type" class="form-select" style="max-width:90px;">
                                    <option value="debit">Dr</option>
                                    <option value="credit">Cr</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Flags</label>
                            <div class="d-flex flex-wrap gap-3 pt-1">
                                <div class="form-check form-check-inline mb-0">
                                    <input type="checkbox" name="is_posting" value="1" id="edit_is_posting" class="form-check-input">
                                    <label class="form-check-label small" for="edit_is_posting">Posting</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input type="checkbox" name="is_cash_bank" value="1" id="edit_is_cash_bank" class="form-check-input">
                                    <label class="form-check-label small" for="edit_is_cash_bank">Cash/Bank</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input type="checkbox" name="is_control" value="1" id="edit_is_control" class="form-check-input">
                                    <label class="form-check-label small" for="edit_is_control">Control</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input type="checkbox" name="is_receivable" value="1" id="edit_is_receivable" class="form-check-input">
                                    <label class="form-check-label small" for="edit_is_receivable">Receivable</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input type="checkbox" name="is_payable" value="1" id="edit_is_payable" class="form-check-input">
                                    <label class="form-check-label small" for="edit_is_payable">Payable</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-edit-account]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var d = JSON.parse(this.dataset.editAccount);
            document.getElementById('edit_code').value = d.code;
            document.getElementById('edit_name').value = d.name;
            document.getElementById('edit_normal_balance').value = d.normal_balance;
            document.getElementById('edit_subtype').value = d.account_subtype || '';
            document.getElementById('edit_is_active').value = d.is_active ? '1' : '0';
            document.getElementById('edit_opening_balance').value = d.opening_balance;
            document.getElementById('edit_opening_balance_type').value = d.opening_balance_type;
            document.getElementById('edit_is_posting').checked = !!d.is_posting;
            document.getElementById('edit_is_cash_bank').checked = !!d.is_cash_bank;
            document.getElementById('edit_is_control').checked = !!d.is_control;
            document.getElementById('edit_is_receivable').checked = !!d.is_receivable;
            document.getElementById('edit_is_payable').checked = !!d.is_payable;
            var base = '{{ rtrim(route('finance.setup.accounts.update', ['account' => '__ID__']), '') }}';
            document.getElementById('editAccountForm').action = base.replace('__ID__', d.id);
            var modal = new bootstrap.Modal(document.getElementById('editAccountModal'));
            modal.show();
        });
    });
});
</script>
@endpush

@endsection
