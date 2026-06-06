<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Organization;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return request()->query('order_type') === OrderType::OpenBill->value
            ? 'Buat Open Bill'
            : 'Buat Order';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $orderType = OrderType::from($data['order_type']);
        $org       = Organization::query()
            ->where('is_active', true)
            ->findOrFail((int) $data['organization_id']);
        $tableId   = filled($data['dining_table_id'] ?? null) ? (int) $data['dining_table_id'] : null;

        if ($tableId !== null) {
            DiningTable::query()
                ->where('organization_id', $org->id)
                ->where('is_active', true)
                ->findOrFail($tableId);
        }

        if (
            $orderType === OrderType::OpenBill
            && $tableId !== null
            && Order::query()
                ->where('dining_table_id', $tableId)
                ->where('order_type', OrderType::OpenBill->value)
                ->where('bill_status', BillStatus::Open->value)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'data.dining_table_id' => 'Meja ini sudah memiliki Open Bill aktif.',
            ]);
        }

        $isOpenBill = $orderType === OrderType::OpenBill;

        return [
            'order_number'                 => Order::generateOrderNumber($org->id),
            'public_token'                 => $isOpenBill ? Str::random(32) : null,
            'organization_id'              => $org->id,
            'dining_table_id'              => $tableId,
            'created_by'                   => auth()->id(),
            'customer_name'                => $data['customer_name'] ?? null,
            'customer_phone'               => $data['customer_phone'] ?? null,
            'order_type'                   => $orderType,
            'bill_status'                  => $isOpenBill ? BillStatus::Open : BillStatus::None,
            'order_status'                 => OrderStatus::Pending,
            'payment_status'               => PaymentStatus::Unpaid,
            'tax_rate_snapshot'            => $org->tax_enabled ? (float) $org->tax_rate : 0.0,
            'service_charge_rate_snapshot' => $org->service_charge_enabled ? (float) $org->service_charge_rate : 0.0,
            'subtotal_amount'              => 0,
            'discount_amount'              => 0,
            'tax_amount'                   => 0,
            'service_charge_amount'        => 0,
            'total_amount'                 => 0,
            'payment_amount'               => 0,
            'change_amount'                => 0,
            'note'                         => $data['note'] ?? null,
            'opened_at'                    => $isOpenBill ? now() : null,
        ];
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->record instanceof Order && $this->record->order_type === OrderType::OpenBill
            ? 'Open Bill berhasil dibuat'
            : 'Order berhasil dibuat';
    }
}
