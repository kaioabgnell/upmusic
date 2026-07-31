<?php

namespace App\Console\Commands;

use App\Models\UserNotification;
use Illuminate\Console\Command;

/**
 * Retenção das notificações internas (specs/22 §6). Não é agendado — o projeto não roda
 * `schedule:work`; fica disponível para execução manual ou cron do servidor.
 */
class PruneNotifications extends Command
{
    protected $signature = 'notificacoes:limpar {--dias=90 : Idade mínima, em dias, das notificações lidas a remover}';

    protected $description = 'Remove notificações já lidas com mais de N dias (padrão 90). Ver specs/22.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('dias'));

        $removed = UserNotification::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays($days))
            ->delete();

        $this->info("{$removed} notificação(ões) lida(s) com mais de {$days} dia(s) removida(s).");

        return self::SUCCESS;
    }
}
