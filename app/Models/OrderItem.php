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
        // Harga breakdown
        'base_price',
        'variant_total',
        'unit_price',
        'price',       // Legacy: sama dengan unit_price — dipertahankan untuk backward compat
        'quantity',
        'subtotal',
        'item_status',
        'note',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'item_type'    => ItemType::class,
            'item_status'  => ItemStatus::class,
            'base_price'   => 'decimal:2',
            'variant_total'=> 'decimal:2',
            'unit_price'   => 'decimal:2',
            'price'        => 'decimal:2',
            'subtotal'     => 'decimal:2',
            'quantity'     => 'integer',
            'metadata'     => 'array',
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

    /**
     * Parent item — dipertahankan untuk backward compat (addon model lama).
     */
    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'parent_item_id');
    }

    /**
     * Child items — dipertahankan untuk backward compat (addon model lama).
     */
    public function children(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'parent_item_id');
    }

    /**
     * Variant pilihan customer — model baru canonical.
     */
    public function selectedVariants(): HasMany
    {
        return $this->hasMany(OrderItemVariant::class);
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
     * Recalculate subtotal = unit_price × quantity.
     * unit_price = base_price + variant_total.
     */
    public function syncSubtotal(): void
    {
        $unitPrice = round((float) $this->base_price + (float) $this->variant_total, 2);

        $this->update([
            'unit_price' => $unitPrice,
            'price'      => $unitPrice, // sync legacy field
            'subtotal'   => round($unitPrice * $this->quantity, 2),
        ]);
    }
}
