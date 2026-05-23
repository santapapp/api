<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'organization_id',
    'open_bill_id',
    'customer_session_id',
    'dining_table_id',
    'order_number',
    'source',
    'status',
    'note',
    'subtotal_amount',
    'total_amount',
    'created_by',
    'accepted_by',
    'cancelled_by',
    'cancel_reason',
])]
class Order extends Model
{
    use HasFactory, BelongsToOrganization, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function openBill(): BelongsTo
    {
        return $this->belongsTo(OpenBill::class);
    }

    public function customerSession(): BelongsTo
    {
        return $this->belongsTo(CustomerSession::class);
    }

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
