<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_template_items', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('description');
            $table->string('priority', 10)->nullable()->after('due_date');
            $table->foreignId('default_assignee_id')->nullable()->after('priority')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('card_template_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_assignee_id');
            $table->dropColumn(['due_date', 'priority']);
        });
    }
};
