<?php

declare(strict_types=1);

namespace App\Enums;

enum MemberStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Suspended = 'suspended';
    case Left = 'left';
}
