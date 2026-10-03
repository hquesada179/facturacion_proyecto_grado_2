<?php

namespace App\Services\Invoices\Validation;

enum ValidationSeverity: string
{
    case Bloqueo = 'bloqueo';
    case Advertencia = 'advertencia';
}
