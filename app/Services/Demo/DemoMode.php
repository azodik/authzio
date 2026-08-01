<?php

namespace App\Services\Demo;

enum DemoMode: string
{
    case Allow = 'allow';
    case Soft = 'soft';
    case Deny = 'deny';
}
