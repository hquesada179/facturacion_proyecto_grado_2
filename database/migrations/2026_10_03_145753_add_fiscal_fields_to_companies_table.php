<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('person_type', 20)->default('juridica')->after('legal_name');
            $table->string('nit_dv', 1)->nullable()->after('nit');
            $table->json('fiscal_responsibilities')->nullable()->after('tax_regime');
            $table->foreignId('main_tax_id')->nullable()->after('fiscal_responsibilities')
                ->constrained('taxes')->nullOnDelete();
            $table->string('currency', 3)->default('COP')->after('main_tax_id');
            $table->boolean('is_test_environment')->default(true)->after('currency');
            $table->string('department')->nullable()->after('city');
            $table->string('country')->default('Colombia')->after('department');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('main_tax_id');
            $table->dropColumn([
                'person_type',
                'nit_dv',
                'fiscal_responsibilities',
                'currency',
                'is_test_environment',
                'department',
                'country',
            ]);
        });
    }
};
