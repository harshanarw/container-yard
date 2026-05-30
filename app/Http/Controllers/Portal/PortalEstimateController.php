<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Mail\EstimateApprovalReceivedMail;
use App\Models\CompanySetting;
use App\Models\EstimateApprovalAction;
use App\Models\EstimateLineItem;
use App\Models\PortalToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PortalEstimateController extends Controller
{
    private function resolveToken(string $token): PortalToken
    {
        $pt = PortalToken::where('token', $token)->first();

        if (!$pt || !$pt->isValid()) {
            abort(403, 'This link has expired or is no longer valid.');
        }

        $pt->markAccessed();
        return $pt;
    }

    public function show(string $token)
    {
        $portalToken = $this->resolveToken($token);
        $estimate    = $portalToken->tokenable;

        if (!$estimate) {
            abort(404, 'Estimate not found.');
        }

        $estimate->load(['lineItems.componentCode', 'lineItems.chargeCode', 'lineItems.taxCode', 'container', 'customer']);
        $company = CompanySetting::current();

        return view('portal.estimate.show', compact('estimate', 'portalToken', 'company', 'token'));
    }

    public function bulkApprove(Request $request, string $token)
    {
        $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $portalToken = $this->resolveToken($token);
        $estimate    = $portalToken->tokenable;

        if (!$estimate) {
            abort(404);
        }

        $estimate->load('lineItems');

        foreach ($estimate->lineItems as $line) {
            if ($line->approval_status === 'pending') {
                $line->update(['approval_status' => 'approved']);
                EstimateApprovalAction::create([
                    'estimate_id'          => $estimate->id,
                    'estimate_line_item_id'=> $line->id,
                    'action'               => 'line_approved',
                    'notes'                => $request->notes,
                    'performed_by_email'   => $portalToken->email,
                ]);
            }
        }

        $estimate->update(['status' => 'approved']);

        EstimateApprovalAction::create([
            'estimate_id'        => $estimate->id,
            'action'             => 'fully_approved',
            'notes'              => $request->notes,
            'performed_by_email' => $portalToken->email,
        ]);

        $this->notifyDepot($estimate, 'approved', $request->notes);

        return back()->with('success', 'Estimate approved successfully. The depot has been notified.');
    }

    public function bulkReject(Request $request, string $token)
    {
        $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        $portalToken = $this->resolveToken($token);
        $estimate    = $portalToken->tokenable;

        if (!$estimate) {
            abort(404);
        }

        $estimate->load('lineItems');

        foreach ($estimate->lineItems as $line) {
            if ($line->approval_status === 'pending') {
                $line->update(['approval_status' => 'rejected']);
                EstimateApprovalAction::create([
                    'estimate_id'          => $estimate->id,
                    'estimate_line_item_id'=> $line->id,
                    'action'               => 'line_rejected',
                    'notes'                => $request->notes,
                    'performed_by_email'   => $portalToken->email,
                ]);
            }
        }

        $estimate->update([
            'status'          => 'rejected',
            'rejected_reason' => $request->notes,
        ]);

        EstimateApprovalAction::create([
            'estimate_id'        => $estimate->id,
            'action'             => 'returned',
            'notes'              => $request->notes,
            'performed_by_email' => $portalToken->email,
        ]);

        $this->notifyDepot($estimate, 'rejected', $request->notes);

        return back()->with('info', 'Estimate rejected. The depot has been notified.');
    }

    public function lineAction(Request $request, string $token, EstimateLineItem $lineItem)
    {
        $request->validate([
            'action' => ['required', 'in:approved,rejected,amended'],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ]);

        $portalToken = $this->resolveToken($token);
        $estimate    = $portalToken->tokenable;

        if (!$estimate || $lineItem->estimate_id !== $estimate->id) {
            abort(403);
        }

        $lineItem->update(['approval_status' => $request->action]);

        EstimateApprovalAction::create([
            'estimate_id'          => $estimate->id,
            'estimate_line_item_id'=> $lineItem->id,
            'action'               => 'line_' . $request->action,
            'notes'                => $request->notes,
            'performed_by_email'   => $portalToken->email,
        ]);

        // Recalculate overall estimate status
        $estimate->load('lineItems');
        $statuses = $estimate->lineItems->pluck('approval_status')->unique();

        if ($statuses->contains('pending')) {
            $newStatus = 'under_review';
        } elseif ($statuses->every(fn ($s) => $s === 'approved')) {
            $newStatus = 'approved';
            EstimateApprovalAction::create([
                'estimate_id'        => $estimate->id,
                'action'             => 'fully_approved',
                'performed_by_email' => $portalToken->email,
            ]);
            $this->notifyDepot($estimate, 'approved', null);
        } elseif ($statuses->contains('rejected')) {
            $newStatus = 'under_review';
        } else {
            $newStatus = 'partially_approved';
            $this->notifyDepot($estimate, 'partially_approved', null);
        }

        $estimate->update(['status' => $newStatus]);

        return back()->with('success', 'Line item ' . $request->action . '.');
    }

    private function notifyDepot(mixed $estimate, string $action, ?string $notes): void
    {
        try {
            $estimate->load('createdBy');
            if ($estimate->createdBy && $estimate->createdBy->email) {
                Mail::to($estimate->createdBy->email)
                    ->send(new EstimateApprovalReceivedMail($estimate, $action, $notes));
            }
        } catch (\Throwable $e) {
            \Log::error('Failed to send depot notification email: ' . $e->getMessage());
        }
    }
}
