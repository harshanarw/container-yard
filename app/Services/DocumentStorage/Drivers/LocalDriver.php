<?php

namespace App\Services\DocumentStorage\Drivers;

use App\Models\Document;
use App\Services\DocumentStorage\Contracts\StorageDriver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LocalDriver implements StorageDriver
{
    private string $disk;

    public function __construct(string $disk = 'public')
    {
        $this->disk = $disk;
    }

    public function upload(UploadedFile $file, string $folder): Document
    {
        $filename = Str::random(16) . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                    . '.' . $file->getClientOriginalExtension();
        $path = trim($folder, '/') . '/' . $filename;

        Storage::disk($this->disk)->put($path, file_get_contents($file->getRealPath()));

        return new Document([
            'provider'      => 'local',
            'disk'          => $this->disk,
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType() ?? 'application/octet-stream',
            'size'          => $file->getSize(),
        ]);
    }

    public function get(Document $document): string
    {
        return Storage::disk($document->disk ?: $this->disk)->get($document->path);
    }

    public function stream(Document $document, bool $inline = true): StreamedResponse
    {
        $disk = Storage::disk($document->disk ?: $this->disk);
        abort_unless($disk->exists($document->path), 404);

        $disposition = $inline ? 'inline' : 'attachment';

        return response()->streamDownload(
            fn () => print($disk->get($document->path)),
            $document->original_name,
            [
                'Content-Type'        => $document->mime_type,
                'Content-Disposition' => "{$disposition}; filename=\"{$document->original_name}\"",
                'Cache-Control'       => 'private, max-age=3600',
            ]
        );
    }

    public function delete(Document $document): void
    {
        Storage::disk($document->disk ?: $this->disk)->delete($document->path);
    }

    public function testConnection(): array
    {
        try {
            $test = 'docmgr_test_' . time() . '.txt';
            Storage::disk($this->disk)->put($test, 'ok');
            Storage::disk($this->disk)->delete($test);
            return ['ok' => true, 'message' => 'Local storage is working correctly.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Local storage error: ' . $e->getMessage()];
        }
    }
}
