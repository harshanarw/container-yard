<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountryState extends Model
{
    protected $fillable = ['country_id', 'parent_id', 'name', 'code', 'type', 'sort_order'];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function hasDistricts(): bool
    {
        return $this->children()->exists();
    }
}
