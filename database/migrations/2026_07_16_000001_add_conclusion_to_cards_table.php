<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->timestamp('concluded_at')->nullable()->after('position')->index();
            $table->foreignId('concluded_by')->nullable()->after('concluded_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('concluded_by');
            $table->dropIndex(['concluded_at']);
            $table->dropColumn('concluded_at');
        });
    }
};
