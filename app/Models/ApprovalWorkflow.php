<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflow extends Model
{
    protected $fillable = [
        'document_type', 'step_order', 'step_key', 'step_label',
        'required_role', 'auto_approve_on_create', 'is_active',
    ];

    protected $casts = [
        'auto_approve_on_create' => 'boolean',
        'is_active'              => 'boolean',
    ];

    public static function stepsFor(string $documentType): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('document_type', $documentType)
            ->where('is_active', true)
            ->orderBy('step_order')
            ->get();
    }
}
