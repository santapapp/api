<div class="qr-modal-container space-y-6"
     data-qr-url="{{ $qrUrl }}"
     data-table-name="{{ $record->name }}"
     data-org-name="{{ $record->organization->name }}"
     x-data="{
         initQr() {
             const url = this.$el.dataset.qrUrl;
             if (window.SantapQR) {
                 window.SantapQR.toCanvas(this.$refs.qrcanvas, url, { width: 240, margin: 2 });
             } else {
                 const render = () => {
                     if (window.SantapQR) {
                         window.SantapQR.toCanvas(this.$refs.qrcanvas, url, { width: 240, margin: 2 });
                     } else {
                         setTimeout(render, 100);
                     }
                 };
                 render();
             }
         },
         copyUrl() {
             const url = this.$el.dataset.qrUrl;
             navigator.clipboard.writeText(url).then(() => {
                 new FilamentNotification()
                     .title('URL Disalin!')
                     .success()
                     .send();
             });
         },
         downloadPng() {
             const name = this.$el.dataset.tableName;
             const dataUrl = this.$refs.qrcanvas.toDataURL('image/png');
             const link = document.createElement('a');
             link.download = 'QR_' + name + '.png';
             link.href = dataUrl;
             link.click();
         },
         printQr() {
             const name = this.$el.dataset.tableName;
             const org = this.$el.dataset.orgName;
             const dataUrl = this.$refs.qrcanvas.toDataURL('image/png');
             const printWin = window.open('', '_blank');
             printWin.document.write('<html><head><title>Print QR - ' + name + '</title><style>body { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; font-family: system-ui, -apple-system, sans-serif; margin: 0; background: #ffffff; color: #111827; } h2 { margin: 0 0 6px 0; font-size: 26px; font-weight: 700; } p { margin: 0 0 24px 0; font-size: 16px; color: #4b5563; } img { max-width: 280px; height: auto; border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }</style></head><body><h2>Meja: ' + name + '</h2><p>' + org + '</p><img src=\'' + dataUrl + '\' onload=\'window.print(); window.close();\' /></body></html>');
             printWin.document.close();
         }
     }"
     x-init="initQr()">

    <style>
        .qr-modal-container {
            font-family: system-ui, -apple-system, sans-serif;
            padding: 4px;
        }
        .qr-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.025);
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 24px;
            width: fit-content;
            margin-left: auto;
            margin-right: auto;
        }
        .dark .qr-card {
            background: #1e293b;
            border-color: #334155;
        }
        .qr-canvas-el {
            border-radius: 8px;
            display: block;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #475569;
        }
        .dark .form-label {
            color: #94a3b8;
        }
        .input-group {
            display: flex;
            gap: 8px;
        }
        .input-field {
            flex: 1;
            padding: 10px 14px;
            font-size: 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background-color: #f8fafc;
            color: #0f172a;
            outline: none;
            transition: all 0.15s ease;
        }
        .input-field:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
            background-color: #ffffff;
        }
        .dark .input-field {
            background-color: #0f172a;
            border-color: #334155;
            color: #f8fafc;
        }
        .dark .input-field:focus {
            border-color: #34d399;
            box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.15);
            background-color: #0f172a;
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
        }
        .btn-primary {
            background-color: #10b981;
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #059669;
        }
        .btn-secondary {
            background-color: #ffffff;
            border-color: #cbd5e1;
            color: #334155;
        }
        .btn-secondary:hover {
            background-color: #f8fafc;
            border-color: #94a3b8;
        }
        .dark .btn-secondary {
            background-color: #1e293b;
            border-color: #334155;
            color: #cbd5e1;
        }
        .dark .btn-secondary:hover {
            background-color: #334155;
            border-color: #475569;
        }
        .button-row {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .dark .button-row {
            border-color: #334155;
        }
    </style>

    <div class="qr-card">
        <!-- Canvas tempat QR Code akan dirender -->
        <canvas x-ref="qrcanvas" class="qr-canvas-el"></canvas>
    </div>

    <div class="form-group">
        <label class="form-label">URL Order Meja</label>
        <div class="input-group">
            <input type="text" readonly value="{{ $qrUrl }}" class="input-field">
            <button type="button" x-on:click="copyUrl()" class="btn btn-primary">
                Copy
            </button>
        </div>
    </div>

    <div class="button-row">
        <button type="button" x-on:click="downloadPng()" class="btn btn-secondary">
            Download PNG
        </button>
        <button type="button" x-on:click="printQr()" class="btn btn-secondary">
            Print QR
        </button>
    </div>

</div>
