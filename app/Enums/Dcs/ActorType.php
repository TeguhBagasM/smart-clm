<?php

namespace App\Enums\Dcs;

enum ActorType: string
{
    case User = 'user';
    case Guest = 'guest';
    case System = 'system';
}
