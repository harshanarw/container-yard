<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\InvoicePosting;
use App\Services\Finance\InvoicePostingService;
use Illuminate\Http\Request;

class InvoicePostingController extends Controller
{
    public function __construct(private InvoicePostingService $service) {}

    /**
     * List all invoice postings with optional filters.
     */
    public function index(Request $request)
    {
        $this->authorize('finance.ar.view');

        $query = InvoicePosting::with(['journal', 'postedBy'])
            ->latest();

        if ($request->filled('type')) {
            $query->where('invoice_type', $request->input('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $postings = $query->paginate(30)->withQueryString();

        return view('finance.ar.index', compact('postings'));
    }

    /**
     * Post an invoice to the GL.
     */
    public function store(Request $request)
    {
        $this->authorize('finance.ar.post');

        $validated = $request->validate([
            'invoice_type' => ['required', 'in:storage,storage-handling,reefer,repair'],
            'invoice_id'   => ['required', 'integer', 'min:1'],
        ]);

        $invoice = $this->resolveInvoice($validated['invoice_type'], (int) $validated['invoice_id']);

        if (!$invoice) {
            return back()->with('error', 'Invoice not found.');
        }

        try {
            $posting = $this->service->post($invoice, $validated['invoice_type'], auth()->id());
            $msg     = "Invoice posted to GL journal {$posting->journal->journal_no}.";

            if ($request->expectsJson()) {
                return response()->json([
                    'ok'         => true,
                    'journal_no' => $posting->journal->journal_no,
                    'message'    => $msg,
                ]);
            }

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Void a posted invoice posting.
     */
    public function void(Request $request, InvoicePosting $posting)
    {
        $this->authorize('finance.ar.void');
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $this->service->void($posting, auth()->id(), $request->input('reason', ''));
            return back()->with('success', 'Invoice posting voided.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function resolveInvoice(string $type, int $id): ?\Illuminate\Database\Eloquent\Model
    {
        return match ($type) {
            'storage'          => \App\Models\StorageInvoice::find($id),
            'storage-handling' => \App\Models\StorageHandlingInvoice::find($id),
            'reefer'           => \App\Models\ReeferElectricityInvoice::find($id),
            'repair'           => \App\Models\RepairInvoice::find($id),
            default            => null,
        };
    }
}
