<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemStatus;
use App\Enums\ItemType;
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
        'item_type',
        'name',
        'price',
        'quantity',
        'subtotal',
        'item_status',
        'note',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'item_type'   => ItemType::class,
            'item_status' => ItemStatus::class,
            'price'       => 'decimal:2',
            'subtotal'    => 'decimal:2',
            'quantity'    => 'integer',
            'metadata'    => 'array',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────

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

    // ── Helpers ────────────────────────────────────────────────────

    public function isProduct(): bool
    {
        return $this->item_type === ItemType::Product;
    }

    public function isVariant(): bool
    {
        return $this->item_type === ItemType::Variant;
    }

    public function isAddon(): bool
    {
        return $this->item_type === ItemType::Addon;
    }

    public function isCancelled(): bool
    {
        return $this->item_status === ItemStatus::Cancelled;
    }

    /**
     * Hitung dan simpan subtotal = price × quantity.
     * Dipanggil setiap kali item dibuat atau quantity diupdate.
     */
    public function syncSubtotal(): void
    {
        $this->update([
            'subtotal' => round((float) $this->price * $this->quantity, 2),
        ]);
    }
}
