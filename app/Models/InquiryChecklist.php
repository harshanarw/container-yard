<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InquiryChecklist extends Model
{
    use HasFactory;

    protected $fillable = [
        'inquiry_id', 'checklist_item', 'is_checked',
    ];

    protected $casts = [
        'is_checked' => 'boolean',
    ];

    // Relationships
    public function inquiry()
    {
        return $this->belongsTo(Inquiry::class);
    }
}
