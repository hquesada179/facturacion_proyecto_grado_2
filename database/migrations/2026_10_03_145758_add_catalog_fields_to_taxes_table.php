<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxes', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('company_id');
            $table->string('calculation_type', 20)->default('percentage')->after('rate');
            $table->string('nature', 20)->nullable()->after('calculation_type');
            $table->string('condition', 20)->nullable()->after('nature');
            $table->date('valid_from')->nullable()->after('condition');
            $table->date('valid_until')->nullable()->after('valid_from');
            $table->boolean('is_active')->default(true)->after('valid_until');
        });
    }

    public function down(): void
    {
        Schema::table('taxes', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn([
                'code',
                'calculation_type',
                'nature',
                'condition',
                'valid_from',
                'valid_until',
                'is_active',
            ]);
        });
    }
};
