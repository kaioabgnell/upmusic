<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notificações internas do sino da topbar (specs/22).
 *
 * O nome `user_notifications` (e não `notifications`) é deliberado: `App\Models\User` usa o trait
 * `Notifiable` do Laravel, que reserva a tabela `notifications` para o canal `database` (uuid +
 * morph). Uma tabela `notifications` com schema próprio quebraria aquela relação silenciosamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('card_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('board_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Lista do sino (user_id + ordem por id desc) e contador do badge / filtro
            // "somente não lidas" (user_id + read_at) sem varrer a tabela inteira.
            $table->index(['user_id', 'read_at', 'id']);
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
