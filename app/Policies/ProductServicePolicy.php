<?php

namespace App\Policies;

use App\Models\ProductService;
use App\Models\User;

class ProductServicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductService $productService): bool
    {
        return $user->company_id === $productService->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('manage-invoicing');
    }

    public function update(User $user, ProductService $productService): bool
    {
        return $user->can('manage-invoicing') && $user->company_id === $productService->company_id;
    }

    public function delete(User $user, ProductService $productService): bool
    {
        return $user->can('manage-invoicing') && $user->company_id === $productService->company_id;
    }
}
