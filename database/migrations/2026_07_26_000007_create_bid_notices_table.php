<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Editais analisados (ver specs/21 §6.6). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_notices', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->enum('status', ['rascunho', 'processando', 'analisado', 'erro'])->default('rascunho');
            $table->enum('source', ['pdf', 'imagem', 'texto']);
            $table->string('file_path', 255)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->longText('raw_text')->nullable();
            $table->string('agency', 180)->nullable();
            $table->string('number', 60)->nullable();
            $table->string('process_number', 60)->nullable();
            $table->string('modality', 60)->nullable();
            $table->string('portal', 120)->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('city', 120)->nullable();
            $table->text('object_summary')->nullable();
            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->dateTime('session_at')->nullable();
            $table->dateTime('proposal_deadline_at')->nullable();
            $table->boolean('me_epp_exclusive')->nullable();
            $table->boolean('requires_site_visit')->nullable();
            $table->boolean('requires_bid_bond')->nullable();
            $table->decimal('ai_confidence', 4, 3)->nullable();
            $table->json('ai_warnings')->nullable();
            $table->longText('raw_response')->nullable();
            $table->string('prompt_version', 20)->nullable();
            $table->string('error_message', 255)->nullable();
            $table->dateTime('analyzed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('session_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_notices');
    }
};
