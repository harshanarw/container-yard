<?php

namespace App\Services\DocumentStorage\Contracts;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface StorageDriver
{
    /**
     * Upload a file and return an unsaved Document model populated with
     * all storage fields. The caller is responsible for setting
     * documentable_type/id and saving.
     */
    public function upload(UploadedFile $file, string $folder): Document;

    /**
     * Return the raw file content as a string.
     */
    public function get(Document $document): string;

    /**
     * Return a StreamedResponse suitable for browser preview/download.
     */
    public function stream(Document $document, bool $inline = true): StreamedResponse;

    /**
     * Delete the file from the storage provider.
     */
    public function delete(Document $document): void;

    /**
     * Check whether the provider is reachable and the credentials are valid.
     * Returns ['ok' => bool, 'message' => string].
     */
    public function testConnection(): array;
}
