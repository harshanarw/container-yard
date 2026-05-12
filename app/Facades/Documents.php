<?php

namespace App\Facades;

use App\Services\DocumentStorage\DocumentManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Models\Document upload(\Illuminate\Http\UploadedFile $file, string $folder, array $extra = [])
 * @method static \App\Models\Document uploadFor(object $model, \Illuminate\Http\UploadedFile $file, string $folder, array $extra = [])
 * @method static \Symfony\Component\HttpFoundation\StreamedResponse stream(\App\Models\Document $document, bool $inline = true)
 * @method static string get(\App\Models\Document $document)
 * @method static void delete(\App\Models\Document $document)
 * @method static array testConnection()
 * @method static string provider()
 * @method static void flushDriver()
 *
 * @see \App\Services\DocumentStorage\DocumentManager
 */
class Documents extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DocumentManager::class;
    }
}
