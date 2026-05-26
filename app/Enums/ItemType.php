<?php

declare(strict_types=1);

namespace App\Enums;

enum ItemType: string
{
    case Product = 'product';
    case Variant = 'variant';
    case Addon   = 'addon';
}
