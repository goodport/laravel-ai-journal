<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('ai-journal.connection');
    }

    public function up(): void
    {
        Schema::table('ai_invocations', function (Blueprint $table): void {
            $table->string('error_code', 64)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('provider_request_id')->nullable();
            $table->unsignedInteger('retry_after')->nullable();
            $table->json('error_details')->nullable();

            $table->index(['error_code', 'created_at']);
            $table->index(['http_status', 'created_at']);
        });

        Schema::table('ai_invocation_steps', function (Blueprint $table): void {
            $table->string('error_code', 64)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('provider_request_id')->nullable();
            $table->unsignedInteger('retry_after')->nullable();
            $table->json('error_details')->nullable();

            $table->index(['error_code', 'created_at']);
            $table->index(['http_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_invocation_steps', function (Blueprint $table): void {
            $table->dropIndex(['error_code', 'created_at']);
            $table->dropIndex(['http_status', 'created_at']);
            $table->dropColumn(['error_code', 'http_status', 'provider_request_id', 'retry_after', 'error_details']);
        });

        Schema::table('ai_invocations', function (Blueprint $table): void {
            $table->dropIndex(['error_code', 'created_at']);
            $table->dropIndex(['http_status', 'created_at']);
            $table->dropColumn(['error_code', 'http_status', 'provider_request_id', 'retry_after', 'error_details']);
        });
    }
};
