<?php

namespace App\Http\Controllers;

use App\Services\Finance\InvoicePostingService;

class InvoicePostingController extends Controller
{
    /**
     * Retry the GL posting for an invoice that was issued but not posted
     * (e.g. because no accounting period was open at issue time). Keyed by the
     * invoice type + id so a single endpoint serves every invoice module.
     */
    public function retry(string $type, int $id, InvoicePostingService $service)
    {
        if (! array_key_exists($type, InvoicePostingService::INVOICE_MODELS)) {
            return back()->with('error', 'Unknown invoice type for posting retry.');
        }

        try {
            $posting = $service->retry($type, $id, auth()->id() ?? 1);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return back()->with('error', 'Invoice not found for posting retry.');
        }

        if ($posting->isPosted()) {
            return back()->with('success', 'Posted to the general ledger successfully.');
        }

        return back()->with('error',
            'Posting still failed — ' . ($posting->error_message ?? 'unknown error') . '.');
    }
}
