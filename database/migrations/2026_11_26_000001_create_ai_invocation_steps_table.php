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
        Schema::create('ai_invocation_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_invocation_id')->constrained('ai_invocations')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->unsignedSmallInteger('step_number')->default(0);
            $table->string('status', 16)->index();
            $table->string('provider', 64)->nullable();
            $table->string('model', 128)->nullable();
            $table->char('request_fingerprint', 64)->nullable()->index();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->json('usage')->nullable();
            $table->decimal('cost', 12, 6)->nullable();
            $table->char('currency', 3)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error_class')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('replay_of_step_id')->nullable()->constrained('ai_invocation_steps')->nullOnDelete();
            $table->timestamps();

            $table->unique(['ai_invocation_id', 'attempt_number', 'step_number'], 'ai_invocation_steps_unique');
            $table->index(['request_fingerprint', 'status', 'created_at'], 'ai_invocation_steps_replay_index');
            $table->index(['status', 'created_at']);
            $table->index('replay_of_step_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_invocation_steps');
    }
};
