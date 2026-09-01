<?php

namespace App\Providers;

use App\Models\Card;
use App\Models\CardAttachment;
use App\Observers\CardAttachmentObserver;
use App\Observers\CardObserver;
use App\View\Composers\NotificationComposer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Performance/qualidade: detecta N+1 e acesso a atributos/colunas ausentes
        // fora de produção. Ver specs/01-arquitetura-tecnica.md.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());

        // Notificações (specs/22): gatilho único das notificações de responsável — qualquer
        // caminho que grave `assignee_id` passa por aqui.
        Card::observe(CardObserver::class);
        // Anexo novo em card já vinculado ao Financeiro vira documento de controle (specs/23).
        CardAttachment::observe(CardAttachmentObserver::class);

        // Badge do sino já correto no primeiro paint, sem piscar em 0 até o primeiro fetch.
        View::composer('components.notification-bell', NotificationComposer::class);
    }
}
