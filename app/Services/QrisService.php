<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

class QrisService
{
    protected string $baseUrl = 'https://qris.sekeco.id';

    /**
     * Buat QRIS payment.
     *
     * @return array{qr_url: string, order_id: string}
     */
    public function create(string $orderId, float $grossAmount): array
    {
        $response = Http::post("{$this->baseUrl}/create", [
            'order_id' => $orderId,
            'gross_amount' => (int) $grossAmount,
        ]);

        $response->throw();

        return $response->json();
    }

    /**
     * Cek status payment.
     *
     * @return array{status: string}
     */
    public function check(string $orderId): array
    {
        $response = Http::get("{$this->baseUrl}/check", [
            'id' => $orderId,
        ]);

        $response->throw();

        return $response->json();
    }

    /**
     * Cancel QRIS payment.
     */
    public function cancel(string $orderId): array
    {
        $response = Http::delete("{$this->baseUrl}/cancel", [
            'id' => $orderId,
        ]);

        $response->throw();

        return $response->json();
    }
}
