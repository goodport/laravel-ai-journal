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
        Schema::create('ai_invocations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('invocation_id')->unique();
            $table->uuid('parent_invocation_id')->nullable()->index();
            $table->uuid('parent_tool_invocation_id')->nullable();
            $table->string('trace_id', 64)->nullable()->index();
            $table->string('agent');
            $table->string('provider', 64)->nullable()->index();
            $table->string('model', 128)->nullable()->index();
            $table->string('status', 16)->index();
            $table->boolean('streamed')->default(false);
            $table->boolean('replayed')->default(false);
            $table->string('scope', 128)->nullable()->index();
            $table->longText('prompt')->nullable();
            $table->json('attachments')->nullable();
            $table->longText('response_text')->nullable();
            $table->json('structured')->nullable();
            $table->json('usage')->nullable();
            $table->json('meta')->nullable();
            $table->decimal('cost', 12, 6)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('error_class')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->json('attempts')->nullable();
            $table->json('context')->nullable();
            $table->json('tags')->nullable();
            $table->string('conversation_id', 36)->nullable()->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['trace_id', 'created_at']);
            $table->index(['agent', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['scope', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_invocations');
    }
};
