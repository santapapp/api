<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemVariant extends Model
{
    protected $fillable = [
        'order_item_id',
        'variant_group_id',
        'variant_id',
        'variant_group_name',
        'variant_name',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * FK nullable ke menus (untuk traceability).
     * Bisa null jika menu sudah dihapus — snapshot tetap aman.
     */
    public function variantGroup(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'variant_group_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'variant_id');
    }
}
