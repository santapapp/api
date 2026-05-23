<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\BillStatus;
use App\Events\CashierCalled;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerCallCashierController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $session = $request->attributes->get('customer_session');
        if (!$session) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $bill = $session->bill;
        if (!$bill || $bill->status !== BillStatus::Open) {
            return response()->json(['message' => 'Tidak ada tagihan aktif untuk meja ini.'], 400);
        }

        broadcast(new CashierCalled($session->diningTable, $session->organization_id));

        return response()->json([
            'message' => 'Kasir telah dipanggil.',
        ]);
    }
}
