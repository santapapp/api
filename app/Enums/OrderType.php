<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderType: string
{
    case TableOrder = 'table_order';
    case CashierOrder = 'cashier_order';
    case OpenBill = 'open_bill';
}
