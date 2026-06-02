<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien QRIS via bridge Sekeco (proxy Midtrans).
 *
 * Catatan penting soal response /check:
 * Status pembayaran sebenarnya ada di `data.transaction_status` dengan nilai
 * Midtrans (pending, settlement, capture, expire, cancel, deny, refund) —
 * BUKAN di field top-level `status`. Lunas = settlement|capture (fraud accept).
 */
class QrisService
{
    /** Nilai transaction_status Midtrans yang dianggap LUNAS. */
    private const PAID_TX_STATUSES = ['settlement', 'capture'];

    protected function baseUrl(): string
    {
        return rtrim((string) config('santap.qris.base_url', 'https://qris.sekeco.id'), '/');
    }

    /**
     * Buat QRIS payment. Melempar exception bila gagal (agar checkout rollback).
     */
    public function create(string $orderId, float $grossAmount): array
    {
        $response = Http::post("{$this->baseUrl()}/create", [
            'order_id'     => $orderId,
            'gross_amount' => (int) $grossAmount,
        ]);

        $response->throw();

        return $response->json();
    }

    /**
     * Cek status payment ke provider.
     *
     * TIDAK melempar exception: saat provider error / transaksi belum ada
     * (HTTP 500 "Transaction doesn't exist."), kembalikan status ternormalisasi
     * agar caller tetap bisa memakai status DB terkini sebagai fallback.
     *
     * @return array{paid: bool, status: string, transaction_status: ?string, raw: array}
     *               status: paid|pending|expired|cancelled|denied|refunded|not_found|error
     */
    public function check(string $orderId): array
    {
        try {
            $response = Http::get("{$this->baseUrl()}/check", [
                'id' => $orderId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('QrisService::check koneksi gagal', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);

            return $this->result('error', null, []);
        }

        $body = $response->json() ?? [];

        // Provider non-ok (mis. HTTP 500 saat transaksi belum/ tidak ada).
        if (! $response->successful() || ($body['ok'] ?? false) !== true) {
            Log::info('QrisService::check provider non-ok', [
                'order_id' => $orderId,
                'http'     => $response->status(),
                'body'     => $body,
            ]);

            return $this->result('not_found', null, $body);
        }

        $txStatus = $body['data']['transaction_status'] ?? null;
        $fraud    = $body['data']['fraud_status'] ?? null;

        return $this->result($this->normalize($txStatus, $fraud), $txStatus, $body);
    }

    /**
     * Cancel QRIS payment. Melempar exception bila gagal.
     */
    public function cancel(string $orderId): array
    {
        $response = Http::delete("{$this->baseUrl()}/cancel", [
            'id' => $orderId,
        ]);

        $response->throw();

        return $response->json();
    }

    /**
     * Petakan transaction_status Midtrans → status internal.
     */
    private function normalize(?string $txStatus, ?string $fraud): string
    {
        return match ($txStatus) {
            'settlement', 'capture'                  => $fraud === 'deny' ? 'denied' : 'paid',
            'expire'                                 => 'expired',
            'cancel'                                 => 'cancelled',
            'deny'                                   => 'denied',
            'refund', 'partial_refund', 'chargeback' => 'refunded',
            // pending atau nilai tak dikenal → perlakukan sebagai pending (aman).
            default                                  => 'pending',
        };
    }

    /**
     * @return array{paid: bool, status: string, transaction_status: ?string, raw: array}
     */
    private function result(string $status, ?string $txStatus, array $raw): array
    {
        return [
            'paid'               => $status === 'paid',
            'status'             => $status,
            'transaction_status' => $txStatus,
            'raw'                => $raw,
        ];
    }
}
