<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // `type` era varchar(10) — cabia nos valores originais ("conclusion", "unarchival" já usavam
        // os 10 caracteres no limite), mas o novo MovementType::MinutaRecebida ("minuta_recebida",
        // 16 caracteres) estourava o limite e quebrava a movimentação automática do card ao receber
        // a minuta do fornecedor (specs/19). Alargado para 30 para não repetir o problema com
        // futuros tipos de movimentação. Raw SQL porque `->change()` exige doctrine/dbal, não
        // instalado neste projeto.
        DB::statement('ALTER TABLE card_movements MODIFY type VARCHAR(30) NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE card_movements MODIFY type VARCHAR(10) NOT NULL');
    }
};
