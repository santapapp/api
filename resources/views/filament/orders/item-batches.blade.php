@php
    $batches = \App\Support\Orders\OrderItemBatchSummary::fromItems(
        $record->items,
        latestFirst: true,
        includeItems: true,
    );
@endphp

<div class="space-y-4">
    @forelse ($batches as $batch)
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                <div>
                    <div class="font-semibold text-gray-950 dark:text-white">
                        {{ $batch['label'] }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $batch['submitted_at'] ? \Illuminate\Support\Carbon::parse($batch['submitted_at'])->timezone(config('app.timezone'))->format('d M Y H:i') : '-' }}
                    </div>
                </div>
                <div class="text-right text-sm text-gray-600 dark:text-gray-300">
                    <div>{{ ucfirst($batch['status']) }}</div>
                    <div>Rp {{ number_format((float) $batch['total_amount'], 0, ',', '.') }}</div>
                </div>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($batch['_items'] as $item)
                    <div class="flex items-start justify-between gap-4 py-2">
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white">
                                {{ $item->quantity }}x {{ $item->name }}
                            </div>
                            @if ($item->note)
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $item->note }}
                                </div>
                            @endif
                        </div>
                        <div class="text-right text-sm text-gray-700 dark:text-gray-300">
                            <div>{{ $item->item_status?->getLabel() ?? '-' }}</div>
                            <div>Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="text-sm text-gray-500 dark:text-gray-400">
            Belum ada item pesanan.
        </div>
    @endforelse
</div>
