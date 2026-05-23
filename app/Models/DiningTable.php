<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TableStatus;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'organization_id',
    'name',
    'code',
    'capacity',
    'status',
    'location_label',
])]
class DiningTable extends Model
{
    use HasFactory, BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
            'capacity' => 'integer',
        ];
    }

    public function qrCode(): HasOne
    {
        return $this->hasOne(TableQrCode::class);
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(TableQrCode::class);
    }
}
