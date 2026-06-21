<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class CustomerEmailContact extends Model
{
    protected $fillable = ['customer_id', 'category', 'email', 'label', 'address_type', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public static function forCustomerCategory(int $customerId, string $category): Collection
    {
        return static::where('customer_id', $customerId)
            ->where('category', $category)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('address_type')
            ->get();
    }
}
