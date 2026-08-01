<?php

namespace App\Enums;

enum UsageEventType: string
{
    case UserAuthenticated = 'user.authenticated';
    case ConsoleLogin = 'console.login';
    case UserCreated = 'user.created';
    case TokenIssued = 'token.issued';
}
