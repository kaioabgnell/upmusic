<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('card_supplier_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_supplier_form_id')->constrained('card_supplier_forms')->cascadeOnDelete();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->foreignId('card_attachment_id')->nullable()->constrained('card_attachments')->nullOnDelete();
            $table->text('note')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_supplier_submissions');
    }
};
