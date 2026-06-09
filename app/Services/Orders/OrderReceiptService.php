<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Order;
use App\Enums\PaymentStatus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderReceiptService
{
    /**
     * Cari order publik dan validasi hak akses serta status bayar.
     *
     * @param string $orderIdentifier Nomor order atau public token.
     * @return Order
     */
    public function getPaidOrder(string $orderIdentifier): Order
    {
        $order = Order::where('order_number', $orderIdentifier)
            ->orWhere('public_token', $orderIdentifier)
            ->first();

        if (!$order) {
            abort(404, 'Order tidak ditemukan.');
        }

        // Validasi: Harus sudah PAID
        if ($order->payment_status !== PaymentStatus::Paid) {
            abort(400, 'Struk pembayaran hanya dapat diunduh untuk pesanan yang sudah lunas.');
        }

        // Load relasi yang diperlukan secara efisien
        $order->load(['items.menu', 'diningTable.organization', 'organization']);

        return $order;
    }

    /**
     * Menyiapkan data receipt untuk dirender di view.
     *
     * @param Order $order
     * @return array
     */
    public function prepareReceiptData(Order $order): array
    {
        $org = $order->organization;

        // Convert logo to base64 for reliable rendering in Dompdf
        $logoBase64 = null;
        if ($org && $org->logo) {
            if (Str::startsWith($org->logo, ['http://', 'https://'])) {
                try {
                    $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($org->logo));
                } catch (\Throwable $e) {
                    // Fallback jika gagal fetch
                }
            } else {
                $logoPath = Storage::disk('public')->path($org->logo);
                if (file_exists($logoPath)) {
                    try {
                        $logoBase64 = 'data:image/' . pathinfo($logoPath, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($logoPath));
                    } catch (\Throwable $e) {
                        // Fallback jika gagal baca file
                    }
                }
            }
        }

        $timezone = $org->timezone ?? 'Asia/Jakarta';
        $orderDate = $order->created_at->timezone($timezone)->format('d M Y, H:i');
        $paidDate = $order->paid_at ? $order->paid_at->timezone($timezone)->format('d M Y, H:i') : null;

        return [
            'order' => $order,
            'organization' => $org,
            'logoBase64' => $logoBase64,
            'orderDate' => $orderDate,
            'paidDate' => $paidDate,
            'timezone' => $timezone,
        ];
    }

    /**
     * Render PDF receipt.
     *
     * @param Order $order
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generatePdf(Order $order)
    {
        $data = $this->prepareReceiptData($order);
        return Pdf::loadView('pdf.receipt', $data);
    }
}
