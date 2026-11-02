<?php

namespace App\Enums;

enum OtpVerificationResult
{
    case Success;
    case Invalid;
    case Expired;
    case TooManyAttempts;
}
