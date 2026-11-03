<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizens', function (Blueprint $table) {
            $table->char('email_fingerprint', 64)->nullable()->index();
            $table->char('phone_fingerprint', 64)->nullable()->index();
        });

        Schema::create('citizen_identity_locks', function (Blueprint $table) {
            $table->char('lock_key', 64)->primary();
            $table->timestamps();
        });

        Schema::create('citizen_match_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('citizen_id')->constrained()->restrictOnDelete();
            $table->json('candidate_citizen_ids');
            $table->char('identity_key', 64)->index();
            $table->string('reason', 40);
            $table->string('status', 20)->default('pending')->index();
            $table->string('resolution', 20)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citizen_match_reviews');
        Schema::dropIfExists('citizen_identity_locks');

        Schema::table('citizens', function (Blueprint $table) {
            $table->dropIndex(['email_fingerprint']);
            $table->dropIndex(['phone_fingerprint']);
            $table->dropColumn(['email_fingerprint', 'phone_fingerprint']);
        });
    }
};
