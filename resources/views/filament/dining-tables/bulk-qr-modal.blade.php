<div class="bulk-qr-container space-y-6"
     data-tables="{{ json_encode($records->map(function($record) use ($baseUrl) {
         return [
             'id' => $record->id,
             'name' => $record->name,
             'org' => $record->organization->name,
             'url' => "{$baseUrl}/o/{$record->organization->slug}/orders?table={$record->qr_token}"
         ];
     })) }}"
     x-data="{
         initAllQr() {
             const tables = JSON.parse(this.$el.dataset.tables);
             const render = () => {
                 if (window.SantapQR) {
                     tables.forEach(table => {
                         const canvasEl = document.getElementById('bulk-qr-' + table.id);
                         if (canvasEl) {
                             window.SantapQR.toCanvas(canvasEl, table.url, { width: 160, margin: 1 });
                         }
                     });
                 } else {
                     setTimeout(render, 100);
                 }
             };
             render();
         },
         printAll() {
             const tables = JSON.parse(this.$el.dataset.tables);
             let printContent = '<html><head><title>Bulk Print QR Meja</title><style>body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 20px; background: #ffffff; color: #111827; } .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; } .card { border: 1px dashed #cbd5e1; border-radius: 12px; padding: 16px; text-align: center; page-break-inside: avoid; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.02); } .card h3 { margin: 0 0 4px 0; font-size: 18px; font-weight: 700; } .card p { margin: 0 0 14px 0; font-size: 13px; color: #4b5563; } .card img { max-width: 100%; height: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 6px; } @media print { .card { border: 1px solid #000; } }</style></head><body><div class="grid">';

             tables.forEach(table => {
                 const canvasEl = document.getElementById('bulk-qr-' + table.id);
                 const dataUrl = canvasEl ? canvasEl.toDataURL('image/png') : '';
                 printContent += '<div class="card"><h3>Meja: ' + table.name + '</h3><p>' + table.org + '</p>';
                 if (dataUrl) {
                     printContent += '<img src=\'' + dataUrl + '\' />';
                 }
                 printContent += '</div>';
             });

             printContent += '</div></body></html>';

             const printWin = window.open('', '_blank');
             printWin.document.write(printContent);
             printWin.document.close();

             setTimeout(() => {
                 printWin.print();
                 printWin.close();
             }, 250);
         }
     }"
     x-init="initAllQr()">

    <style>
        .bulk-qr-container {
            font-family: system-ui, -apple-system, sans-serif;
            padding: 4px;
        }
        .bulk-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            max-height: 60vh;
            overflow-y: auto;
            padding: 16px;
            background-color: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        @media (min-width: 768px) {
            .bulk-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        @media (min-width: 1024px) {
            .bulk-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        .dark .bulk-grid {
            background-color: #0f172a;
            border-color: #334155;
        }
        .bulk-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: all 0.2s ease;
        }
        .bulk-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .dark .bulk-card {
            background-color: #1e293b;
            border-color: #334155;
        }
        .bulk-card-title {
            font-weight: 700;
            font-size: 15px;
            color: #0f172a;
            margin-bottom: 2px;
            text-align: center;
        }
        .dark .bulk-card-title {
            color: #f8fafc;
        }
        .bulk-card-subtitle {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 12px;
            text-align: center;
        }
        .dark .bulk-card-subtitle {
            color: #94a3b8;
        }
        .bulk-canvas {
            border-radius: 6px;
            display: block;
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

    <div class="bulk-grid">
        @foreach($records as $record)
            <div class="bulk-card">
                <div class="bulk-card-title">{{ $record->name }}</div>
                <div class="bulk-card-subtitle">{{ $record->organization->name }}</div>
                <canvas id="bulk-qr-{{ $record->id }}" class="bulk-canvas"></canvas>
            </div>
        @endforeach
    </div>

    <div class="button-row">
        <button type="button" x-on:click="printAll()" class="btn btn-primary">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Print Semua QR
        </button>
    </div>

</div>
