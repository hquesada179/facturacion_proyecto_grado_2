<?php

namespace App\Services\Invoices\Validation;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

final class ValidationResultCollection implements Countable, IteratorAggregate, JsonSerializable
{
    /** @param ValidationResult[] $results */
    public function __construct(private readonly array $results = []) {}

    public function hasBlocking(): bool
    {
        return $this->blockingCount() > 0;
    }

    public function blockingCount(): int
    {
        return count(array_filter(
            $this->results,
            static fn (ValidationResult $result): bool => $result->severidad === ValidationSeverity::Bloqueo
        ));
    }

    public function warningCount(): int
    {
        return count(array_filter(
            $this->results,
            static fn (ValidationResult $result): bool => $result->severidad === ValidationSeverity::Advertencia
        ));
    }

    /** @return ValidationResult[] */
    public function all(): array
    {
        return $this->results;
    }

    public function count(): int
    {
        return count($this->results);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->results);
    }

    public function toArray(): array
    {
        return [
            'ok' => ! $this->hasBlocking(),
            'blocking_count' => $this->blockingCount(),
            'warning_count' => $this->warningCount(),
            'results' => array_map(static fn (ValidationResult $result): array => $result->toArray(), $this->results),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
