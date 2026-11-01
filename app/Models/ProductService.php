<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Models\Concerns\BelongsToCompany;
use Brick\Math\BigDecimal;
use Database\Factories\ProductServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class ProductService extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<ProductServiceFactory> */
    use HasFactory;

    /**
     * Prototype-scale list, not the official DIAN unit catalog. Existing
     * rows may hold a value outside this list (free text predates this
     * constant) — forms must keep showing that value as an extra option
     * rather than silently losing it.
     */
    public const UNITS = [
        'unidad' => 'Unidad',
        'servicio' => 'Servicio',
        'hora' => 'Hora',
        'dia' => 'Día',
        'kilogramo' => 'Kilogramo',
        'gramo' => 'Gramo',
        'litro' => 'Litro',
        'metro' => 'Metro',
        'caja' => 'Caja',
        'paquete' => 'Paquete',
    ];

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
        'category',
        'type',
        'unit',
        'price',
        'status',
        'tax_included',
        'is_inventoried',
        'stock_quantity',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'tax_included' => 'boolean',
            'is_inventoried' => 'boolean',
            'stock_quantity' => 'decimal:2',
            'status' => ProductStatus::class,
        ];
    }

    /**
     * Only a product (never a service) can be inventory-controlled —
     * tracking "stock" of a service has no real meaning.
     */
    public function canBeInventoried(): bool
    {
        return $this->type === 'product';
    }

    /**
     * NOT wired into invoice issuance or credit-note reversal yet (Fase
     * 3 intentionally stops here — see the Fase 3 report for why). Safe
     * to call directly for manual stock adjustments: guards against a
     * negative resulting balance the same way credit notes already
     * guard against over-crediting.
     */
    public function decrementStock(string $quantity): void
    {
        $remaining = BigDecimal::of((string) $this->stock_quantity)->minus(BigDecimal::of($quantity));

        if ($remaining->isNegative()) {
            throw new InvalidArgumentException('No hay suficiente existencia disponible para descontar esa cantidad.');
        }

        $this->stock_quantity = (string) $remaining;
    }

    public function incrementStock(string $quantity): void
    {
        $this->stock_quantity = (string) BigDecimal::of((string) $this->stock_quantity)->plus(BigDecimal::of($quantity));
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

    public function scopeCategory(Builder $query, ?string $category): Builder
    {
        return $category ? $query->where('category', $category) : $query;
    }
}
