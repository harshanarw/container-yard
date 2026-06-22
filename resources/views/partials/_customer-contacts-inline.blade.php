{{--
    Reusable inline customer email-contacts shortcut.

    Shows a customer's saved recipients for one category and — for users with
    customers.edit — an inline add form, so the TO/CC list can be reviewed and
    extended at a send trigger point (estimate send form, invoice modal) without
    navigating to the customer profile.

    Params:
      $customer       \App\Models\Customer|null  the customer whose contacts to show
      $category       string                     one of config('email_categories.customer') keys
      $title          string|null                optional heading override
      $showPortalHint bool                       when true, adds a note that the first TO contact
                                                 pre-fills the portal recipient field (estimate send form)
--}}
@php
    $ccCustomer      = $customer ?? null;
    $ccCategory      = $category ?? 'general';
    $ccTitle         = $title ?? (config("email_categories.customer.$ccCategory.label") ?? 'Email Contacts');
    $showPortalHint  = $showPortalHint ?? false;
    $ccIcon     = config("email_categories.customer.$ccCategory.icon", 'bi-envelope-at');
    $ccColor    = config("email_categories.customer.$ccCategory.color", 'secondary');

    $ccContacts = $ccCustomer
        ? \App\Models\CustomerEmailContact::forCustomerCategory($ccCustomer->id, $ccCategory)
        : collect();
    $ccTo = $ccContacts->where('address_type', 'to');
    $ccCc = $ccContacts->where('address_type', 'cc');

    // Unique suffix so several instances on one page don't collide.
    $ccUid = $ccCustomer ? ($ccCustomer->id . '_' . $ccCategory) : ('none_' . $ccCategory);
@endphp

<div class="border rounded p-2 bg-light small">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="fw-semibold">
            <i class="bi {{ $ccIcon }} text-{{ $ccColor }} me-1"></i>{{ $ccTitle }}
        </span>
        @if($ccCustomer)
        <a href="{{ route('customers.show', $ccCustomer) }}" target="_blank"
           class="text-decoration-none" title="Open customer profile">
            <i class="bi bi-box-arrow-up-right"></i>
        </a>
        @endif
    </div>

    @if(!$ccCustomer)
        <div class="text-muted fst-italic">No customer linked — saved recipients unavailable.</div>
    @else
        <div class="text-muted mb-2" style="font-size:.78rem;">
            @if($showPortalHint)
                The first saved <strong>TO</strong> contact pre-fills the portal recipient field above. All saved contacts (TO and CC) are automatically included when the email is sent.
            @else
                Saved contacts are automatically included when this email is sent.
            @endif
        </div>

        {{-- Current saved recipients --}}
        @if($ccContacts->isEmpty())
            <div class="text-muted fst-italic mb-2">No saved recipients for this category yet.</div>
        @else
            @if($ccTo->isNotEmpty())
            <div class="mb-1">
                <span class="badge bg-primary me-1">TO</span>
                @foreach($ccTo as $c)
                    <span class="badge bg-white text-dark border me-1">{{ $c->email }}</span>
                @endforeach
            </div>
            @endif
            @if($ccCc->isNotEmpty())
            <div class="mb-1">
                <span class="badge bg-secondary me-1">CC</span>
                @foreach($ccCc as $c)
                    <span class="badge bg-white text-dark border me-1">{{ $c->email }}</span>
                @endforeach
            </div>
            @endif
        @endif

        {{-- Inline add form (standalone — not nested in any parent form) --}}
        @can('customers.edit')
        <div class="mt-2">
            <button type="button" class="btn btn-xs btn-outline-primary"
                    data-bs-toggle="collapse" data-bs-target="#addContact_{{ $ccUid }}">
                <i class="bi bi-plus-lg me-1"></i>Add recipient
            </button>
            <div class="collapse mt-2" id="addContact_{{ $ccUid }}">
                <form method="POST" action="{{ route('customers.email-contacts.store', $ccCustomer) }}"
                      class="d-flex gap-2 align-items-end flex-wrap">
                    @csrf
                    <input type="hidden" name="category" value="{{ $ccCategory }}">
                    <div style="flex:2;min-width:150px;">
                        <label class="form-label form-label-sm mb-1">Email</label>
                        <input type="email" name="email" class="form-control form-control-sm"
                               placeholder="contact@company.com" required>
                    </div>
                    <div style="flex:1;min-width:90px;">
                        <label class="form-label form-label-sm mb-1">Label</label>
                        <input type="text" name="label" class="form-control form-control-sm" placeholder="Name">
                    </div>
                    <div style="min-width:80px;">
                        <label class="form-label form-label-sm mb-1">Type</label>
                        <select name="address_type" class="form-select form-select-sm">
                            <option value="to">TO</option>
                            <option value="cc">CC</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i></button>
                    </div>
                </form>
            </div>
        </div>
        @endcan
    @endif
</div>

@once
@push('styles')
<style>
    .btn-xs { padding: .18rem .5rem; font-size: .72rem; line-height: 1.2; }
</style>
@endpush
@endonce
