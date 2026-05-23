<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerSessionStatus;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'dining_table_id',
    'open_bill_id',
    'session_token',
    'client_label',
    'status',
    'started_at',
    'closed_at',
    'expires_at',
    'last_seen_at',
    'metadata',
])]
class CustomerSession extends Model
{
    use HasFactory, HasUuids, BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'status' => CustomerSessionStatus::class,
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(OpenBill::class, 'open_bill_id');
    }
}
