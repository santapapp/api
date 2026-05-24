<?php

declare(strict_types=1);

namespace App\Enums;

enum MenuType: string
{
    case Product = 'product';
    case VariantGroup = 'variant_group';
    case Variant = 'variant';
    case AddonGroup = 'addon_group';
    case Addon = 'addon';
}
