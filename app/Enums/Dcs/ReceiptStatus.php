<?php

namespace App\Enums\Dcs;

enum ReceiptStatus: string
{
    case Pending = 'pending';
    case Read = 'read';
    case Acknowledged = 'acknowledged';
}
