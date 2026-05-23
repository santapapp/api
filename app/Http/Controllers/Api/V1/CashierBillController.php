<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\BillStatus;
use App\Models\OpenBill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashierBillController extends Controller
{
    public function index(): JsonResponse
    {
        $context = app(\App\Services\OrganizationContext::class);
        
        $bills = OpenBill::with(['table', 'orders.items', 'sessions'])
            ->where('organization_id', $context->getOrganizationId())
            ->where('status', BillStatus::Open)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $bills,
        ]);
    }
}
