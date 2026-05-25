<div class="space-y-4">
    @if ($record->items->isEmpty())
        <p class="text-gray-500">No items found for this order.</p>
    @else
        <div class="divide-y divide-gray-200 dark:divide-white/10 border-b border-t border-gray-200 dark:border-white/10">
            @foreach ($record->items as $item)
                <div class="py-3 flex justify-between items-center">
                    <div>
                        <div class="font-medium text-gray-900 dark:text-white">
                            {{ $item->quantity }}x {{ $item->name }}
                        </div>
                        @if ($item->note)
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                Note: {{ $item->note }}
                            </div>
                        @endif
                    </div>
                    <div class="font-medium text-gray-900 dark:text-white">
                        Rp {{ number_format($item->price * $item->quantity, 0, ',', '.') }}
                    </div>
                </div>
            @endforeach
        </div>
        <div class="flex justify-between items-center pt-4">
            <div class="font-bold text-gray-900 dark:text-white">Total</div>
            <div class="font-bold text-lg text-gray-900 dark:text-white">
                Rp {{ number_format($record->total_amount, 0, ',', '.') }}
            </div>
        </div>
    @endif
</div>
