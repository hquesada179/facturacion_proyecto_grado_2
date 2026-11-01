<?php

namespace Tests\Unit;

use App\Models\ProductService;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Fase 3 intentionally stops at "prepared but not wired": these helpers
 * exist and are safe to call, but nothing in the issuance/credit-note
 * flow calls them yet (see the Fase 3 report for why). Covering them
 * here keeps the non-negative guarantee tested even while unused.
 */
class ProductServiceInventoryTest extends TestCase
{
    public function test_only_a_product_can_be_inventoried(): void
    {
        $product = new ProductService(['type' => 'product']);
        $service = new ProductService(['type' => 'service']);

        $this->assertTrue($product->canBeInventoried());
        $this->assertFalse($service->canBeInventoried());
    }

    public function test_decrementing_stock_reduces_the_balance(): void
    {
        $product = new ProductService(['type' => 'product', 'stock_quantity' => '10.00']);

        $product->decrementStock('4');

        $this->assertSame('6.00', (string) $product->stock_quantity);
    }

    public function test_decrementing_more_than_available_throws_instead_of_going_negative(): void
    {
        $product = new ProductService(['type' => 'product', 'stock_quantity' => '3.00']);

        $this->expectException(InvalidArgumentException::class);

        $product->decrementStock('5');
    }

    public function test_incrementing_stock_restores_the_balance(): void
    {
        $product = new ProductService(['type' => 'product', 'stock_quantity' => '6.00']);

        $product->incrementStock('4');

        $this->assertSame('10.00', (string) $product->stock_quantity);
    }
}
