<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Struk Pembayaran #{{ $order->order_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1a1714;
            margin: 0;
            padding: 0;
            font-size: 13px;
            background-color: #ffffff;
        }
        .receipt-box {
            max-width: 600px;
            margin: auto;
            padding: 20px;
            border: 1px solid #e8e3dc;
            border-radius: 8px;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
        }
        .header img {
            max-height: 60px;
            margin-bottom: 10px;
        }
        .org-name {
            font-size: 20px;
            font-weight: bold;
            color: #1a1714;
            margin: 0 0 5px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .org-info {
            color: #6b635a;
            font-size: 11px;
            margin: 2px 0;
        }
        .divider {
            border-top: 1px dashed #c8c2ba;
            margin: 15px 0;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 20px;
            font-size: 12px;
        }
        .meta-table td {
            padding: 3px 0;
            vertical-align: top;
        }
        .meta-label {
            color: #9e9690;
            width: 35%;
        }
        .meta-value {
            color: #1a1714;
            font-weight: 500;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .items-table th {
            text-align: left;
            padding: 8px 5px;
            font-size: 11px;
            color: #9e9690;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e8e3dc;
        }
        .items-table td {
            padding: 10px 5px;
            border-bottom: 1px solid #f3efe9;
            vertical-align: top;
        }
        .item-name {
            font-weight: bold;
            color: #1a1714;
        }
        .item-options {
            font-size: 11px;
            color: #6b635a;
            margin-top: 3px;
            padding-left: 10px;
        }
        .item-note {
            font-size: 11px;
            color: #c07b2a;
            font-style: italic;
            margin-top: 3px;
        }
        .summary-table {
            width: 100%;
            margin-top: 20px;
            page-break-inside: avoid;
        }
        .summary-table td {
            padding: 4px 0;
            font-size: 12px;
        }
        .summary-label {
            text-align: right;
            color: #6b635a;
            padding-right: 15px;
            width: 70%;
        }
        .summary-value {
            text-align: right;
            color: #1a1714;
            font-weight: 500;
            width: 30%;
        }
        .grand-total-label {
            font-size: 15px;
            font-weight: bold;
            color: #1a1714;
            padding-top: 8px;
            border-top: 1.5px solid #e8e3dc;
        }
        .grand-total-value {
            font-size: 16px;
            font-weight: bold;
            color: #c45e0f;
            padding-top: 8px;
            border-top: 1.5px solid #e8e3dc;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            font-size: 11px;
            color: #9e9690;
            page-break-inside: avoid;
        }
        .footer-thankyou {
            font-size: 13px;
            font-weight: bold;
            color: #1a1714;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="receipt-box">
        <div class="header">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" alt="Logo">
            @endif
            <div class="org-name">{{ $organization->name }}</div>
            @if($organization->address)
                <div class="org-info">{{ $organization->address }}</div>
            @endif
            @if($organization->phone || $organization->email)
                <div class="org-info">
                    @if($organization->phone) Telp: {{ $organization->phone }} @endif
                    @if($organization->phone && $organization->email) | @endif
                    @if($organization->email) Email: {{ $organization->email }} @endif
                </div>
            @endif
        </div>

        <div class="divider"></div>

        <table class="meta-table">
            <tr>
                <td class="meta-label">Nomor Order</td>
                <td class="meta-value">: {{ $order->order_number }}</td>
                <td class="meta-label">Tipe Pesanan</td>
                <td class="meta-value">: {{ $order->order_type->value === 'open_bill' ? 'Open Bill' : 'Table Order' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Tanggal Order</td>
                <td class="meta-value">: {{ $orderDate }}</td>
                <td class="meta-label">Meja</td>
                <td class="meta-value">: {{ $order->diningTable->name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Waktu Pembayaran</td>
                <td class="meta-value">: {{ $paidDate ?? '-' }}</td>
                <td class="meta-label">Metode Pembayaran</td>
                <td class="meta-value">: {{ strtoupper($order->payment_method ?? 'QRIS') }}</td>
            </tr>
            <tr>
                <td class="meta-label">Status Pembayaran</td>
                <td class="meta-value">: LUNAS</td>
                @if($order->payment_reference)
                    <td class="meta-label">Referensi Bayar</td>
                    <td class="meta-value">: {{ $order->payment_reference }}</td>
                @else
                    <td colspan="2"></td>
                @endif
            </tr>
            @if($order->customer_name)
                <tr>
                    <td class="meta-label">Nama Pelanggan</td>
                    <td class="meta-value">: {{ $order->customer_name }}</td>
                    <td colspan="2"></td>
                </tr>
            @endif
        </table>

        <div class="divider"></div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th style="text-align: center; width: 10%;">Qty</th>
                    <th style="text-align: right; width: 20%;">Harga</th>
                    <th style="text-align: right; width: 25%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    @php
                        if ($item->isCancelled()) continue;
                    @endphp
                    <tr>
                        <td>
                            <div class="item-name">{{ $item->name }}</div>
                            @if(!empty($item->metadata['selected_options']))
                                <div class="item-options">
                                    @foreach($item->metadata['selected_options'] as $option)
                                        · {{ $option['option_name'] ?? '-' }}
                                        @if(($option['price_delta'] ?? 0) > 0)
                                            (+Rp {{ number_format(floatval($option['price_delta']), 0, ',', '.') }})
                                        @endif
                                        <br>
                                    @endforeach
                                </div>
                            @endif
                            @if($item->note)
                                <div class="item-note">Catatan: "{{ $item->note }}"</div>
                            @endif
                        </td>
                        <td style="text-align: center;">{{ $item->quantity }}</td>
                        <td style="text-align: right;">Rp {{ number_format(floatval($item->unit_price), 0, ',', '.') }}</td>
                        <td style="text-align: right;">Rp {{ number_format(floatval($item->subtotal), 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="summary-table">
            <tr>
                <td class="summary-label">Subtotal</td>
                <td class="summary-value">Rp {{ number_format(floatval($order->subtotal_amount), 0, ',', '.') }}</td>
            </tr>
            @if($order->discount_amount > 0)
                <tr>
                    <td class="summary-label">Diskon</td>
                    <td class="summary-value">-Rp {{ number_format(floatval($order->discount_amount), 0, ',', '.') }}</td>
                </tr>
            @endif
            @if($order->service_charge_amount > 0)
                <tr>
                    <td class="summary-label">Biaya Layanan ({{ floatval($order->service_charge_rate_snapshot) }}%)</td>
                    <td class="summary-value">Rp {{ number_format(floatval($order->service_charge_amount), 0, ',', '.') }}</td>
                </tr>
            @endif
            @if($order->tax_amount > 0)
                <tr>
                    <td class="summary-label">Pajak ({{ floatval($order->tax_rate_snapshot) }}%)</td>
                    <td class="summary-value">Rp {{ number_format(floatval($order->tax_amount), 0, ',', '.') }}</td>
                </tr>
            @endif
            <tr>
                <td class="summary-label grand-total-label">Total Pembayaran</td>
                <td class="summary-value grand-total-value">Rp {{ number_format(floatval($order->total_amount), 0, ',', '.') }}</td>
            </tr>
        </table>

        <div class="divider" style="margin-top: 30px;"></div>

        <div class="footer">
            <div class="footer-thankyou">Terima kasih atas kunjungan Anda!</div>
            <div style="margin-bottom: 5px;">Pembayaran diproses secara aman melalui Sekeco Payment</div>
            <div style="font-size: 9px; color: #a09080; margin-top: 15px;">
                Resi digital ini tercatat resmi pada sistem Santap.<br>
                Diunduh pada {{ now()->timezone($timezone)->format('d-m-Y H:i:s T') }}
            </div>
        </div>
    </div>
</body>
</html>
