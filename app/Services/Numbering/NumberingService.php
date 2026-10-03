<?php

namespace App\Services\Numbering;

use App\Enums\DocumentType;
use App\Exceptions\NumberingRangeExhaustedException;
use App\Models\Company;
use App\Models\NumberingResolution;
use Illuminate\Support\Facades\DB;

/**
 * Centralizes the (still unused) numbering logic so that whenever real
 * invoice emission is implemented, it can reserve consecutives atomically
 * instead of reading/incrementing current_consecutive directly.
 */
class NumberingService
{
    public function activeResolutionFor(Company $company, DocumentType|string $documentType): ?NumberingResolution
    {
        $type = $documentType instanceof DocumentType ? $documentType->value : $documentType;

        return NumberingResolution::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('document_type', $type)
            ->vigente()
            ->orderByDesc('valid_from')
            ->first();
    }

    /**
     * Atomically reserves and returns the next consecutive for the given
     * resolution. Not called anywhere yet — real invoice emission is a
     * later phase — but kept transaction-safe for when it is.
     */
    public function reserveNextNumber(NumberingResolution $resolution): int
    {
        return DB::transaction(function () use ($resolution): int {
            $locked = NumberingResolution::query()
                ->withoutGlobalScopes()
                ->where('id', $resolution->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->current_consecutive > $locked->range_to) {
                throw new NumberingRangeExhaustedException(
                    'El rango de numeración simulado se encuentra agotado.'
                );
            }

            $number = $locked->current_consecutive;
            $locked->current_consecutive = $number + 1;
            $locked->save();

            return $number;
        });
    }

    public function hasOverlappingActiveRange(
        int $companyId,
        string $documentType,
        int $rangeFrom,
        int $rangeTo,
        ?int $excludingId = null
    ): bool {
        return NumberingResolution::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('document_type', $documentType)
            ->where('is_active', true)
            ->when($excludingId !== null, fn ($query) => $query->where('id', '!=', $excludingId))
            ->where(function ($query) use ($rangeFrom, $rangeTo): void {
                $query->where('range_from', '<=', $rangeTo)
                    ->where('range_to', '>=', $rangeFrom);
            })
            ->exists();
    }

    public function isNearExpiry(NumberingResolution $resolution, int $days = 30): bool
    {
        return $resolution->isNearExpiry($days);
    }

    public function isNearExhaustion(NumberingResolution $resolution, float $thresholdPercent = 90.0): bool
    {
        return $resolution->isNearExhaustion($thresholdPercent);
    }
}
