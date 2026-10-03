<?php

namespace Database\Seeders;

use App\Models\Tax;
use Illuminate\Database\Seeder;

class TaxSeeder extends Seeder
{
    /**
     * Global tax catalog (company_id = null) shared by every company.
     * Idempotent by code, safe to run on every seed.
     */
    public function run(): void
    {
        $taxes = [
            ['code' => 'IVA19', 'name' => 'IVA 19%', 'rate' => 19, 'nature' => 'IVA', 'condition' => 'gravado'],
            ['code' => 'IVA5', 'name' => 'IVA 5%', 'rate' => 5, 'nature' => 'IVA', 'condition' => 'gravado'],
            ['code' => 'IVA0', 'name' => 'IVA 0%', 'rate' => 0, 'nature' => 'IVA', 'condition' => 'gravado'],
            ['code' => 'EXENTO', 'name' => 'Exento', 'rate' => 0, 'nature' => 'IVA', 'condition' => 'exento'],
            ['code' => 'EXCLUIDO', 'name' => 'Excluido', 'rate' => 0, 'nature' => 'ninguna', 'condition' => 'excluido'],
            ['code' => 'INC8', 'name' => 'INC 8%', 'rate' => 8, 'nature' => 'INC', 'condition' => 'gravado'],
        ];

        foreach ($taxes as $tax) {
            Tax::query()->firstOrCreate(
                ['code' => $tax['code']],
                array_merge($tax, [
                    'company_id' => null,
                    'type' => 'vat',
                    'calculation_type' => 'percentage',
                    'is_default' => $tax['code'] === 'IVA19',
                    'is_active' => true,
                ])
            );
        }
    }
}
