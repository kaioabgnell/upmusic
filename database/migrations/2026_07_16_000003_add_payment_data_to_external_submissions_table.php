<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_submissions', function (Blueprint $table) {
            $table->text('payment_data')->nullable()->after('service_description');
        });
    }

    public function down(): void
    {
        Schema::table('external_submissions', function (Blueprint $table) {
            $table->dropColumn('payment_data');
        });
    }
};
