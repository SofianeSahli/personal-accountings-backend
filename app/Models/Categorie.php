<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categorie extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'description',
        'parent_id',
        'is_income',
    ];

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Categorie::class, 'parent_id');
    }

    /**
     * Get the subcategories for this category.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Categorie::class, 'parent_id');
    }

    /**
     * Get all descendants of this category.
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get all ancestors of this category.
     */
    public function ancestors(): BelongsTo
    {
        return $this->parent()->with('ancestors');
    }
}
