<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InquiryPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'inquiry_id', 'photo_path', 'uploaded_by',
    ];

    // Relationships
    public function inquiry()
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getPhotoUrlAttribute(): string
    {
        return asset('storage/' . $this->photo_path);
    }
}
