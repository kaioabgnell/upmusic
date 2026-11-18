<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Limpa capturas pendentes abandonadas há mais de 7 dias — ver specs/16.
        $schedule->command('captures:prune')->daily();

        // Custos fixos no modo "X dias antes do vencimento" — ver specs/27 §3.2. Exige o cron do
        // `schedule:run` no servidor; sem ele, o botão "Gerar próxima parcela agora" cobre.
        $schedule->command('custos-fixos:gerar')->dailyAt('06:00')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
