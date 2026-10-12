<?php

namespace App\Enums;

enum CgaStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
}
