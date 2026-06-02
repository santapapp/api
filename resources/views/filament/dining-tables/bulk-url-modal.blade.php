@php
    $urls = $records->map(function ($record) use ($baseUrl) {
        return "Meja {$record->name} ({$record->organization->name}):\n{$baseUrl}/o/{$record->organization->slug}/orders?table={$record->qr_token}";
    })->join("\n\n");
@endphp

<div class="bulk-url-container space-y-4" x-data="{
    copyAll() {
        const text = this.$refs.urlTextarea.value;
        navigator.clipboard.writeText(text).then(() => {
            new FilamentNotification()
                .title('Semua URL Disalin!')
                .success()
                .send();
        });
    }
}">
    <style>
        .bulk-url-container {
            font-family: system-ui, -apple-system, sans-serif;
            padding: 4px;
        }
        .bulk-url-desc {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 16px;
        }
        .dark .bulk-url-desc {
            color: #94a3b8;
        }
        .bulk-url-textarea {
            width: 100%;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            color: #0f172a;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 13px;
            padding: 16px;
            outline: none;
            resize: none;
            transition: all 0.15s ease;
        }
        .bulk-url-textarea:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
            background-color: #ffffff;
        }
        .dark .bulk-url-textarea {
            background-color: #0f172a;
            border-color: #334155;
            color: #f8fafc;
        }
        .dark .bulk-url-textarea:focus {
            border-color: #34d399;
            box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.15);
            background-color: #0f172a;
        }
        .button-row {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .dark .button-row {
            border-color: #334155;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.15s ease;
            border: 1px solid transparent;
            user-select: none;
            gap: 8px;
        }
        .btn-primary {
            background-color: #10b981;
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #059669;
        }
    </style>

    <p class="bulk-url-desc">Berikut adalah daftar URL untuk {{ $records->count() }} meja yang dipilih. Anda dapat menyalinnya untuk dibagikan.</p>

    <textarea x-ref="urlTextarea" readonly rows="10" class="bulk-url-textarea">{{ $urls }}</textarea>

    <div class="button-row">
        <button type="button" x-on:click="copyAll()" class="btn btn-primary">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
            Copy Semua
        </button>
    </div>
</div>
