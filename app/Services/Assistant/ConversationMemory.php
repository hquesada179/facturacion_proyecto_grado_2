<?php

namespace App\Services\Assistant;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Small structured snapshot of "what was this conversation just talking
 * about" — used only to resolve conversational references such as "esa
 * factura", "cuáles están anuladas", or "el más caro". This is never the
 * raw conversation text and never anything secret: just a handful of IDs
 * and filter values, capped and short-lived (see isStale()). Persisted as
 * JSON on assistant_conversations.conversation_memory — scoped to that
 * one conversation row, so a brand-new conversation (a new row) never
 * sees another conversation's memory.
 */
final class ConversationMemory
{
    private const TTL_MINUTES = 30;

    private const MAX_RESULT_IDS = 10;

    public function __construct(
        public readonly ?string $entityType = null,
        public readonly ?int $entityId = null,
        public readonly ?string $entityNumber = null,
        public readonly ?int $customerId = null,
        public readonly ?string $customerName = null,
        public readonly ?string $queryType = null,
        public readonly array $filters = [],
        public readonly array $resultIds = [],
        public readonly ?string $timeRangeKey = null,
        public readonly ?string $recordedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromArray(?array $data): self
    {
        if ($data === null) {
            return new self;
        }

        return new self(
            entityType: $data['entity_type'] ?? null,
            entityId: $data['entity_id'] ?? null,
            entityNumber: $data['entity_number'] ?? null,
            customerId: $data['customer_id'] ?? null,
            customerName: $data['customer_name'] ?? null,
            queryType: $data['query_type'] ?? null,
            filters: $data['filters'] ?? [],
            resultIds: $data['result_ids'] ?? [],
            timeRangeKey: $data['time_range'] ?? null,
            recordedAt: $data['recorded_at'] ?? null,
        );
    }

    /**
     * @param  array<int, int>  $resultIds
     * @param  array<string, mixed>  $filters
     */
    public static function record(
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $entityNumber = null,
        ?int $customerId = null,
        ?string $customerName = null,
        ?string $queryType = null,
        array $filters = [],
        array $resultIds = [],
        ?string $timeRangeKey = null,
    ): self {
        return new self(
            entityType: $entityType,
            entityId: $entityId,
            entityNumber: $entityNumber,
            customerId: $customerId,
            customerName: $customerName,
            queryType: $queryType,
            filters: $filters,
            resultIds: array_slice(array_values($resultIds), 0, self::MAX_RESULT_IDS),
            timeRangeKey: $timeRangeKey,
            recordedAt: CarbonImmutable::now()->toIso8601String(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'entity_number' => $this->entityNumber,
            'customer_id' => $this->customerId,
            'customer_name' => $this->customerName,
            'query_type' => $this->queryType,
            'filters' => $this->filters,
            'result_ids' => $this->resultIds,
            'time_range' => $this->timeRangeKey,
            'recorded_at' => $this->recordedAt,
        ];
    }

    public function isStale(): bool
    {
        if ($this->recordedAt === null) {
            return true;
        }

        try {
            return CarbonImmutable::parse($this->recordedAt)->diffInMinutes(CarbonImmutable::now(), true) > self::TTL_MINUTES;
        } catch (Throwable) {
            return true;
        }
    }

    public function isEmpty(): bool
    {
        return $this->entityType === null && $this->customerId === null && $this->queryType === null;
    }

    public function hasFreshCustomer(): bool
    {
        return ! $this->isStale() && $this->customerId !== null;
    }

    public function hasFreshEntity(string $type): bool
    {
        return ! $this->isStale() && $this->entityType === $type && $this->entityId !== null;
    }

    /**
     * @return array<int, int>
     */
    public function freshResultIds(string $type): array
    {
        return (! $this->isStale() && $this->entityType === $type) ? $this->resultIds : [];
    }
}
