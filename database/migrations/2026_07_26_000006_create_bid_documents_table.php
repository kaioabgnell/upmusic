<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acervo de documentos de habilitação (ver specs/21 §6.5). O status de vigência NÃO é coluna —
 * é sempre calculado na leitura a partir de `expires_at`/`no_expiry` (§10.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_company_id')->constrained('bid_companies')->cascadeOnDelete();
            $table->foreignId('bid_document_category_id')->constrained('bid_document_categories')->restrictOnDelete();
            $table->foreignId('bid_document_type_id')->nullable()->constrained('bid_document_types')->nullOnDelete();
            $table->string('name', 180);
            $table->string('control_code', 120)->nullable();
            $table->string('issuer', 120)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('no_expiry')->default(false);
            $table->string('file_path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size')->default(0);
            $table->json('ai_extracted')->nullable();
            $table->decimal('ai_confidence', 4, 3)->nullable();
            $table->text('notes')->nullable();
            // Versionamento: o novo documento aponta para o que substituiu; o antigo recebe
            // `superseded_at` e sai das listas vigentes, permanecendo no histórico.
            $table->foreignId('supersedes_id')->nullable()->constrained('bid_documents')->nullOnDelete();
            $table->dateTime('superseded_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['bid_company_id', 'superseded_at'], 'bid_documents_company_current_index');
            $table->index(['bid_company_id', 'bid_document_type_id'], 'bid_documents_company_type_index');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_documents');
    }
};
