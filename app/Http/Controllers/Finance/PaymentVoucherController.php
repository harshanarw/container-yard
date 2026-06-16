<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\PaymentVoucher;
use App\Services\Finance\ReceiptPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentVoucherController extends Controller
{
    public function __construct(private ReceiptPostingService $postingService) {}

    public function index(Request $request)
    {
        $this->authorize('finance.vouchers.view');

        $query = PaymentVoucher::with('journal')
            ->orderByDesc('voucher_date')
            ->orderByDesc('id');

        if ($request->filled('payee')) {
            $query->where('payee_name', 'like', '%' . $request->payee . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('voucher_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('voucher_date', '<=', $request->date_to);
        }

        $vouchers = $query->paginate(25)->withQueryString();

        return view('finance.vouchers.index', compact('vouchers'));
    }

    public function create()
    {
        $this->authorize('finance.vouchers.create');

        $bankAccounts    = BankAccount::where('is_active', true)->orderBy('bank_name')->get();
        $expenseAccounts = Account::where('is_posting', true)
            ->where('is_active', true)
            ->orderBy('classification')
            ->orderBy('code')
            ->get();

        return view('finance.vouchers.create', compact('bankAccounts', 'expenseAccounts'));
    }

    public function store(Request $request)
    {
        $this->authorize('finance.vouchers.create');

        $validated = $request->validate([
            'voucher_date'      => ['required', 'date'],
            'payee_name'        => ['required', 'string', 'max:150'],
            'bank_account_id'   => ['nullable', 'exists:bank_accounts,id'],
            'amount'            => ['required', 'numeric', 'min:0.0001'],
            'currency'          => ['required', 'string', 'max:10'],
            'exchange_rate'     => ['required', 'numeric', 'min:0.000001'],
            'payment_method'    => ['required', 'in:cash,cheque,bank_transfer,online'],
            'cheque_no'         => ['nullable', 'string', 'max:50'],
            'reference_no'      => ['nullable', 'string', 'max:100'],
            'narration'         => ['required', 'string', 'max:255'],
            'expense_account_id' => ['nullable', 'exists:accounts,id'],
        ]);

        $validated['voucher_no']  = $this->nextVoucherNo();
        $validated['created_by']  = auth()->id();
        $validated['status']      = 'draft';

        $voucher = PaymentVoucher::create($validated);

        return redirect()->route('finance.vouchers.show', $voucher)
            ->with('success', "Voucher {$voucher->voucher_no} created successfully.");
    }

    public function show(PaymentVoucher $voucher)
    {
        $this->authorize('finance.vouchers.view');

        $voucher->load(['bankAccount.glAccount', 'expenseAccount', 'journal', 'createdBy', 'voidedBy']);

        return view('finance.vouchers.show', compact('voucher'));
    }

    public function confirm(PaymentVoucher $voucher)
    {
        $this->authorize('finance.vouchers.confirm');

        try {
            $this->postingService->confirmVoucher($voucher, auth()->id());
            return back()->with('success', "Voucher {$voucher->voucher_no} confirmed and posted to GL.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function void(Request $request, PaymentVoucher $voucher)
    {
        $this->authorize('finance.vouchers.void');

        $reason = $request->input('reason', '');

        try {
            $this->postingService->voidVoucher($voucher, auth()->id(), $reason);
            return back()->with('success', "Voucher {$voucher->voucher_no} has been voided.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function nextVoucherNo(): string
    {
        return DB::transaction(function () {
            $prefix = \App\Models\CompanySetting::current()->prefix_voucher ?? 'PV';
            $last   = PaymentVoucher::where('voucher_no', 'like', "{$prefix}-%")
                ->orderByDesc('voucher_no')
                ->lockForUpdate()
                ->value('voucher_no');
            $seq = 1;
            if ($last) {
                $parts = explode('-', $last);
                $seq   = ((int) end($parts)) + 1;
            }
            return $prefix . '-' . str_pad($seq, 6, '0', STR_PAD_LEFT);
        });
    }
}
