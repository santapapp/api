<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'public_token',
        'organization_id',
        'dining_table_id',
        'created_by',
        'order_type',
        'bill_status',
        'order_status',
        'payment_status',
        'payment_method',
        'payment_reference',
        'payment_amount',
        'subtotal_amount',
        'total_amount',
        'note',
        'paid_at',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'order_type' => OrderType::class,
            'bill_status' => BillStatus::class,
            'order_status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_amount' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    // --- Relationships ---

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Root items saja (bukan variant/addon children).
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->whereNull('parent_item_id');
    }

    /**
     * Semua items termasuk children.
     */
    public function allItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // --- Helpers ---

    public function isOpen(): bool
    {
        return $this->bill_status === BillStatus::Open;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }

    /**
     * Hitung ulang subtotal & total dari root items.
     */
    public function recalculate(): void
    {
        $subtotal = (float) $this->allItems()->sum(DB::raw('price * quantity'));

        $this->update([
            'subtotal_amount' => $subtotal,
            'total_amount' => $subtotal,
            'payment_amount' => $subtotal,
        ]);
    }

    /**
     * Generate order number: ORD-YYYYMMDD-0001
     */
    public static function generateOrderNumber(int $organizationId): string
    {
        $today = now()->format('Ymd');
        $prefix = "ORD-{$today}-";

        $lastOrder = static::where('organization_id', $organizationId)
            ->where('order_number', 'like', "{$prefix}%")
            ->orderByDesc('order_number')
            ->first();

        if ($lastOrder) {
            $lastSeq = (int) substr($lastOrder->order_number, -4);
            $nextSeq = $lastSeq + 1;
        } else {
            $nextSeq = 1;
        }

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }
}
