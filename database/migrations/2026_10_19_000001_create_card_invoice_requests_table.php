<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitação de Nota por card.
 *
 * É o formulário externo (specs/11) ao contrário: lá cada envio cria um card novo; aqui o link
 * nasce DE um card existente e o envio entra nele — dados no histórico, NF como anexo. Mesmo
 * desenho do link de minuta (specs/19): um link por card, reutilizável, desativável.
 *
 * Tabelas próprias em vez de reaproveitar `external_submissions`: lá `external_form_id` é
 * obrigatório e `Card::externalSubmission()` é hasOne — um card criado pelo formulário externo
 * que depois recebesse a nota passaria a ter dois envios disputando o mesmo "Solicitante".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_invoice_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->unique()->constrained('cards')->cascadeOnDelete();
            $table->string('token', 40)->unique();
            $table->boolean('active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('card_invoice_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_invoice_request_id')->constrained('card_invoice_requests')->cascadeOnDelete();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->foreignId('card_attachment_id')->nullable()->constrained('card_attachments')->nullOnDelete();
            $table->string('cnpj', 18);
            $table->string('name', 180);
            $table->string('requester_name', 180);
            $table->decimal('value', 15, 2);
            $table->date('service_date');
            $table->text('service_description');
            $table->text('payment_data')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_invoice_submissions');
        Schema::dropIfExists('card_invoice_requests');
    }
};
