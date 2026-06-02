<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\QrisService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Menguji pemetaan status QRIS (Sekeco/Midtrans) → status internal.
 *
 * Tanpa jaringan: Http::fake mensimulasikan response provider. Fokus pada
 * QrisService::check() — sumber kebenaran mapping pembayaran.
 */
class QrisServiceTest extends TestCase
{
    private function fakeCheck(array $body, int $status = 200): void
    {
        Http::fake([
            'qris.sekeco.id/check*' => Http::response($body, $status),
        ]);
    }

    /**
     * @param array<string, mixed> $data  Isi data.* dari provider.
     */
    private function checkWith(array $data, int $status = 200): array
    {
        $this->fakeCheck(['ok' => true, 'message' => 'status transaksi', 'data' => $data], $status);

        return app(QrisService::class)->check('santap-1');
    }

    public function test_settlement_dianggap_paid(): void
    {
        $result = $this->checkWith(['transaction_status' => 'settlement', 'fraud_status' => 'accept']);

        $this->assertTrue($result['paid']);
        $this->assertSame('paid', $result['status']);
        $this->assertSame('settlement', $result['transaction_status']);
    }

    public function test_capture_dianggap_paid(): void
    {
        $result = $this->checkWith(['transaction_status' => 'capture', 'fraud_status' => 'accept']);

        $this->assertTrue($result['paid']);
        $this->assertSame('paid', $result['status']);
    }

    public function test_settlement_dengan_fraud_deny_tidak_paid(): void
    {
        $result = $this->checkWith(['transaction_status' => 'settlement', 'fraud_status' => 'deny']);

        $this->assertFalse($result['paid']);
        $this->assertSame('denied', $result['status']);
    }

    public function test_pending_tetap_pending(): void
    {
        $result = $this->checkWith(['transaction_status' => 'pending', 'fraud_status' => 'accept']);

        $this->assertFalse($result['paid']);
        $this->assertSame('pending', $result['status']);
    }

    public function test_expire_dipetakan_expired(): void
    {
        $result = $this->checkWith(['transaction_status' => 'expire']);

        $this->assertFalse($result['paid']);
        $this->assertSame('expired', $result['status']);
    }

    public function test_cancel_dipetakan_cancelled(): void
    {
        $result = $this->checkWith(['transaction_status' => 'cancel']);

        $this->assertFalse($result['paid']);
        $this->assertSame('cancelled', $result['status']);
    }

    public function test_deny_dipetakan_denied(): void
    {
        $result = $this->checkWith(['transaction_status' => 'deny']);

        $this->assertFalse($result['paid']);
        $this->assertSame('denied', $result['status']);
    }

    public function test_refund_dipetakan_refunded(): void
    {
        $result = $this->checkWith(['transaction_status' => 'refund']);

        $this->assertFalse($result['paid']);
        $this->assertSame('refunded', $result['status']);
    }

    public function test_status_tak_dikenal_default_pending(): void
    {
        $result = $this->checkWith(['transaction_status' => 'authorize']);

        $this->assertFalse($result['paid']);
        $this->assertSame('pending', $result['status']);
    }

    public function test_provider_error_500_tidak_throw_dan_not_found(): void
    {
        // Mensimulasikan "Transaction doesn't exist." (HTTP 500, ok:false).
        $this->fakeCheck([
            'ok'      => false,
            'message' => 'midtrans error',
            'error'   => "Midtrans API is returning API error. HTTP status code: 404 ... Transaction doesn't exist.",
        ], 500);

        $result = app(QrisService::class)->check('santap-tidak-ada');

        $this->assertFalse($result['paid']);
        $this->assertSame('not_found', $result['status']);
        $this->assertNull($result['transaction_status']);
    }

    public function test_ok_false_dengan_http_200_tetap_not_found(): void
    {
        $this->fakeCheck(['ok' => false, 'message' => 'error'], 200);

        $result = app(QrisService::class)->check('santap-1');

        $this->assertFalse($result['paid']);
        $this->assertSame('not_found', $result['status']);
    }
}
