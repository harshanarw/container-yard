<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Damage extends Model
{
    use HasFactory;

    protected $fillable = [
        'inquiry_id', 'location', 'damage_type', 'severity', 'dimensions', 'description',
    ];

    // Relationships
    public function inquiry()
    {
        return $this->belongsTo(Inquiry::class);
    }
}
