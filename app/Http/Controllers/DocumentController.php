<?php

namespace App\Http\Controllers;

use App\Facades\Documents;
use App\Models\Document;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    /**
     * Upload one or more files and attach them to a model.
     * POST /documents
     *
     * Required fields: documentable_type, documentable_id
     * Optional:        label, document_type, folder
     */
    public function store(Request $request)
    {
        $request->validate([
            'documentable_type' => ['required', 'string'],
            'documentable_id'   => ['required', 'integer'],
            'files'             => ['required', 'array', 'min:1', 'max:10'],
            'files.*'           => ['file', 'max:20480'], // 20 MB per file
            'label'             => ['nullable', 'string', 'max:100'],
            'document_type'     => ['nullable', 'string', 'in:photo,document,certificate,other'],
        ]);

        // Verify the model class is an allowed documentable
        $allowedTypes = [
            'App\\Models\\GateMovement',
            'App\\Models\\Inquiry',
            'App\\Models\\Customer',
            'App\\Models\\StorageInvoice',
            'App\\Models\\StorageHandlingInvoice',
            'App\\Models\\SupplierInvoice',
        ];

        abort_unless(in_array($request->documentable_type, $allowedTypes), 422, 'Invalid documentable type.');

        $modelClass = $request->documentable_type;
        $model      = $modelClass::findOrFail($request->documentable_id);

        // Build folder path: e.g. gate-movements/42
        $folder = $this->folderFor($request->documentable_type, $request->documentable_id);

        $uploaded = [];
        foreach ($request->file('files') as $file) {
            $uploaded[] = Documents::uploadFor($model, $file, $folder, [
                'label'         => $request->label,
                'document_type' => $request->document_type ?? 'photo',
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success'   => true,
                'documents' => collect($uploaded)->map(fn ($d) => $this->documentJson($d)),
            ]);
        }

        return back()->with('success', count($uploaded) . ' file(s) uploaded successfully.');
    }

    /**
     * Stream a file to the browser for inline preview.
     * GET /documents/{document}/preview
     */
    public function preview(Document $document)
    {
        return Documents::stream($document, inline: true);
    }

    /**
     * Force-download a file.
     * GET /documents/{document}/download
     */
    public function download(Document $document)
    {
        return Documents::stream($document, inline: false);
    }

    /**
     * Delete a document from storage and the database.
     * DELETE /documents/{document}
     */
    public function destroy(Document $document)
    {
        Documents::delete($document);

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Document deleted.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function folderFor(string $type, int $id): string
    {
        $map = [
            'App\\Models\\GateMovement'             => 'gate-movements',
            'App\\Models\\Inquiry'                  => 'surveys',
            'App\\Models\\Customer'                 => 'customers',
            'App\\Models\\StorageInvoice'           => 'invoices/storage',
            'App\\Models\\StorageHandlingInvoice'   => 'invoices/storage-handling',
            'App\\Models\\SupplierInvoice'          => 'invoices/supplier',
        ];

        return ($map[$type] ?? 'misc') . '/' . $id;
    }

    private function documentJson(Document $d): array
    {
        return [
            'id'            => $d->id,
            'original_name' => $d->original_name,
            'mime_type'     => $d->mime_type,
            'size'          => $d->size,
            'formatted_size'=> $d->formattedSize(),
            'icon'          => $d->icon(),
            'icon_color'    => $d->iconColor(),
            'is_image'      => $d->isImage(),
            'is_pdf'        => $d->isPdf(),
            'is_previewable'=> $d->isPreviewable(),
            'preview_url'   => route('documents.preview', $d),
            'download_url'  => route('documents.download', $d),
            'destroy_url'   => route('documents.destroy', $d),
            'uploaded_at'   => $d->created_at?->format('d M Y H:i'),
        ];
    }
}
