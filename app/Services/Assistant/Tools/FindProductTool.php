<?php

namespace App\Services\Assistant\Tools;

use App\Models\ProductService;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class FindProductTool
{
    public function handle(User $user, ?string $term): array
    {
        $term = trim((string) $term);

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

        $matches = $query->limit(5)->get()
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
            'matches' => $matches,
        ];
    }
}
