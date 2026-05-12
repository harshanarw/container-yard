<?php

namespace App\Services\DocumentStorage\Drivers;

use App\Models\Document;
use App\Services\DocumentStorage\Contracts\StorageDriver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Google Drive storage driver.
 *
 * Requires:
 *   composer require masbug/flysystem-google-drive-ext google/apiclient
 */
class GoogleDriveDriver implements StorageDriver
{
    private Filesystem $fs;
    private \Google\Service\Drive $service;

    public function __construct(array $config)
    {
        $this->ensurePackageInstalled();

        $client = new \Google\Client();
        $client->setClientId($config['client_id']);
        $client->setClientSecret($config['client_secret']);
        $client->refreshToken($config['refresh_token']);

        $this->service = new \Google\Service\Drive($client);

        $adapter  = new \Masbug\Flysystem\GoogleDriveAdapter(
            $this->service,
            $config['folder_id'] ?? 'root'
        );
        $this->fs = new Filesystem($adapter);
    }

    public function upload(UploadedFile $file, string $folder): Document
    {
        $filename = Str::random(16) . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                    . '.' . $file->getClientOriginalExtension();
        $path = trim($folder, '/') . '/' . $filename;

        $this->fs->write($path, file_get_contents($file->getRealPath()));

        return new Document([
            'provider'      => 'gdrive',
            'disk'          => 'gdrive',
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
            $test = 'docmgr_test_' . time() . '.txt';
            $this->fs->write($test, 'connection test');
            $this->fs->delete($test);
            return ['ok' => true, 'message' => 'Google Drive connection successful.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Google Drive error: ' . $e->getMessage()];
        }
    }

    private function ensurePackageInstalled(): void
    {
        if (!class_exists(\Masbug\Flysystem\GoogleDriveAdapter::class)) {
            throw new \RuntimeException(
                'Google Drive driver requires: composer require masbug/flysystem-google-drive-ext google/apiclient'
            );
        }
    }
}
