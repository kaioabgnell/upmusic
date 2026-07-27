<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Log de uso da IA — base do controle de custo (ver specs/21 §6.10 e §14). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_ai_calls', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['documento', 'edital']);
            $table->string('related_type', 60)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('model', 60);
            $table->string('prompt_version', 20)->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->boolean('success')->default(false);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error_message', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index(['related_type', 'related_id'], 'bid_ai_calls_related_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_ai_calls');
    }
};
