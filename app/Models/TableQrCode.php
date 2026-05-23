<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QrCodeStatus;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'dining_table_id',
    'qr_token',
    'qr_url',
    'status',
    'last_scanned_at',
])]
class TableQrCode extends Model
{
    use HasFactory, BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'status' => QrCodeStatus::class,
            'last_scanned_at' => 'datetime',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }
}
