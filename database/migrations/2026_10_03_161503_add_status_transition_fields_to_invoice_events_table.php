<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_events', function (Blueprint $table) {
            $table->string('from_status')->nullable()->after('type');
            $table->string('to_status')->nullable()->after('from_status');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_events', function (Blueprint $table) {
            $table->dropColumn(['from_status', 'to_status']);
        });
    }
};
