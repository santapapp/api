<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Sidebar Navigation -->
        <div class="md:col-span-1 space-y-2">
            <div class="flex flex-col bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 space-y-1 shadow-sm">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-2 px-3">Daftar Dokumen</h3>
                @foreach ($this->getDocsList() as $key => $label)
                    <button
                        wire:click="setDoc('{{ $key }}')"
                        class="text-left px-3 py-2 text-sm rounded-lg transition-colors font-medium {{ $this->currentDoc === $key ? 'bg-primary-50 dark:bg-primary-950/30 text-primary-600 dark:text-primary-400' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Document Content -->
        <div class="md:col-span-3">
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-8 shadow-sm">
                <article class="prose dark:prose-invert max-w-none prose-headings:font-bold prose-a:text-primary-600 dark:prose-a:text-primary-400 prose-pre:bg-gray-50 dark:prose-pre:bg-gray-950">
                    {!! $this->getHtmlContent() !!}
                </article>
            </div>
        </div>
    </div>
</x-filament-panels::page>
