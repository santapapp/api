<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'name',
    'slug',
    'description',
    'sort_order',
    'status',
])]
class MenuCategory extends Model
{
    use HasFactory, BelongsToOrganization;

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }
}
