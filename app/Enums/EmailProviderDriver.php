<?php

namespace App\Enums;

enum EmailProviderDriver: string
{
    case Smtp = 'smtp';
    case Resend = 'resend';
    case Postmark = 'postmark';
    case Ses = 'ses';
    case Mailgun = 'mailgun';
}
