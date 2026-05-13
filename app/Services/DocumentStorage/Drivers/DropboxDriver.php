<?php

namespace App\Services\DocumentStorage\Drivers;

use App\Models\CloudStorageSetting;
use App\Models\Document;
use App\Services\DocumentStorage\Contracts\StorageDriver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dropbox storage driver.
 *
 * Supports two token modes:
 *   1. Refresh token (recommended) — obtained via OAuth2 flow in settings UI.
 *      Access token is auto-refreshed when it expires.
 *   2. Legacy long-lived access token — entered manually in settings.
 *
 * Requires: composer require spatie/flysystem-dropbox
 */
class DropboxDriver implements StorageDriver
{
    private Filesystem $fs;
    private string $root;
    private array $config;

    public function __construct(array $config)
    {
        $this->ensurePackageInstalled();

        $this->config = $config;
        $this->root   = trim($config['root'] ?? '/container-yard', '/');

        $this->fs = new Filesystem(
            new \Spatie\FlysystemDropbox\DropboxAdapter(
                new \Spatie\Dropbox\Client($this->resolveAccessToken())
            )
        );
    }

    // ── StorageDriver interface ───────────────────────────────────────────────

    public function upload(UploadedFile $file, string $folder): Document
    {
        $filename = Str::random(16) . '_'
                    . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
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
            $test = $this->root . '/.docmgr_test_' . time() . '.txt';
            $this->fs->write($test, 'connection test');
            $this->fs->delete($test);
            return ['ok' => true, 'message' => 'Dropbox connection successful.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Dropbox error: ' . $e->getMessage()];
        }
    }

    // ── Token resolution ──────────────────────────────────────────────────────

    /**
     * Returns a valid access token.
     * - If a refresh token is stored, auto-refreshes when expired.
     * - Falls back to the manually entered long-lived token.
     */
    private function resolveAccessToken(): string
    {
        $appKey      = $this->config['app_key']      ?? '';
        $appSecret   = $this->config['app_secret']   ?? '';
        $refreshToken = $this->config['refresh_token'] ?? '';

        // Preferred: refresh token flow
        if ($refreshToken && $appKey && $appSecret) {
            return $this->getFreshAccessToken($appKey, $appSecret, $refreshToken);
        }

        // Fallback: manually entered access token (may expire after 4h)
        $token = $this->config['access_token'] ?? '';

        if (empty($token)) {
            throw new \RuntimeException('Dropbox: no access token or refresh token configured.');
        }

        return $token;
    }

    /**
     * Returns a cached access token, refreshing it via the Dropbox API if expired.
     */
    private function getFreshAccessToken(string $appKey, string $appSecret, string $refreshToken): string
    {
        $settings = CloudStorageSetting::current();

        // Return cached token if still valid (with 60s buffer)
        if ($settings->dropboxAccessTokenIsValid()) {
            return $settings->dropbox_access_token_cache;
        }

        // Refresh the token
        $response = Http::asForm()->post('https://api.dropboxapi.com/oauth2/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id'     => $appKey,
            'client_secret' => $appSecret,
        ]);

        if (!$response->successful() || empty($response->json('access_token'))) {
            throw new \RuntimeException(
                'Dropbox token refresh failed: ' . ($response->json('error_description') ?? $response->status())
            );
        }

        $newToken  = $response->json('access_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 14400); // default 4h

        // Cache the new token
        $settings->update([
            'dropbox_access_token_cache' => $newToken,
            'dropbox_token_expires_at'   => now()->addSeconds($expiresIn - 60),
        ]);

        return $newToken;
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
