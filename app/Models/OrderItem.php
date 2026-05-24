<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'menu_id',
        'parent_item_id',
        'name',
        'price',
        'quantity',
        'item_status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'item_status' => ItemStatus::class,
            'price' => 'decimal:2',
            'quantity' => 'integer',
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

    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'parent_item_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'parent_item_id');
    }
}
