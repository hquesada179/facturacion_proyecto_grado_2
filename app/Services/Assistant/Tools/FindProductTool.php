<?php

namespace App\Services\Assistant\Tools;

use App\Models\ProductService;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class FindProductTool
{
    private const MAX_LIMIT = 10;

    /**
     * @param  array<string, mixed>  $filters  Optional keys: status,
     *   tax_rate, tax_code.
     */
    public function handle(User $user, ?string $term, array $filters = [], int $limit = 10): array
    {
        $term = trim((string) $term);
        $limit = max(1, min($limit, self::MAX_LIMIT));

        $query = ProductService::withoutGlobalScopes()
            ->with('taxes')
            ->where('company_id', $user->company_id)
            ->orderBy('name');

        if ($term !== '') {
            $query->where(function ($query) use ($term): void {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhere('sku', 'like', '%'.$term.'%')
                    ->orWhere('description', 'like', '%'.$term.'%');
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['tax_rate'])) {
            $rate = $filters['tax_rate'];
            $query->whereHas('taxes', fn ($q) => $q->where('rate', $rate));
        }

        if (! empty($filters['tax_code'])) {
            $code = $filters['tax_code'];
            $query->whereHas('taxes', fn ($q) => $q->where('code', 'like', '%'.$code.'%'));
        }

        $totalMatches = (clone $query)->count();

        $matches = $query->limit($limit)->get()
            ->filter(fn (ProductService $product): bool => Gate::forUser($user)->allows('view', $product))
            ->map(fn (ProductService $product): array => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'price' => (float) $product->price,
                'status' => $product->status->label(),
                'taxes' => $product->taxes->map(fn ($tax): string => $tax->code.' '.$tax->rate.'%')->implode(', ') ?: 'Sin impuestos asociados',
            ])
            ->values()
            ->all();

        return [
            'tool' => 'find_product',
            'total_matches' => $totalMatches,
            'matches' => $matches,
        ];
    }
}
