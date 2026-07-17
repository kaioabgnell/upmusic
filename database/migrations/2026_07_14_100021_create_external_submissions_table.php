<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_form_id')->constrained('external_forms')->cascadeOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('card_id')->nullable()->constrained('cards')->nullOnDelete();
            $table->string('cnpj', 18);
            $table->string('name', 180);
            $table->decimal('value', 15, 2);
            $table->date('service_date');
            $table->text('service_description');
            $table->string('invoice_path', 255);
            $table->string('status', 20)->default('recebido');
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_submissions');
    }
};
