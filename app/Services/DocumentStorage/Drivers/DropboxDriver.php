<?php

namespace App\Services\DocumentStorage\Drivers;

use App\Models\Document;
use App\Services\DocumentStorage\Contracts\StorageDriver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dropbox storage driver.
 *
 * Requires: composer require spatie/flysystem-dropbox
 */
class DropboxDriver implements StorageDriver
{
    private Filesystem $fs;
    private string $root;

    public function __construct(array $config)
    {
        $this->ensurePackageInstalled();

        $client    = new \Spatie\Dropbox\Client($config['access_token']);
        $adapter   = new \Spatie\FlysystemDropbox\DropboxAdapter($client);
        $this->fs  = new Filesystem($adapter);
        $this->root = trim($config['root'] ?? '/container-yard', '/');
    }

    public function upload(UploadedFile $file, string $folder): Document
    {
        $filename = Str::random(16) . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                    . '.' . $file->getClientOriginalExtension();
        $path = $this->root . '/' . trim($folder, '/') . '/' . $filename;

        $this->fs->write($path, file_get_contents($file->getRealPath()));

        return new Document([
            'provider'      => 'dropbox',
            'disk'          => 'dropbox',
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType() ?? 'application/octet-stream',
            'size'          => $file->getSize(),
        ]);
    }

    public function get(Document $document): string
    {
        return $this->fs->read($document->path);
    }

    public function stream(Document $document, bool $inline = true): StreamedResponse
    {
        abort_unless($this->fs->fileExists($document->path), 404);

        $content     = $this->fs->read($document->path);
        $disposition = $inline ? 'inline' : 'attachment';

        return response()->streamDownload(
            fn () => print($content),
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
        if ($this->fs->fileExists($document->path)) {
            $this->fs->delete($document->path);
        }
    }

    public function testConnection(): array
    {
        try {
            $test = $this->root . '/docmgr_test_' . time() . '.txt';
            $this->fs->write($test, 'connection test');
            $this->fs->delete($test);
            return ['ok' => true, 'message' => 'Dropbox connection successful.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Dropbox error: ' . $e->getMessage()];
        }
    }

    private function ensurePackageInstalled(): void
    {
        if (!class_exists(\Spatie\FlysystemDropbox\DropboxAdapter::class)) {
            throw new \RuntimeException(
                'Dropbox driver requires: composer require spatie/flysystem-dropbox'
            );
        }
    }
}
