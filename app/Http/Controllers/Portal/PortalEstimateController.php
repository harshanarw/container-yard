<?php

namespace App\Http\Controllers\Portal;

use App\Facades\Documents;
use App\Http\Controllers\Controller;
use App\Mail\EstimateApprovalReceivedMail;
use App\Models\CompanySetting;
use App\Models\Document;
use App\Models\EstimateApprovalAction;
use App\Models\EstimateLineItem;
use App\Models\Inquiry;
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

    public function pdf(string $token)
    {
        $portalToken = $this->resolveToken($token);
        $estimate    = $portalToken->tokenable;

        if (!$estimate) {
            abort(404, 'Estimate not found.');
        }

        $estimate->load(['container', 'customer', 'inquiry', 'lineItems.componentCode',
                         'lineItems.chargeCode', 'lineItems.taxCode', 'createdBy']);

        return view('estimates.pdf', compact('estimate'));
    }

    public function photos(string $token)
    {
        $portalToken = $this->resolveToken($token);
        $estimate    = $portalToken->tokenable;

        if (!$estimate) {
            abort(404, 'Estimate not found.');
        }

        $company = CompanySetting::current();
        $inquiry = $estimate->inquiry;

        // New document-manager photos (polymorphic Document records)
        $documents    = $inquiry
            ? $inquiry->documents()->where('mime_type', 'like', 'image/%')->latest()->get()
            : collect();

        // Legacy InquiryPhoto records
        $legacyPhotos = $inquiry ? $inquiry->photos : collect();

        $totalCount = $documents->count() + $legacyPhotos->count();

        return view('portal.estimate.photos',
            compact('estimate', 'portalToken', 'company', 'token', 'documents', 'legacyPhotos', 'totalCount'));
    }

    public function viewPhoto(string $token, Document $document)
    {
        $portalToken = $this->resolveToken($token);
        $estimate    = $portalToken->tokenable;
        $inquiry     = $estimate?->inquiry;

        abort_unless($inquiry, 404);
        abort_unless(
            $document->documentable_type === Inquiry::class
            && $document->documentable_id === $inquiry->id
            && $document->isImage(),
            403
        );

        return Documents::stream($document, true);
    }

    public function downloadPhoto(string $token, Document $document)
    {
        $portalToken = $this->resolveToken($token);
        $estimate    = $portalToken->tokenable;
        $inquiry     = $estimate?->inquiry;

        abort_unless($inquiry, 404);
        abort_unless(
            $document->documentable_type === Inquiry::class
            && $document->documentable_id === $inquiry->id
            && $document->isImage(),
            403
        );

        return Documents::stream($document, false); // false = attachment (download)
    }

    public function downloadAllPhotos(string $token)
    {
        $portalToken = $this->resolveToken($token);
        $estimate    = $portalToken->tokenable;
        $inquiry     = $estimate?->inquiry;

        abort_unless($inquiry, 404);

        $documents    = $inquiry->documents()->where('mime_type', 'like', 'image/%')->get();
        $legacyPhotos = $inquiry->photos;

        abort_if($documents->isEmpty() && $legacyPhotos->isEmpty(), 404, 'No photos available.');

        $tempDir  = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $baseName  = 'survey-' . $estimate->estimate_no . '-' . time();
        $usedNames = [];
        $idx       = 1;

        if (class_exists('ZipArchive')) {
            // ZIP (preferred when extension is available)
            $archivePath  = $tempDir . '/' . $baseName . '.zip';
            $downloadName = 'survey-photos-' . $estimate->estimate_no . '-' . $inquiry->inquiry_no . '.zip';

            $zip = new \ZipArchive();
            abort_unless($zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true, 500);

            foreach ($documents as $doc) {
                try {
                    $content = Documents::get($doc);
                    $name    = $doc->original_name ?: ('photo-' . $idx . '.jpg');
                    if (isset($usedNames[$name])) {
                        $info = pathinfo($name);
                        $name = ($info['filename'] ?? 'photo') . '-' . $idx . '.' . ($info['extension'] ?? 'jpg');
                    }
                    $usedNames[$name] = true;
                    $zip->addFromString($name, $content);
                    $idx++;
                } catch (\Throwable $e) {
                    \Log::warning('Portal ZIP: skipped document ' . $doc->id . ': ' . $e->getMessage());
                }
            }

            foreach ($legacyPhotos as $photo) {
                try {
                    $fullPath = public_path('storage/' . $photo->photo_path);
                    if (file_exists($fullPath)) {
                        $ext  = pathinfo($photo->photo_path, PATHINFO_EXTENSION) ?: 'jpg';
                        $name = 'photo-' . $idx . '.' . $ext;
                        $zip->addFile($fullPath, $name);
                        $idx++;
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Portal ZIP: skipped legacy photo ' . $photo->id . ': ' . $e->getMessage());
                }
            }

            $zip->close();

        } else {
            // Fallback: tar.gz (no php-zip extension required)
            $tarPath      = $tempDir . '/' . $baseName . '.tar';
            $archivePath  = $tarPath . '.gz';
            $downloadName = 'survey-photos-' . $estimate->estimate_no . '-' . $inquiry->inquiry_no . '.tar.gz';

            $phar = new \PharData($tarPath);

            foreach ($documents as $doc) {
                try {
                    $content = Documents::get($doc);
                    $name    = $doc->original_name ?: ('photo-' . $idx . '.jpg');
                    if (isset($usedNames[$name])) {
                        $info = pathinfo($name);
                        $name = ($info['filename'] ?? 'photo') . '-' . $idx . '.' . ($info['extension'] ?? 'jpg');
                    }
                    $usedNames[$name] = true;
                    $phar->addFromString($name, $content);
                    $idx++;
                } catch (\Throwable $e) {
                    \Log::warning('Portal archive: skipped document ' . $doc->id . ': ' . $e->getMessage());
                }
            }

            foreach ($legacyPhotos as $photo) {
                try {
                    $fullPath = public_path('storage/' . $photo->photo_path);
                    if (file_exists($fullPath)) {
                        $ext  = pathinfo($photo->photo_path, PATHINFO_EXTENSION) ?: 'jpg';
                        $name = 'photo-' . $idx . '.' . $ext;
                        $phar->addFile($fullPath, $name);
                        $idx++;
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Portal archive: skipped legacy photo ' . $photo->id . ': ' . $e->getMessage());
                }
            }

            $phar->compress(\Phar::GZ);
            unset($phar);
            @unlink($tarPath);
        }

        return response()->download($archivePath, $downloadName)->deleteFileAfterSend(true);
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
