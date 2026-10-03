<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_conversations', function (Blueprint $table): void {
            $table->string('provider')->nullable()->after('status');
            $table->string('model_identifier')->nullable()->after('provider');
            $table->json('screen_context')->nullable()->after('model_identifier');
            $table->timestamp('started_at')->nullable()->after('screen_context');
            $table->timestamp('completed_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('assistant_conversations', function (Blueprint $table): void {
            $table->dropColumn([
                'provider',
                'model_identifier',
                'screen_context',
                'started_at',
                'completed_at',
            ]);
        });
    }
};
