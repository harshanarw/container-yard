<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\FileAsset;
use App\Models\User;
use App\Services\DocumentStorage\DocumentManager;
use App\Services\StorageUsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Storage report (Phase 2): overall usage, per-section breakdown, a filtered
 * file list and largest files, with previews served through short-lived signed
 * routes (the guard-post images are NIC/licence PII). Admin-gated.
 */
class StorageReportController extends Controller
{
    private const MANAGE_ROLES = ['administrator', 'system_administrator'];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! in_array(auth()->user()->role ?? null, self::MANAGE_ROLES, true)) {
                abort(403, 'Administrator access required.');
            }
            return $next($request);
        });
    }

    public function index(Request $request, StorageUsageService $usage)
    {
        $summary = $usage->summary();

        $section  = $request->query('section');
        $uploader = $request->query('uploader');
        $minMb    = (float) $request->query('min_mb', 0);
        $q        = trim((string) $request->query('q', ''));
        $from     = $request->query('from');
        $to       = $request->query('to');

        $files = FileAsset::query()
            ->with('owner')
            ->when($section, fn ($w) => $w->where('section', $section))
            ->when($uploader, fn ($w) => $w->where('uploaded_by', $uploader))
            ->when($minMb > 0, fn ($w) => $w->where('size', '>=', (int) round($minMb * 1048576)))
            ->when($q !== '', fn ($w) => $w->where('path', 'like', "%{$q}%"))
            ->when($from, fn ($w) => $w->whereDate('created_at', '>=', $from))
            ->when($to, fn ($w) => $w->whereDate('created_at', '<=', $to))
            ->orderByDesc('size')
            ->paginate(30)
            ->withQueryString();

        $files->getCollection()->transform(fn (FileAsset $a) => $this->decorate($a));

        $largest = FileAsset::orderByDesc('size')->limit(10)->get()->map(fn ($a) => $this->decorate($a));

        $uploaders = User::whereIn('id', FileAsset::whereNotNull('uploaded_by')->distinct()->pluck('uploaded_by'))
            ->orderBy('name')->get(['id', 'name']);

        return view('storage.report', compact(
            'summary', 'files', 'largest', 'uploaders',
            'section', 'uploader', 'minMb', 'q', 'from', 'to'
        ));
    }

    /** Attach short-lived signed preview/download URLs + an image flag. */
    private function decorate(FileAsset $asset): FileAsset
    {
        $expires = now()->addMinutes(15);
        $asset->preview_url  = URL::temporarySignedRoute('storage.preview', $expires, ['asset' => $asset->id, 'inline' => 1]);
        $asset->download_url = URL::temporarySignedRoute('storage.preview', $expires, ['asset' => $asset->id]);
        $asset->is_image     = str_starts_with((string) $asset->mime_type, 'image/');

        return $asset;
    }

    /** Stream a file for inline preview or download (signed route). */
    public function preview(Request $request, FileAsset $asset)
    {
        $inline = (bool) $request->query('inline', false);

        // Documents go through the DocumentManager so cloud drivers work too.
        if ($asset->document_id && ($doc = Document::find($asset->document_id))) {
            return app(DocumentManager::class)->stream($doc, $inline);
        }

        $disk = Storage::disk($asset->disk);
        if (! $disk->exists($asset->path)) {
            abort(404, 'File no longer exists.');
        }

        $name = basename($asset->path);

        return $inline ? $disk->response($asset->path, $name) : $disk->download($asset->path, $name);
    }
}
