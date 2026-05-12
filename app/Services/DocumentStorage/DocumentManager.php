<?php

namespace App\Services\DocumentStorage;

use App\Models\CloudStorageSetting;
use App\Models\Document;
use App\Services\DocumentStorage\Contracts\StorageDriver;
use App\Services\DocumentStorage\Drivers\DropboxDriver;
use App\Services\DocumentStorage\Drivers\GoogleDriveDriver;
use App\Services\DocumentStorage\Drivers\LocalDriver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentManager
{
    private ?StorageDriver $driver = null;
    private ?CloudStorageSetting $settings = null;

    // ── Driver resolution ─────────────────────────────────────────────────────

    public function driver(): StorageDriver
    {
        if ($this->driver !== null) {
            return $this->driver;
        }

        $s = $this->settings();

        $this->driver = match ($s->provider) {
            'dropbox' => new DropboxDriver([
                'access_token' => $s->dropbox_access_token,
                'app_key'      => $s->dropbox_app_key,
                'app_secret'   => $s->dropbox_app_secret,
                'root'         => $s->dropbox_root_folder ?: '/container-yard',
            ]),
            'gdrive'  => new GoogleDriveDriver([
                'client_id'     => $s->gdrive_client_id,
                'client_secret' => $s->gdrive_client_secret,
                'refresh_token' => $s->gdrive_refresh_token,
                'folder_id'     => $s->gdrive_folder_id,
            ]),
            default   => new LocalDriver('public'),
        };

        return $this->driver;
    }

    public function settings(): CloudStorageSetting
    {
        if ($this->settings === null) {
            $this->settings = CloudStorageSetting::current();
        }
        return $this->settings;
    }

    /** Force re-resolution of the driver on next call (used after settings change). */
    public function flushDriver(): void
    {
        $this->driver   = null;
        $this->settings = null;
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Upload a file, persist a Document record, and return it.
     *
     * @param  UploadedFile  $file
     * @param  string        $folder   e.g. 'gate-movements/in/5'
     * @param  array         $extra    Extra attributes: label, document_type, documentable_type, documentable_id
     */
    public function upload(UploadedFile $file, string $folder, array $extra = []): Document
    {
        $document = $this->driver()->upload($file, $folder);

        $document->fill(array_merge([
            'uploaded_by' => Auth::id(),
        ], $extra));

        $document->save();

        return $document;
    }

    /**
     * Upload and attach directly to a model using morphMany.
     */
    public function uploadFor(object $model, UploadedFile $file, string $folder, array $extra = []): Document
    {
        $document = $this->driver()->upload($file, $folder);

        $document->fill(array_merge([
            'uploaded_by'       => Auth::id(),
            'documentable_type' => get_class($model),
            'documentable_id'   => $model->getKey(),
        ], $extra));

        $document->save();

        return $document;
    }

    /**
     * Stream a document to the browser for inline preview or download.
     */
    public function stream(Document $document, bool $inline = true): StreamedResponse
    {
        return $this->driver()->stream($document, $inline);
    }

    /**
     * Return raw file content (used for email attachments, etc.).
     */
    public function get(Document $document): string
    {
        return $this->driver()->get($document);
    }

    /**
     * Delete a document from storage and remove the DB record.
     */
    public function delete(Document $document): void
    {
        try {
            $this->driver()->delete($document);
        } catch (\Throwable) {
            // File may already be gone on the provider; proceed with DB cleanup.
        }
        $document->delete();
    }

    /**
     * Test the active provider connection.
     */
    public function testConnection(): array
    {
        return $this->driver()->testConnection();
    }

    /**
     * Return the active provider name.
     */
    public function provider(): string
    {
        return $this->settings()->provider ?? 'local';
    }
}
