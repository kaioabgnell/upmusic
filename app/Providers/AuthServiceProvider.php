<?php

namespace App\Providers;

use App\Models\BidBusinessLine;
use App\Models\BidCompany;
use App\Models\BidDocument;
use App\Models\BidDocumentCategory;
use App\Models\BidDocumentType;
use App\Models\BidNotice;
use App\Models\BidNoticeRequirement;
use App\Models\BidRequirementMatch;
use App\Models\Board;
use App\Models\Card;
use App\Models\CardCapture;
use App\Models\CardTemplate;
use App\Models\Empresa;
use App\Models\Event;
use App\Models\Fornecedor;
use App\Models\FornecedorCategoria;
use App\Models\Setor;
use App\Models\User;
use App\Policies\BidModulePolicy;
use App\Policies\BoardPolicy;
use App\Policies\CardCapturePolicy;
use App\Policies\CardPolicy;
use App\Policies\CardTemplatePolicy;
use App\Policies\EmpresaPolicy;
use App\Policies\EventPolicy;
use App\Policies\FornecedorCategoriaPolicy;
use App\Policies\FornecedorPolicy;
use App\Policies\SetorPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Setor::class => SetorPolicy::class,
        Empresa::class => EmpresaPolicy::class,
        Fornecedor::class => FornecedorPolicy::class,
        FornecedorCategoria::class => FornecedorCategoriaPolicy::class,
        Board::class => BoardPolicy::class,
        Card::class => CardPolicy::class,
        CardTemplate::class => CardTemplatePolicy::class,
        Event::class => EventPolicy::class,
        CardCapture::class => CardCapturePolicy::class,

        // Módulo de Licitações — exclusivo do Admin (ver specs/21 §7).
        BidCompany::class => BidModulePolicy::class,
        BidDocument::class => BidModulePolicy::class,
        BidNotice::class => BidModulePolicy::class,
        BidNoticeRequirement::class => BidModulePolicy::class,
        BidRequirementMatch::class => BidModulePolicy::class,
        BidDocumentCategory::class => BidModulePolicy::class,
        BidDocumentType::class => BidModulePolicy::class,
        BidBusinessLine::class => BidModulePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Admin tem acesso total (ver specs/04). Retornar null deixa a decisão para a policy.
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);
    }
}
