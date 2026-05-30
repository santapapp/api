<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MenuType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'parent_id',
        'type',
        'name',
        'image',
        'sku',
        'description',
        'price',
        'is_available',
        'is_required',
        'min_select',
        'max_select',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type'         => MenuType::class,
            'price'        => 'decimal:2',
            'is_available' => 'boolean',
            'is_required'  => 'boolean',
            'min_select'   => 'integer',
            'max_select'   => 'integer',
            'sort_order'   => 'integer',
            'metadata'     => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Scope: hanya produk root (tanpa parent).
     */
    public function scopeProducts(Builder $query): Builder
    {
        return $query->whereNull('parent_id')->where('type', MenuType::Product);
    }

    /**
     * Scope: hanya yang tersedia.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }

    /**
     * Scope: variant groups dan addon groups (selector rules).
     */
    public function scopeGroups(Builder $query): Builder
    {
        return $query->whereIn('type', [MenuType::VariantGroup, MenuType::AddonGroup]);
    }

    /**
     * Scope: variant dan addon.
     */
    public function scopeItems(Builder $query): Builder
    {
        return $query->whereIn('type', [MenuType::Variant, MenuType::Addon]);
    }
}
