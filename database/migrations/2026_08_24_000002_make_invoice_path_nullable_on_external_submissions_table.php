<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A nota fiscal passou a ser opcional no formulário público — o envio pode chegar sem arquivo.
     *
     * SQL cru em vez de `->change()`: o Laravel 10 depende de doctrine/dbal para alterar coluna e o
     * pacote não está instalado neste projeto (só o Laravel 11+ faz isso nativamente).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE external_submissions MODIFY invoice_path VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE external_submissions SET invoice_path = '' WHERE invoice_path IS NULL");
        DB::statement('ALTER TABLE external_submissions MODIFY invoice_path VARCHAR(255) NOT NULL');
    }
};
