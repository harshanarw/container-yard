<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:finance.suppliers.view')->only(['index', 'show', 'search']);
        $this->middleware('can:finance.suppliers.create')->only(['create', 'store']);
        $this->middleware('can:finance.suppliers.edit')->only(['edit', 'update']);
        $this->middleware('can:finance.suppliers.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $suppliers = Supplier::query()
            ->withCount('invoices')
            ->when($request->search, fn ($q, $search) =>
                $q->where(fn ($qb) => $qb->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
            )
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $totalSuppliers  = Supplier::count();
        $activeSuppliers = Supplier::where('status', 'active')->count();

        return view('finance.suppliers.index', compact('suppliers', 'totalSuppliers', 'activeSuppliers'));
    }

    public function create()
    {
        $countries = Country::forSelect();
        $nextCode  = $this->nextCode();

        return view('finance.suppliers.create', compact('countries', 'nextCode'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['tax_exempt'] = $request->boolean('tax_exempt');
        $data['credit_limit'] = $data['credit_limit'] ?? 0;
        $data['country_id'] = $request->input('country_id') ?: null;
        $data['created_by'] = auth()->id();

        Supplier::create($data);

        return redirect()->route('finance.suppliers.index')
            ->with('success', 'Supplier created successfully.');
    }

    public function show(Supplier $supplier)
    {
        $supplier->loadCount('invoices');
        $recentInvoices = $supplier->invoices()->latest('invoice_date')->take(10)->get();

        $outstanding = $supplier->invoices()
            ->whereIn('status', ['approved', 'partially_paid'])
            ->get()
            ->sum(fn ($inv) => max(0, (float) $inv->total_amount
                - (float) $inv->allocations()
                    ->whereHas('voucher', fn ($q) => $q->whereIn('status', ['draft', 'confirmed']))
                    ->sum('allocated_amount')));

        return view('finance.suppliers.show', compact('supplier', 'recentInvoices', 'outstanding'));
    }

    public function edit(Supplier $supplier)
    {
        $countries = Country::forSelect();

        return view('finance.suppliers.edit', compact('supplier', 'countries'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $this->validateData($request, $supplier->id);
        $data['tax_exempt'] = $request->boolean('tax_exempt');
        $data['credit_limit'] = $data['credit_limit'] ?? 0;
        $data['country_id'] = $request->input('country_id') ?: null;

        $supplier->update($data);

        return redirect()->route('finance.suppliers.index')
            ->with('success', 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->invoices()->exists()) {
            return back()->with('error', 'Cannot delete a supplier that has invoices.');
        }

        $supplier->delete();

        return redirect()->route('finance.suppliers.index')
            ->with('success', 'Supplier deleted successfully.');
    }

    /** JSON autocomplete for allocation/voucher forms. */
    public function search(Request $request)
    {
        $q = trim($request->input('q', ''));

        $suppliers = Supplier::where('status', 'active')
            ->where(fn ($qb) => $qb->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%"))
            ->orderBy('name')->limit(15)
            ->get(['id', 'code', 'name']);

        return response()->json($suppliers->map(fn ($s) => [
            'id'    => $s->id,
            'label' => "{$s->code} — {$s->name}",
            'name'  => $s->name,
            'code'  => $s->code,
        ]));
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code'            => ['required', 'string', 'max:20', Rule::unique('suppliers', 'code')->ignore($ignoreId)],
            'name'            => ['required', 'string', 'max:255'],
            'registration_no' => ['nullable', 'string', 'max:50'],
            'tin_number'      => ['nullable', 'string', 'max:20'],
            'address'         => ['nullable', 'string'],
            'city'            => ['nullable', 'string', 'max:100'],
            'country'         => ['nullable', 'string', 'max:100'],
            'country_id'      => ['nullable', 'integer', 'exists:countries,id'],
            'contact_person'  => ['nullable', 'string', 'max:150'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'email'           => ['nullable', 'email', 'max:255'],
            'website'         => ['nullable', 'string', 'max:255'],
            'currency'        => ['required', 'in:LKR,USD,SGD'],
            'credit_limit'    => ['nullable', 'numeric', 'min:0'],
            'payment_terms'   => ['required', 'in:cod,net15,net30,net45,net60'],
            'status'          => ['required', 'in:active,pending,inactive'],
            'notes'           => ['nullable', 'string'],
        ]);
    }

    private function nextCode(): string
    {
        $last = Supplier::where('code', 'like', 'SUP-%')
            ->orderByRaw('CAST(SUBSTRING(code, 5) AS UNSIGNED) DESC')
            ->value('code');

        $n = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'SUP-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
