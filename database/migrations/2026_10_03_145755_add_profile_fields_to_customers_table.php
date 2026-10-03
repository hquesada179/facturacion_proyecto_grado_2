<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('person_type', 20)->default('natural')->after('identification_type');
            $table->string('dv', 1)->nullable()->after('identification_number');
            $table->string('department')->nullable()->after('city');
            $table->string('country')->default('Colombia')->after('department');
            $table->foreignId('tax_id')->nullable()->after('tax_responsibility')
                ->constrained('taxes')->nullOnDelete();
            $table->boolean('is_final_consumer')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_id');
            $table->dropColumn(['person_type', 'dv', 'department', 'country', 'is_final_consumer']);
        });
    }
};
