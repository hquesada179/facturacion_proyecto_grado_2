<?php

namespace App\Services\Invoices;

final readonly class DianSimulationResult
{
    public function __construct(
        public string $status,
        public string $message,
    ) {}
}
