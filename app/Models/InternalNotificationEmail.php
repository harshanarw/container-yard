<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class InternalNotificationEmail extends Model
{
    protected $fillable = ['category', 'email', 'label', 'address_type', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public static function forCategory(string $category): Collection
    {
        return static::where('category', $category)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('address_type')
            ->get();
    }
}
