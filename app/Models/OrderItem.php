<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'order_id',
    'menu_id',
    'menu_name_snapshot',
    'menu_price_snapshot',
    'quantity',
    'note',
    'status',
    'subtotal_amount',
])]
class OrderItem extends Model
{
    use HasFactory, BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'status' => OrderItemStatus::class,
            'menu_price_snapshot' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
