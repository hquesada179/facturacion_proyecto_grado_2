<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ProductServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductService extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<ProductServiceFactory> */
    use HasFactory;

    /**
     * tax_id (singular) is kept only for historical rows created before
     * the product <-> taxes many-to-many pivot existed. New code should
     * read/write taxes() instead.
     */
    protected $fillable = [
        'tax_id',
        'sku',
        'name',
        'description',
        'type',
        'unit',
        'price',
        'status',
        'tax_included',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'tax_included' => 'boolean',
            'status' => ProductStatus::class,
        ];
    }

    /** @deprecated Kept for historical data; use taxes() for new code. */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function taxes(): BelongsToMany
    {
        return $this->belongsToMany(Tax::class, 'product_service_tax');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function scopeAvailableForInvoicing(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Active->value);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopeType(Builder $query, ?string $type): Builder
    {
        return $type ? $query->where('type', $type) : $query;
    }
}
