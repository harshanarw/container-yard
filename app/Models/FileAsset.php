<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Route;

/**
 * Ledger row for a single uploaded file. Populated by StorageService (direct
 * uploads) and by the Document observer (DocumentManager uploads). The sum of
 * `size` is the app's total storage usage.
 */
class FileAsset extends Model
{
    protected $fillable = [
        'disk', 'path', 'section', 'size', 'mime_type',
        'owner_type', 'owner_id', 'document_id', 'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    /** Human labels for each storage section (used by the report / dashboard). */
    public const SECTION_LABELS = [
        'guard_post'               => 'Guard Post captures',
        'gate_ocr'                 => 'Gate OCR images',
        'gate_photo'               => 'Gate Movement Photos',
        'survey'                   => 'Survey Captures',
        'repair_estimate'          => 'Repair Estimates',
        'supplier_invoice'         => 'Supplier Invoices',
        'storage_invoice'          => 'Storage Invoices',
        'storage_handling_invoice' => 'Storage & Handling Invoices',
        'document'                 => 'Documents',
        'company'                  => 'Company assets',
        'customer'                 => 'Customer files',
        'user'                     => 'User photos',
        'other'                    => 'Other',
    ];

    /** Donut/legend colour per section — shared by the report and dashboard. */
    public const SECTION_COLORS = [
        'guard_post'               => '#0d6efd',
        'gate_ocr'                 => '#6610f2',
        'gate_photo'               => '#6f42c1',
        'survey'                   => '#d63384',
        'repair_estimate'          => '#dc3545',
        'supplier_invoice'         => '#fd7e14',
        'storage_invoice'          => '#ffc107',
        'storage_handling_invoice' => '#198754',
        'document'                 => '#20c997',
        'company'                  => '#0dcaf0',
        'customer'                 => '#adb5bd',
        'user'                     => '#795548',
        'other'                    => '#6c757d',
    ];

    /**
     * Owning record class (documentable_type) → ledger section, so files uploaded
     * through the Document system land in a meaningful bucket instead of a single
     * "Documents" catch-all. Keyed by fully-qualified class name (the app uses no
     * morph map, so owner_type is always an FQCN).
     */
    public const OWNER_SECTIONS = [
        \App\Models\Inquiry::class                => 'survey',
        \App\Models\Estimate::class               => 'repair_estimate',
        \App\Models\GateMovement::class           => 'gate_photo',
        \App\Models\SupplierInvoice::class        => 'supplier_invoice',
        \App\Models\StorageInvoice::class         => 'storage_invoice',
        \App\Models\StorageHandlingInvoice::class => 'storage_handling_invoice',
        \App\Models\Customer::class               => 'customer',
    ];

    /** Owner class → [human-reference column, route name to that record]. */
    public const OWNER_REFERENCES = [
        \App\Models\Inquiry::class                => ['inquiry_no', 'inquiries.show'],
        \App\Models\Estimate::class               => ['estimate_no', 'estimates.show'],
        \App\Models\GateMovement::class           => ['container_no', 'yard.movements.edit'],
        \App\Models\SupplierInvoice::class        => ['invoice_no', 'finance.ap.invoices.show'],
        \App\Models\StorageInvoice::class         => ['invoice_no', 'billing.show'],
        \App\Models\StorageHandlingInvoice::class => ['invoice_no', 'billing.storage-handling.show'],
        \App\Models\Customer::class               => ['code', 'customers.show'],
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function getSectionLabelAttribute(): string
    {
        return self::SECTION_LABELS[$this->section] ?? ucfirst(str_replace('_', ' ', (string) $this->section));
    }

    /** Ledger section for a document, derived from its owning record's class. */
    public static function sectionForOwner(?string $ownerType): string
    {
        return $ownerType ? (self::OWNER_SECTIONS[$ownerType] ?? 'document') : 'document';
    }

    /**
     * Human reference to the owning business record for the report:
     * ['number' => 'INQ-0007', 'url' => '/inquiries/7'] or null when the owner
     * has no mapped reference (e.g. company/user assets).
     */
    public function ownerReference(): ?array
    {
        if (! $this->owner_type || ! isset(self::OWNER_REFERENCES[$this->owner_type])) {
            return null;
        }

        [$column, $routeName] = self::OWNER_REFERENCES[$this->owner_type];
        $owner  = $this->relationLoaded('owner') ? $this->owner : $this->owner()->first();
        $number = ($owner?->{$column}) ?: ('#' . $this->owner_id);
        $url    = ($owner && Route::has($routeName)) ? route($routeName, $owner) : null;

        return ['number' => $number, 'url' => $url];
    }
}
