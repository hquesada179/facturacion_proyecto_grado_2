<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_conversations', function (Blueprint $table): void {
            // IA 2.0-C: small structured snapshot (entity/customer/query
            // type/filters/result ids/time range + a timestamp for
            // staleness checks) used to resolve conversational references
            // ("esa factura", "cuáles están anuladas"). Never the full
            // conversation text — see AiAssistantService/ConversationMemory.
            $table->json('conversation_memory')->nullable()->after('messages');
        });
    }

    public function down(): void
    {
        Schema::table('assistant_conversations', function (Blueprint $table): void {
            $table->dropColumn('conversation_memory');
        });
    }
};
