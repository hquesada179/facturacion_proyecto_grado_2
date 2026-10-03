<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\NumberingResolutionStatus;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\NumberingResolutionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A simulated DIAN numbering resolution. Nothing here represents a real
 * DIAN authorization — authorization_number_simulated and
 * simulated_technical_key are prototype placeholders only.
 */
class NumberingResolution extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<NumberingResolutionFactory> */
    use HasFactory;

    protected $fillable = [
        'document_type',
        'authorization_number_simulated',
        'prefix',
        'range_from',
        'range_to',
        'current_consecutive',
        'valid_from',
        'valid_until',
        'simulated_technical_key',
        'status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'status' => NumberingResolutionStatus::class,
            'range_from' => 'integer',
            'range_to' => 'integer',
            'current_consecutive' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $resolution): void {
            $resolution->status = $resolution->determineStatus();
        });
    }

    public function determineStatus(): NumberingResolutionStatus
    {
        if (! $this->is_active) {
            return NumberingResolutionStatus::Inactiva;
        }

        if ($this->current_consecutive > $this->range_to) {
            return NumberingResolutionStatus::Agotada;
        }

        if ($this->valid_until !== null && $this->valid_until->isPast()) {
            return NumberingResolutionStatus::Vencida;
        }

        return NumberingResolutionStatus::Vigente;
    }

    public function scopeVigente(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('is_active', true)
            ->whereDate('valid_from', '<=', $today)
            ->whereDate('valid_until', '>=', $today)
            ->whereColumn('current_consecutive', '<=', 'range_to');
    }

    public function totalRange(): int
    {
        return $this->range_to - $this->range_from + 1;
    }

    public function remaining(): int
    {
        return max(0, $this->range_to - $this->current_consecutive + 1);
    }

    public function consumedPercentage(): float
    {
        $total = $this->totalRange();

        if ($total <= 0) {
            return 0.0;
        }

        return (($total - $this->remaining()) / $total) * 100;
    }

    public function isNearExpiry(int $days = 30): bool
    {
        if ($this->valid_until === null || $this->valid_until->isPast()) {
            return false;
        }

        return now()->diffInDays($this->valid_until, false) <= $days;
    }

    public function isNearExhaustion(float $thresholdPercent = 90.0): bool
    {
        return $this->remaining() > 0 && $this->consumedPercentage() >= $thresholdPercent;
    }
}
