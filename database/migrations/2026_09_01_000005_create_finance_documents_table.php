<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O "CONTROLE" da planilha (colunas V-AA) — specs/23 §4.5.
 *
 * O documento que já vive no card NÃO é copiado: a linha aponta para o `card_attachments` existente.
 * É o que elimina o trabalho dobrado. Upload direto (guia, boleto, taxa sem card) usa o outro lado.
 * Invariante garantida pela Action CreateFinanceDocument: `card_attachment_id` XOR `path`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_cost_item_id')->constrained('finance_cost_items')->cascadeOnDelete();
            $table->string('kind', 20);   // FinanceDocumentKind

            // Forma A — referência ao anexo do card (caso comum).
            $table->foreignId('card_attachment_id')->nullable()
                ->constrained('card_attachments')->cascadeOnDelete();

            // Forma B — upload feito direto no financeiro.
            $table->string('original_name', 255)->nullable();
            $table->string('path', 255)->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedInteger('size')->default(0);

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Idempotência da ponte: reenviar o mesmo card não duplica documento.
            $table->unique(['finance_cost_item_id', 'card_attachment_id'], 'finance_documents_item_attachment_uq');
            $table->index(['finance_cost_item_id', 'kind'], 'finance_documents_item_kind_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_documents');
    }
};
