@php
    $indent = $depth * 20;
    $isGroup = !$account->is_posting;
    $color = \App\Models\Account::classificationBadge($account->classification);
@endphp
<tr class="{{ !$account->is_active ? 'text-muted opacity-50' : '' }}">
    <td class="font-monospace small fw-semibold" style="padding-left: {{ 12 + $indent }}px;">
        {{ $account->code }}
    </td>
    <td style="padding-left: {{ $indent > 0 ? 4 : 0 }}px;">
        @if($isGroup)
            <i class="bi bi-folder-fill me-1 text-secondary opacity-75" style="font-size:.75rem;"></i>
        @else
            <i class="bi bi-pencil-square me-1 text-{{ $color }} opacity-75" style="font-size:.75rem;"></i>
        @endif
        <span class="{{ $depth === 0 ? 'fw-semibold' : ($depth === 1 ? 'fw-medium' : '') }}" style="font-size:{{ max(0.78, 0.9 - $depth * 0.04) }}rem;">
            {{ $account->name }}
        </span>
        @if($account->is_control)
            <span class="badge bg-warning-subtle text-warning ms-1" style="font-size:.6rem;">CTRL</span>
        @endif
        @if($account->is_receivable)
            <span class="badge bg-primary-subtle text-primary ms-1" style="font-size:.6rem;">AR</span>
        @endif
        @if($account->is_payable)
            <span class="badge bg-danger-subtle text-danger ms-1" style="font-size:.6rem;">AP</span>
        @endif
        @if($account->is_cash_bank)
            <span class="badge bg-success-subtle text-success ms-1" style="font-size:.6rem;">BANK</span>
        @endif
    </td>
    <td class="text-center">
        <span class="badge bg-{{ $color }}-subtle text-{{ $color }}" style="font-size:.65rem;">
            {{ \App\Models\Account::classificationLabel($account->classification) }}
        </span>
    </td>
    <td class="text-center">
        @if($account->is_posting)
            <span class="badge bg-dark-subtle text-dark" style="font-size:.65rem;">Posting</span>
        @else
            <span class="text-muted" style="font-size:.7rem;">Group</span>
        @endif
    </td>
    <td class="text-center text-muted" style="font-size:.75rem;">
        {{ ucfirst($account->normal_balance) }}
    </td>
    <td class="text-center">
        @can('finance.coa.edit')
        @if(!$account->is_system)
        <form method="POST" action="{{ route('finance.setup.accounts.toggle', $account) }}" class="d-inline">
            @csrf @method('PATCH')
            <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent">
                <i class="bi bi-{{ $account->is_active ? 'toggle-on text-success' : 'toggle-off text-muted' }}" style="font-size:1.1rem;"></i>
            </button>
        </form>
        @else
            <i class="bi bi-toggle-on text-success" style="font-size:1.1rem;" title="System account"></i>
        @endif
        @endcan
    </td>
    <td class="text-end">
        @can('finance.coa.edit')
        <button class="btn btn-sm btn-link p-0 text-secondary"
                data-edit-account='@json($account->only(["id","code","name","classification","account_subtype","normal_balance","is_posting","is_control","is_receivable","is_payable","is_cash_bank","opening_balance","opening_balance_type","is_active"]))'>
            <i class="bi bi-pencil"></i>
        </button>
        @endcan
        @can('finance.coa.delete')
        @if(!$account->is_system && $account->allChildren->isEmpty())
        <form method="POST" action="{{ route('finance.setup.accounts.destroy', $account) }}" class="d-inline">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-sm btn-link p-0 text-danger ms-1"
                    onclick="return confirm('Delete account {{ $account->code }}?')">
                <i class="bi bi-trash3"></i>
            </button>
        </form>
        @endif
        @endcan
    </td>
</tr>
@foreach($account->allChildren as $child)
    @include('finance.setup.accounts._row', ['account' => $child, 'depth' => $depth + 1])
@endforeach
