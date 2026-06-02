@php
    /** @var \App\Models\Order $order */
    /** @var array{paid: bool, status: string, transaction_status: ?string, raw: array} $result */
    $data = data_get($result, 'raw.data', []);
    $isError = in_array($result['status'], ['not_found', 'error'], true);

    $statusColor = match ($result['status']) {
        'paid'                          => 'text-green-600 bg-green-50 ring-green-600/20',
        'pending'                       => 'text-amber-600 bg-amber-50 ring-amber-600/20',
        'expired', 'cancelled', 'denied' => 'text-red-600 bg-red-50 ring-red-600/20',
        default                         => 'text-gray-600 bg-gray-50 ring-gray-500/20',
    };

    $rows = [
        'Status Transaksi' => data_get($data, 'transaction_status'),
        'Transaction ID'   => data_get($data, 'transaction_id'),
        'Order ID / Ref'   => data_get($data, 'order_id', $order->payment_reference),
        'Tipe Pembayaran'  => data_get($data, 'payment_type'),
        'Acquirer'         => data_get($data, 'acquirer'),
        'Jumlah'           => data_get($data, 'gross_amount') ? 'Rp ' . number_format((float) data_get($data, 'gross_amount'), 0, ',', '.') : null,
        'Waktu Transaksi'  => data_get($data, 'transaction_time'),
        'Kedaluwarsa'      => data_get($data, 'expiry_time'),
        'Fraud Status'     => data_get($data, 'fraud_status'),
        'Merchant ID'      => data_get($data, 'merchant_id'),
    ];
@endphp

<div class="space-y-4 text-sm">
    {{-- Ringkasan status --}}
    <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 dark:border-white/10 p-4">
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Status internal</p>
            <span @class([
                'inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold ring-1 ring-inset mt-1',
                $statusColor,
            ])>
                {{ strtoupper($result['status']) }}
            </span>
        </div>
        <div class="text-right">
            <p class="text-xs text-gray-500 dark:text-gray-400">Referensi</p>
            <p class="font-mono text-xs mt-1 text-gray-700 dark:text-gray-200">{{ $order->payment_reference }}</p>
        </div>
    </div>

    @if ($isError)
        <div class="rounded-xl bg-amber-50 dark:bg-amber-500/10 p-4 text-amber-700 dark:text-amber-400">
            <p class="font-semibold">Data pembayaran tidak tersedia</p>
            <p class="text-xs mt-1">
                {{ data_get($result, 'raw.error') ?? data_get($result, 'raw.message') ?? 'Transaksi belum ditemukan di provider atau provider sedang tidak dapat dihubungi.' }}
            </p>
        </div>
    @else
        {{-- Detail dari Midtrans --}}
        <dl class="divide-y divide-gray-100 dark:divide-white/5 rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
            @foreach ($rows as $label => $value)
                @if (filled($value))
                    <div class="flex items-start justify-between gap-4 px-4 py-2.5">
                        <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="text-right font-medium text-gray-800 dark:text-gray-100 break-all">{{ $value }}</dd>
                    </div>
                @endif
            @endforeach
        </dl>

        @if ($qrString = data_get($data, 'qr_string'))
            <div class="rounded-xl border border-gray-200 dark:border-white/10 p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">QR String</p>
                <p class="font-mono text-[11px] leading-relaxed break-all text-gray-600 dark:text-gray-300">{{ $qrString }}</p>
            </div>
        @endif
    @endif

    <p class="text-[11px] text-gray-400 dark:text-gray-500">
        Data ditarik langsung dari Sekeco / Midtrans saat tombol ini diklik — bukan dari database lokal.
    </p>
</div>
