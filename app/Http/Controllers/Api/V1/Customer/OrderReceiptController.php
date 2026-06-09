<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Services\Orders\OrderReceiptService;
use Illuminate\Http\Request;

/**
 * @tags Customer Web
 */
class OrderReceiptController extends Controller
{
    protected OrderReceiptService $receiptService;

    public function __construct(OrderReceiptService $receiptService)
    {
        $this->receiptService = $receiptService;
    }

    /**
     * Download Struk Pembayaran PDF.
     *
     * Menghasilkan file PDF struk/kwitansi pembayaran resmi dari pesanan yang sudah lunas.
     * Struk berisi detail pesanan, data pembayaran, informasi meja, dan branding organisasi.
     *
     * @param Request $request
     * @param string $order Nomor order atau public token.
     * @return \Illuminate\Http\Response
     */
    public function download(Request $request, string $order)
    {
        // Mendapatkan order yang sudah paid (akan melempar abort 404/400 jika tidak valid)
        $paidOrder = $this->receiptService->getPaidOrder($order);

        // Menghasilkan file PDF
        $pdf = $this->receiptService->generatePdf($paidOrder);

        $orgSlug = $paidOrder->organization->slug ?? 'santap';
        $filename = "receipt-{$orgSlug}-{$paidOrder->order_number}.pdf";

        return $pdf->download($filename);
    }
}
