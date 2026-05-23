<x-filament-panels::page>
    <div class="prose dark:prose-invert max-w-none bg-white dark:bg-gray-900 p-8 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        {!! Str::markdown($this->docsContent) !!}
    </div>
</x-filament-panels::page>
