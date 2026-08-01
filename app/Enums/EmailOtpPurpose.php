<?php

namespace App\Enums;

enum EmailOtpPurpose: string
{
    case Login = 'login';
    case VerifyEmail = 'verify_email';
}
