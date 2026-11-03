<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short, human-typable linking code ("FISCORA-7K4P9X"), sent back by
 * WhatsApp as plain text with no other identifying information — so,
 * unlike EmailVerificationCode (looked up by the already-known
 * authenticated user_id), the bridge has to find the matching row from
 * the code ALONE. `lookup_hash` (HMAC-SHA256 keyed by APP_KEY, never a
 * bare unkeyed hash) gives an O(1) indexed candidate lookup without
 * being reversible from a DB dump alone; `code_hash` (bcrypt, same
 * convention as users.password/email_verification_codes.code_hash) is
 * what actually decides a match — see WhatsappLinkService::consume().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_link_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->string('lookup_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_link_codes');
    }
};
