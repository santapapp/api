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

class Order extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::updated(function (Order $order): void {
            if ($order->wasChanged('payment_status') && $order->payment_status === \App\Enums\PaymentStatus::Paid) {
                try {
                    event(\App\Events\OrderPaid::fromOrder($order->fresh(['diningTable'])));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Gagal melakukan broadcast OrderPaid: ' . $e->getMessage());
                }
            }
        });
    }

    protected $fillable = [
        'order_number',
        'public_token',
        'organization_id',
        'dining_table_id',
        'order_marker_number',
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
            'order_marker_number'          => 'integer',
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

    public function getDerivedSessionStatus(): string
    {
        if ($this->isCancelled() || $this->cancelled_at !== null) {
            return 'Dibatalkan';
        }
        if ($this->bill_status === BillStatus::Closed || $this->closed_at !== null) {
            return 'Ditutup';
        }
        if ($this->bill_status === BillStatus::Open) {
            return 'Aktif';
        }
        return 'Tidak valid';
    }

    public function getDerivedSessionStatusColor(): string
    {
        if ($this->isCancelled() || $this->cancelled_at !== null) {
            return 'danger';
        }
        if ($this->bill_status === BillStatus::Closed || $this->closed_at !== null) {
            return 'gray';
        }
        if ($this->bill_status === BillStatus::Open) {
            return 'success';
        }
        return 'warning';
    }

    /**
     * Apakah order ini adalah table order (pesanan langsung dari meja).
     */
    public function isTableOrder(): bool
    {
        return $this->order_type === OrderType::TableOrder;
    }

    /**
     * Apakah pembayaran sudah melewati deadline (payment_expires_at).
     * Hanya relevan saat payment_status masih pending.
     */
    public function isPaymentExpired(): bool
    {
        return $this->payment_status === PaymentStatus::Pending
            && $this->payment_expires_at !== null
            && $this->payment_expires_at->isPast();
    }

    /**
     * Tandai order LUNAS — sumber kebenaran tunggal untuk transisi ke paid.
     *
     * Idempotent: jika sudah paid, tidak melakukan apa pun (aman dipanggil
     * berulang oleh polling/scheduler). Mendukung rekonsiliasi: order yang
     * sebelumnya cancelled/expired tetap bisa diangkat menjadi paid bila
     * provider ternyata settlement (gateway = source of truth).
     *
     * @param bool $closeBill tutup bill (open bill) saat lunas.
     */
    public function markPaid(bool $closeBill = false): void
    {
        if ($this->payment_status === PaymentStatus::Paid) {
            if ($closeBill && $this->bill_status !== BillStatus::Closed) {
                $this->update([
                    'bill_status' => BillStatus::Closed,
                    'closed_at'   => $this->closed_at ?? now(),
                ]);
            }
            return;
        }

        $nextOrderStatus = in_array($this->order_status, [OrderStatus::Pending, OrderStatus::Cancelled], true)
            ? OrderStatus::Confirmed
            : $this->order_status;

        $attributes = [
            'payment_status' => PaymentStatus::Paid,
            'payment_amount' => $this->total_amount,
            'change_amount'  => 0,
            'order_status'   => $nextOrderStatus,
            'paid_at'        => $this->paid_at ?? now(),
            // Bersihkan jejak pembatalan jika order direkonsiliasi dari cancelled.
            'cancel_reason'  => null,
            'cancelled_at'   => null,
        ];

        if ($closeBill) {
            $attributes['bill_status'] = BillStatus::Closed;
            $attributes['closed_at']   = $this->closed_at ?? now();
        }

        $this->update($attributes);
    }

    /**
     * Tandai order sebagai dibatalkan karena pembayaran timeout/gagal.
     *
     * Sumber kebenaran tunggal untuk expiry order — dipakai oleh endpoint
     * tracking/payment-status publik dan command terjadwal agar status
     * konsisten (payment_status=cancelled, order_status=cancelled).
     *
     * PENTING: pemanggil WAJIB melakukan final sync ke provider lebih dulu;
     * order tidak boleh dibatalkan hanya berdasarkan deadline lokal.
     */
    public function markPaymentExpired(?string $reason = null): void
    {
        // Jangan pernah membatalkan order yang sudah lunas.
        if ($this->payment_status === PaymentStatus::Paid) {
            return;
        }

        $this->update([
            'order_status'   => OrderStatus::Cancelled,
            'payment_status' => PaymentStatus::Cancelled,
            'cancel_reason'  => $reason ?? 'Payment Timeout',
            'cancelled_at'   => now(),
        ]);
    }

    // ── Sinkronisasi status order ↔ item (item-driven) ─────────────

    /**
     * Turunkan order_status dari agregat item_status (item = source of truth).
     *
     * Pemetaan:
     *   semua item served            → completed
     *   semua item ready/served      → ready
     *   ada item preparing/ready/... → preparing
     *   sisanya (semua pending)      → confirmed
     *
     * Item cancelled diabaikan dari agregasi. Order yang masih `pending` (belum
     * dikonfirmasi/dibayar) atau sudah `cancelled` TIDAK disentuh — kedua state
     * itu dikendalikan jalur lain (pembayaran / pembatalan eksplisit).
     */
    public function syncStatusFromItems(): void
    {
        if (in_array($this->order_status, [OrderStatus::Pending, OrderStatus::Cancelled], true)) {
            return;
        }

        $statuses = $this->allItems()
            ->where('item_status', '!=', ItemStatus::Cancelled->value)
            ->get()
            ->pluck('item_status');

        // Tanpa item aktif, biarkan status apa adanya (pembatalan dilakukan eksplisit).
        if ($statuses->isEmpty()) {
            return;
        }

        $allServed  = $statuses->every(fn (ItemStatus $s): bool => $s === ItemStatus::Served);
        $allReady   = $statuses->every(fn (ItemStatus $s): bool => in_array($s, [ItemStatus::Ready, ItemStatus::Served], true));
        $anyStarted = $statuses->contains(fn (ItemStatus $s): bool => $s->rank() >= ItemStatus::Preparing->rank());

        $derived = match (true) {
            $allServed  => OrderStatus::Completed,
            $allReady   => OrderStatus::Ready,
            $anyStarted => OrderStatus::Preparing,
            default     => OrderStatus::Confirmed,
        };

        if ($this->order_status !== $derived) {
            $this->update(['order_status' => $derived]);
        }
    }

    /**
     * Majukan semua item aktif satu tahap (pending→preparing→ready→served),
     * lalu rollup order_status. Dipakai aksi "advance" di dashboard.
     */
    public function advanceItems(): void
    {
        $this->allItems()
            ->where('item_status', '!=', ItemStatus::Cancelled->value)
            ->get()
            ->each(function (OrderItem $item): void {
                $next = $item->item_status?->next();
                if ($next !== null) {
                    $item->update(['item_status' => $next]);
                }
            });

        $this->syncStatusFromItems();
    }

    /**
     * Batalkan semua item aktif (dipakai saat order dibatalkan).
     */
    public function cancelItems(): void
    {
        $this->allItems()
            ->where('item_status', '!=', ItemStatus::Cancelled->value)
            ->update(['item_status' => ItemStatus::Cancelled->value]);
    }

    /**
     * Hitung ulang semua nilai finansial order.
     *
     * Hanya menghitung dari root order_items (parent_item_id IS NULL)
     * yang tidak cancelled. Setiap root item sudah menyimpan subtotal
     * yang mencakup base_price + variant_total (dari metadata).
     *
     * Formula:
     *   subtotal = SUM(subtotal dari root items non-cancelled)
     *   tax      = subtotal × tax_rate_snapshot / 100
     *   service  = subtotal × service_charge_rate_snapshot / 100
     *   total    = subtotal - discount + tax + service
     */
    public function recalculate(): void
    {
        $subtotal = (float) $this->allItems()
            ->whereNull('parent_item_id')
            ->where('item_status', '!=', ItemStatus::Cancelled->value)
            ->sum('subtotal');

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
