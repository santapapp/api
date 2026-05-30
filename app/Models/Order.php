<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillStatus;
use App\Enums\ItemStatus;
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
        'cancelled_by',
        'cancel_reason',
        'cancelled_at',
        // Customer info
        'customer_name',
        'customer_phone',
        // Order state
        'order_type',
        'bill_status',
        'order_status',
        'payment_status',
        // Snapshot rates — dikunci saat order dibuat
        'tax_rate_snapshot',
        'service_charge_rate_snapshot',
        // Financial
        'subtotal_amount',
        'discount_amount',
        'tax_amount',
        'service_charge_amount',
        'total_amount',
        'payment_amount',
        'change_amount',
        // Payment
        'payment_method',
        'payment_reference',
        'payment_expires_at',
        // Timestamps
        'note',
        'paid_at',
        'opened_at',
        'closed_at',
        // Metadata
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'order_type'                   => OrderType::class,
            'bill_status'                  => BillStatus::class,
            'order_status'                 => OrderStatus::class,
            'payment_status'               => PaymentStatus::class,
            'subtotal_amount'              => 'decimal:2',
            'discount_amount'              => 'decimal:2',
            'tax_amount'                   => 'decimal:2',
            'service_charge_amount'        => 'decimal:2',
            'total_amount'                 => 'decimal:2',
            'payment_amount'               => 'decimal:2',
            'change_amount'                => 'decimal:2',
            'tax_rate_snapshot'            => 'decimal:2',
            'service_charge_rate_snapshot' => 'decimal:2',
            'paid_at'                      => 'datetime',
            'opened_at'                    => 'datetime',
            'closed_at'                    => 'datetime',
            'cancelled_at'                 => 'datetime',
            'payment_expires_at'           => 'datetime',
            'metadata'                     => 'array',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────

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

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Root items saja (produk — bukan variant/addon children).
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->whereNull('parent_item_id');
    }

    /**
     * Semua items termasuk children (variant & addon).
     */
    public function allItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->bill_status === BillStatus::Open;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }

    public function isCancelled(): bool
    {
        return $this->order_status === OrderStatus::Cancelled;
    }

    /**
     * Hitung ulang semua nilai finansial order.
     *
     * Menghitung dari SEMUA order_items yang tidak cancelled
     * (termasuk addon berbayar — bukan hanya root product).
     * Formula:
     *   subtotal = SUM(subtotal dari semua item non-cancelled)
     *   tax      = subtotal × tax_rate_snapshot / 100
     *   service  = subtotal × service_charge_rate_snapshot / 100
     *   total    = subtotal - discount + tax + service
     */
    public function recalculate(): void
    {
        $subtotal = (float) $this->allItems()
            ->where('item_status', '!=', ItemStatus::Cancelled->value)
            ->sum(DB::raw('price * quantity'));

        $taxRate      = (float) $this->tax_rate_snapshot;
        $serviceRate  = (float) $this->service_charge_rate_snapshot;
        $discount     = (float) $this->discount_amount;

        $tax          = round($subtotal * $taxRate / 100, 2);
        $serviceCharge = round($subtotal * $serviceRate / 100, 2);
        $total        = $subtotal - $discount + $tax + $serviceCharge;

        $this->update([
            'subtotal_amount'       => $subtotal,
            'tax_amount'            => $tax,
            'service_charge_amount' => $serviceCharge,
            'total_amount'          => max(0, $total),
        ]);
    }

    /**
     * Generate order number: ORD-YYYYMMDD-0001
     */
    public static function generateOrderNumber(int $organizationId): string
    {
        $today  = now()->format('Ymd');
        $prefix = "ORD-{$today}-";

        $lastOrder = static::where('order_number', 'like', "{$prefix}%")
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
