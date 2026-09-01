<?php

use App\Http\Controllers\Bid\BidCompanyController;
use App\Http\Controllers\Bid\BidDashboardController;
use App\Http\Controllers\Bid\BidDocumentController;
use App\Http\Controllers\Bid\BidNoticeController;
use App\Http\Controllers\Bid\BidReportController;
use App\Http\Controllers\Bid\BidRequirementController;
use App\Http\Controllers\Bid\BidSettingsController;
use App\Http\Controllers\BoardColumnController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\BoardFieldController;
use App\Http\Controllers\CaptureController;
use App\Http\Controllers\CaptureTokenController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CardSupplierFormController;
use App\Http\Controllers\CnpjLookupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExternalFormController;
use App\Http\Controllers\Finance\CardFinanceController;
use App\Http\Controllers\Finance\FinanceCostItemController;
use App\Http\Controllers\Finance\FinanceDocumentController;
use App\Http\Controllers\Finance\FinanceImportExportController;
use App\Http\Controllers\Finance\FinanceItemPresetController;
use App\Http\Controllers\Finance\FinancePaymentController;
use App\Http\Controllers\Finance\FinancePaymentSourceController;
use App\Http\Controllers\Finance\FinanceRevenueController;
use App\Http\Controllers\Finance\FinanceSettlementController;
use App\Http\Controllers\Finance\FinanceSheetController;
use App\Http\Controllers\FinancialEntryController;
use App\Http\Controllers\FinancialPlanController;
use App\Http\Controllers\FinancialReportController;
use App\Http\Controllers\FornecedorCategoriaController;
use App\Http\Controllers\FornecedorController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PriceCategoriaController;
use App\Http\Controllers\PriceHistoryController;
use App\Http\Controllers\PriceRecordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicFormController;
use App\Http\Controllers\SetorController;
use App\Http\Controllers\SupplierFormController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TemplateItemController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

// Formulário externo público (sem autenticação) — ver specs/11.
Route::get('/f/{token}', [PublicFormController::class, 'show'])->name('external.form.show');
Route::post('/f/{token}', [PublicFormController::class, 'submit'])
    ->middleware('throttle:10,1')->name('external.form.submit');
Route::get('/f/{token}/sucesso', [PublicFormController::class, 'success'])->name('external.form.success');

// Formulário de minuta do fornecedor (link por card, sem autenticação) — ver specs/19.
Route::get('/minuta/{token}', [SupplierFormController::class, 'show'])->name('supplier.form.show');
Route::post('/minuta/{token}', [SupplierFormController::class, 'submit'])
    ->middleware('throttle:10,1')->name('supplier.form.submit');
Route::get('/minuta/{token}/sucesso', [SupplierFormController::class, 'success'])->name('supplier.form.success');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
    // Foto de perfil servida pelo Laravel (sem depender do symlink /storage — ver ProfileController::showAvatar).
    Route::get('/avatar/{user}', [ProfileController::class, 'showAvatar'])->name('avatar.show');

    // Notificações (specs/22) — JSON, sempre escopadas ao usuário logado.
    // Rotas literais antes do wildcard {notification}.
    Route::get('notificacoes', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notificacoes/contador', [NotificationController::class, 'count'])->name('notifications.count');
    Route::post('notificacoes/marcar-todas-lidas', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notificacoes/{notification}/lida', [NotificationController::class, 'read'])->name('notifications.read');

    // Usuários (Admin/Coordenador) — ver specs/04.
    Route::resource('usuarios', UserController::class)
        ->parameters(['usuarios' => 'user'])
        ->names('users')
        ->except('show')
        ->middleware('role:admin,coordenador');

    // Empresas — busca e cadastro inline disponíveis a qualquer autenticado (fluxo do card).
    Route::get('empresas/buscar', [EmpresaController::class, 'search'])->name('empresas.search');
    Route::post('empresas/quick', [EmpresaController::class, 'quick'])->name('empresas.quick');

    // Consulta de CNPJ (specs/19) — preenchimento automático da razão social nos cadastros
    // rápidos de empresa/fornecedor (ver ConsultaCnpjService).
    Route::get('cnpj/{cnpj}', [CnpjLookupController::class, 'show'])->name('cnpj.lookup');

    // Fornecedores — CRUD completo liberado a qualquer usuário autenticado (não é "cadastro base"
    // restrito a Admin/Coordenador como Setores/Empresas/Eventos).
    Route::post('fornecedores/quick', [FornecedorController::class, 'quick'])->name('fornecedores.quick');
    Route::get('fornecedores/{fornecedor}/preco-historico', [FornecedorController::class, 'priceHistory'])->name('fornecedores.price-history');
    Route::resource('fornecedores', FornecedorController::class)
        ->parameters(['fornecedores' => 'fornecedor'])->except('show');

    // Quadros / Departamentos — ver specs/06.
    Route::get('quadros', [BoardController::class, 'index'])->name('boards.index');

    Route::middleware('role:admin,coordenador')->group(function () {
        // Rotas literais antes do wildcard {board}.
        Route::get('quadros/criar', [BoardController::class, 'create'])->name('boards.create');
        Route::post('quadros', [BoardController::class, 'store'])->name('boards.store');
        Route::get('quadros/{board}/editar', [BoardController::class, 'edit'])->name('boards.edit');
        Route::put('quadros/{board}', [BoardController::class, 'update'])->name('boards.update');
        Route::delete('quadros/{board}', [BoardController::class, 'destroy'])->name('boards.destroy');
        Route::get('quadros/{board}/configurar', [BoardController::class, 'config'])->name('boards.config');
        Route::put('quadros/{board}/acesso', [BoardController::class, 'updateAccess'])->name('boards.access');
        Route::put('quadros/{board}/config-fornecedor', [BoardController::class, 'updateSupplierForm'])->name('boards.supplier-form.update');

        // Colunas (JSON)
        Route::post('quadros/{board}/colunas', [BoardColumnController::class, 'store'])->name('columns.store');
        Route::put('colunas/{column}', [BoardColumnController::class, 'update'])->name('columns.update');
        Route::delete('colunas/{column}', [BoardColumnController::class, 'destroy'])->name('columns.destroy');
        Route::post('quadros/{board}/colunas/reordenar', [BoardColumnController::class, 'reorder'])->name('columns.reorder');
        Route::put('colunas/{column}/aprovadores', [BoardColumnController::class, 'updateApprovers'])->name('columns.approvers.update');

        // Campos do card (JSON)
        Route::post('quadros/{board}/campos', [BoardFieldController::class, 'store'])->name('fields.store');
        Route::put('campos/{field}', [BoardFieldController::class, 'update'])->name('fields.update');
        Route::delete('campos/{field}', [BoardFieldController::class, 'destroy'])->name('fields.destroy');
        Route::post('quadros/{board}/campos/reordenar', [BoardFieldController::class, 'reorder'])->name('fields.reorder');

        // Formulário externo (gestão) — ver specs/11.
        Route::get('quadros/{board}/formulario', [ExternalFormController::class, 'manage'])->name('external.forms.manage');
        Route::put('quadros/{board}/formulario', [ExternalFormController::class, 'update'])->name('external.forms.update');
        Route::post('quadros/{board}/formulario/regenerar', [ExternalFormController::class, 'regenerate'])->name('external.forms.regenerate');
    });

    // Cards — acesso conforme o quadro (CardPolicy). Ver specs/07.
    Route::get('cards', [CardController::class, 'index'])->name('cards.index');
    Route::post('quadros/{board}/cards', [CardController::class, 'store'])->name('cards.store');
    Route::get('cards/{card}', [CardController::class, 'show'])->name('cards.show');
    Route::put('cards/{card}', [CardController::class, 'update'])->name('cards.update');
    Route::delete('cards/{card}', [CardController::class, 'destroy'])->name('cards.destroy');
    Route::post('cards/{card}/mover', [CardController::class, 'move'])->name('cards.move');
    Route::post('cards/{card}/enviar-departamento', [CardController::class, 'transfer'])->name('cards.transfer');
    Route::post('cards/{card}/concluir', [CardController::class, 'conclude'])->name('cards.conclude');
    Route::post('cards/{card}/reabrir', [CardController::class, 'reopen'])->name('cards.reopen');
    Route::post('cards/{card}/duplicar', [CardController::class, 'duplicate'])->name('cards.duplicate');
    Route::post('cards/{card}/arquivar', [CardController::class, 'archive'])->name('cards.archive');
    Route::post('cards/{card}/desarquivar', [CardController::class, 'unarchive'])->name('cards.unarchive');
    Route::post('cards/{card}/aprovar', [CardController::class, 'approve'])->name('cards.approve');
    Route::post('cards/{card}/reprovar', [CardController::class, 'reject'])->name('cards.reject');
    // Link de minuta do fornecedor (specs/19) — gerar/desativar; página pública fica fora do grupo auth.
    Route::post('cards/{card}/minuta/link', [CardSupplierFormController::class, 'generate'])->name('supplier.link.generate');
    Route::delete('cards/{card}/minuta/link', [CardSupplierFormController::class, 'disable'])->name('supplier.link.disable');
    // Ponte Kanban -> Financeiro (specs/23 §6). Fica no bloco de cards de propósito: quem edita o
    // card empurra a despesa para o financeiro, mesmo sem acesso ao módulo (autorizado pela
    // CardPolicy). Rotas literais antes do wildcard de anexos.
    Route::get('cards/{card}/financeiro/preview', [CardFinanceController::class, 'preview'])->name('cards.finance.preview');
    Route::post('cards/{card}/financeiro', [CardFinanceController::class, 'sync'])->name('cards.finance.sync');
    Route::post('cards/{card}/comentarios', [CardController::class, 'storeComment'])->name('cards.comments.store');
    Route::post('cards/{card}/anexos', [CardController::class, 'storeAttachment'])->name('cards.attachments.store');
    Route::delete('anexos/{attachment}', [CardController::class, 'destroyAttachment'])->name('cards.attachments.destroy');
    Route::get('anexos/{attachment}/download', [CardController::class, 'downloadAttachment'])->name('cards.attachments.download');

    // Captura rápida — qualquer usuário autenticado e ativo (ferramenta pessoal, não cadastro base).
    // Ver specs/16. Autorização por dono via CardCapturePolicy (não por role/quadro).
    // `captures.show` fica FORA deste grupo (ver abaixo) — precisa abrir sem sessão prévia via link
    // assinado (Atalho iOS); `captures.store` fica aqui normalmente: por já rodar depois do auto-login
    // feito em show(), sempre chega com sessão válida.
    // Rotas literais (configurar-iphone, upload, token) SEMPRE antes do wildcard `{capture}` — senão o
    // Laravel tenta casar "token"/"upload" como o parâmetro {capture} e falha o binding com 404.
    Route::get('capturas', [CaptureController::class, 'index'])->name('captures.index');
    Route::post('capturas/upload', [CaptureController::class, 'upload'])->name('captures.upload');

    // Configurar iPhone — token pessoal (Sanctum) para o Atalho compartilhar via WhatsApp. Fase 3/specs/16.
    Route::get('capturas/configurar-iphone', [CaptureTokenController::class, 'edit'])->name('captures.ios.setup');
    Route::post('capturas/token', [CaptureTokenController::class, 'store'])->name('captures.token.store');
    Route::delete('capturas/token', [CaptureTokenController::class, 'destroy'])->name('captures.token.destroy');

    Route::get('capturas/{capture}/preview', [CaptureController::class, 'preview'])->name('captures.preview');
    Route::post('capturas/{capture}/criar-card', [CaptureController::class, 'store'])->name('captures.store');
    Route::delete('capturas/{capture}', [CaptureController::class, 'destroy'])->name('captures.destroy');

    // Importar template — qualquer usuário com acesso ao quadro (autorizado no controller).
    Route::post('templates/{template}/importar', [TemplateController::class, 'import'])->name('templates.import');

    // Banco de preços por categoria — leitura liberada a qualquer autenticado. Ver specs/15.
    Route::get('precos/evolucao', [PriceHistoryController::class, 'index'])->name('prices.history');
    Route::get('precos/categorias', [PriceCategoriaController::class, 'index'])->name('prices.categorias.index');
    Route::get('precos/categorias/{fornecedorCategoria}', [PriceCategoriaController::class, 'show'])->name('prices.categorias.show');

    // Dados assíncronos do quadro (colunas + cards) — ver specs/14.
    Route::get('quadros/{board}/kanban', [BoardController::class, 'kanbanData'])->name('boards.kanban.data');

    // Link direto de card (specs/18) — mesmo controller/método de boards.show, {card} opcional.
    // ->missing(): quadro (ou card) inexistente redireciona para a lista de quadros em vez de 404 cru.
    Route::get('quadros/{board}/card/{card?}', [BoardController::class, 'show'])->name('boards.show.card')
        ->missing(fn () => redirect()->route('boards.index'));

    // Wildcard de exibição por último para não capturar as rotas literais acima.
    Route::get('quadros/{board}', [BoardController::class, 'show'])->name('boards.show')
        ->missing(fn () => redirect()->route('boards.index'));

    // Cadastros base (Admin/Coordenador) — ver specs/05.
    Route::middleware('role:admin,coordenador')->group(function () {
        Route::resource('setores', SetorController::class)
            ->parameters(['setores' => 'setor'])->except('show');
        Route::resource('empresas', EmpresaController::class)->except('show');
        Route::resource('fornecedor-categorias', FornecedorCategoriaController::class)
            ->parameters(['fornecedor-categorias' => 'fornecedorCategoria'])->except('show');
        Route::post('fornecedor-categorias/quick', [FornecedorCategoriaController::class, 'quick'])->name('fornecedor-categorias.quick');
        Route::resource('eventos', EventController::class)
            ->parameters(['eventos' => 'evento'])->except('show');

        // Templates de cards — ver specs/08.
        Route::resource('templates', TemplateController::class)->except('show');
        Route::post('templates/{template}/itens', [TemplateItemController::class, 'store'])->name('template.items.store');
        Route::put('template-itens/{item}', [TemplateItemController::class, 'update'])->name('template.items.update');
        Route::delete('template-itens/{item}', [TemplateItemController::class, 'destroy'])->name('template.items.destroy');
        Route::post('templates/{template}/itens/reordenar', [TemplateItemController::class, 'reorder'])->name('template.items.reorder');

        /*
        |------------------------------------------------------------------
        | Financeiro do Evento (specs/23) — substitui a planilha do evento
        |------------------------------------------------------------------
        | Rotas literais e prefixos fixos SEMPRE antes dos wildcards ({evento}, {item}, ...).
        | `usuario` não entra aqui (o grupo é role:admin,coordenador) e o coordenador restrito por
        | evento é filtrado pela FinanceSheetPolicy.
        */
        Route::get('financeiro', [FinanceSheetController::class, 'index'])->name('finance.index');

        // Catálogo do módulo — configuração é do Admin (o middleware fecha para o coordenador).
        Route::middleware('role:admin')->group(function () {
            Route::get('financeiro/configuracoes', [FinancePaymentSourceController::class, 'index'])->name('finance.settings.index');
            Route::post('financeiro/configuracoes/fontes', [FinancePaymentSourceController::class, 'store'])->name('finance.sources.store');
            Route::put('financeiro/configuracoes/fontes/{source}', [FinancePaymentSourceController::class, 'update'])->name('finance.sources.update');
            Route::delete('financeiro/configuracoes/fontes/{source}', [FinancePaymentSourceController::class, 'destroy'])->name('finance.sources.destroy');
            Route::post('financeiro/configuracoes/itens', [FinanceItemPresetController::class, 'store'])->name('finance.presets.store');
            Route::put('financeiro/configuracoes/itens/{preset}', [FinanceItemPresetController::class, 'update'])->name('finance.presets.update');
            Route::delete('financeiro/configuracoes/itens/{preset}', [FinanceItemPresetController::class, 'destroy'])->name('finance.presets.destroy');
        });

        // Autocomplete da coluna DESCRIÇÃO (leitura) — usado pela grade e pelo modal do card.
        Route::get('financeiro/itens', [FinanceItemPresetController::class, 'index'])->name('finance.presets.index');

        // Linhas, pagamentos e documentos (wildcards próprios, fora do prefixo de evento).
        Route::put('financeiro/custos/{item}', [FinanceCostItemController::class, 'update'])->name('finance.costs.update');
        Route::delete('financeiro/custos/{item}', [FinanceCostItemController::class, 'destroy'])->name('finance.costs.destroy');
        Route::post('financeiro/custos/{item}/duplicar', [FinanceCostItemController::class, 'duplicate'])->name('finance.costs.duplicate');
        Route::get('financeiro/custos/{item}/pagamentos', [FinancePaymentController::class, 'index'])->name('finance.payments.index');
        Route::post('financeiro/custos/{item}/pagamentos', [FinancePaymentController::class, 'store'])->name('finance.payments.store');
        Route::get('financeiro/custos/{item}/documentos', [FinanceDocumentController::class, 'index'])->name('finance.documents.index');
        Route::post('financeiro/custos/{item}/documentos', [FinanceDocumentController::class, 'store'])->name('finance.documents.store');
        Route::post('financeiro/custos/{item}/documentos/vincular', [FinanceDocumentController::class, 'attach'])->name('finance.documents.attach');
        Route::put('financeiro/pagamentos/{payment}', [FinancePaymentController::class, 'update'])->name('finance.payments.update');
        Route::delete('financeiro/pagamentos/{payment}', [FinancePaymentController::class, 'destroy'])->name('finance.payments.destroy');
        Route::get('financeiro/documentos/{document}', [FinanceDocumentController::class, 'show'])->name('finance.documents.show');
        Route::delete('financeiro/documentos/{document}', [FinanceDocumentController::class, 'destroy'])->name('finance.documents.destroy');
        Route::put('financeiro/receitas/{revenue}', [FinanceRevenueController::class, 'update'])->name('finance.revenues.update');
        Route::delete('financeiro/receitas/{revenue}', [FinanceRevenueController::class, 'destroy'])->name('finance.revenues.destroy');

        // Planilha de um evento.
        Route::prefix('financeiro/eventos/{evento}')->group(function () {
            Route::get('/', [FinanceSheetController::class, 'show'])->name('finance.show');
            Route::put('config', [FinanceSheetController::class, 'updateConfig'])->name('finance.config.update');
            Route::post('fechar', [FinanceSheetController::class, 'close'])->name('finance.close');
            Route::post('reabrir', [FinanceSheetController::class, 'reopen'])->name('finance.reopen');
            Route::put('socios', [FinanceSettlementController::class, 'sync'])->name('finance.settlements.sync');

            Route::get('receitas', [FinanceRevenueController::class, 'index'])->name('finance.revenues.index');
            Route::post('receitas', [FinanceRevenueController::class, 'store'])->name('finance.revenues.store');

            Route::get('custos', [FinanceCostItemController::class, 'index'])->name('finance.costs.index');
            Route::post('custos', [FinanceCostItemController::class, 'store'])->name('finance.costs.store');
            Route::post('custos/em-massa', [FinanceCostItemController::class, 'bulk'])->name('finance.costs.bulk');

            Route::get('exportar', [FinanceImportExportController::class, 'export'])->name('finance.export');
            Route::post('importar/preview', [FinanceImportExportController::class, 'importPreview'])->name('finance.import.preview');
            Route::post('importar', [FinanceImportExportController::class, 'import'])->name('finance.import');
        });

        // Planejamento financeiro (specs/09) — SUBSTITUÍDO pelo Financeiro do Evento acima.
        // Mantido acessível por URL (fora do menu) só para consulta do histórico; ver specs/23 §14.
        Route::get('financeiro/comparativo', [FinancialReportController::class, 'report'])->name('financial.report');
        Route::get('financeiro/comparativo/exportar', [FinancialReportController::class, 'export'])->name('financial.export');
        Route::resource('financeiro/planos', FinancialPlanController::class)
            ->parameters(['planos' => 'plan'])->names('plans')->except('show');
        Route::post('financeiro/planos/{plan}/importar/preview', [FinancialPlanController::class, 'importPreview'])->name('plans.import.preview');
        Route::post('financeiro/planos/{plan}/importar', [FinancialPlanController::class, 'import'])->name('plans.import');
        Route::post('financeiro/planos/{plan}/lancamentos', [FinancialEntryController::class, 'store'])->name('entries.store');
        Route::put('financeiro/lancamentos/{entry}', [FinancialEntryController::class, 'update'])->name('entries.update');
        Route::delete('financeiro/lancamentos/{entry}', [FinancialEntryController::class, 'destroy'])->name('entries.destroy');

        // Banco de preços (gestão dos registros) — ver specs/15.
        Route::post('precos/categorias/{fornecedorCategoria}/registros', [PriceRecordController::class, 'store'])->name('prices.store');
        Route::put('precos/registros/{priceRecord}', [PriceRecordController::class, 'update'])->name('prices.update');
        Route::delete('precos/registros/{priceRecord}', [PriceRecordController::class, 'destroy'])->name('prices.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Módulo de Licitações — exclusivo do Admin (ver specs/21)
    |--------------------------------------------------------------------------
    | Coordenador (restrito ou não) e Usuário recebem 403 aqui; o bloqueio é de servidor, e não
    | apenas o item oculto na sidebar. Rotas literais sempre antes dos wildcards.
    */
    Route::middleware('role:admin')->prefix('licitacoes')->name('bid.')->group(function () {
        Route::get('/', [BidDashboardController::class, 'index'])->name('dashboard');

        // Empresas licitantes
        Route::get('empresas', [BidCompanyController::class, 'index'])->name('companies.index');
        Route::get('empresas/criar', [BidCompanyController::class, 'create'])->name('companies.create');
        Route::post('empresas', [BidCompanyController::class, 'store'])->name('companies.store');
        Route::get('empresas/{company}', [BidCompanyController::class, 'show'])->name('companies.show');
        Route::get('empresas/{company}/editar', [BidCompanyController::class, 'edit'])->name('companies.edit');
        Route::put('empresas/{company}', [BidCompanyController::class, 'update'])->name('companies.update');
        Route::delete('empresas/{company}', [BidCompanyController::class, 'destroy'])->name('companies.destroy');

        // Documentos do acervo
        Route::post('empresas/{company}/documentos', [BidDocumentController::class, 'store'])->name('documents.store');
        // Leitura assistida pela IA — literal antes de {document} e com throttle (specs/21 §12).
        Route::post('documentos/ler', [BidDocumentController::class, 'read'])
            ->middleware('throttle:10,1')->name('documents.read');
        Route::put('documentos/{document}', [BidDocumentController::class, 'update'])->name('documents.update');
        Route::post('documentos/{document}/renovar', [BidDocumentController::class, 'renew'])->name('documents.renew');
        Route::delete('documentos/{document}', [BidDocumentController::class, 'destroy'])->name('documents.destroy');
        Route::get('documentos/{document}/arquivo', [BidDocumentController::class, 'file'])->name('documents.file');
        Route::get('documentos/{document}/historico', [BidDocumentController::class, 'history'])->name('documents.history');

        // Análise de edital
        Route::get('editais', [BidNoticeController::class, 'index'])->name('notices.index');
        Route::get('editais/nova', [BidNoticeController::class, 'create'])->name('notices.create');
        Route::post('editais', [BidNoticeController::class, 'store'])
            ->middleware('throttle:10,1')->name('notices.store');
        Route::get('editais/{notice}', [BidNoticeController::class, 'show'])->name('notices.show');
        Route::put('editais/{notice}', [BidNoticeController::class, 'update'])->name('notices.update');
        Route::delete('editais/{notice}', [BidNoticeController::class, 'destroy'])->name('notices.destroy');
        Route::post('editais/{notice}/reprocessar', [BidNoticeController::class, 'reprocess'])
            ->middleware('throttle:10,1')->name('notices.reprocess');
        Route::post('editais/{notice}/recalcular', [BidNoticeController::class, 'reevaluate'])->name('notices.reevaluate');
        Route::get('editais/{notice}/matriz', [BidNoticeController::class, 'matrix'])->name('notices.matrix');
        Route::get('editais/{notice}/plano/{company}', [BidNoticeController::class, 'plan'])->name('notices.plan');

        // Correção humana sobre o que a IA extraiu
        Route::put('requisitos/{requirement}', [BidRequirementController::class, 'update'])->name('requirements.update');
        Route::put('conferencias/{match}', [BidRequirementController::class, 'updateMatch'])->name('matches.update');
        Route::delete('conferencias/{match}/override', [BidRequirementController::class, 'resetMatch'])->name('matches.reset');

        // Relatórios
        Route::get('relatorios', [BidReportController::class, 'index'])->name('reports.index');
        Route::get('relatorios/exportar', [BidReportController::class, 'export'])->name('reports.export');

        // Configurações do módulo (categorias, tipos de documento, ramos)
        Route::get('config', [BidSettingsController::class, 'index'])->name('settings.index');
        Route::post('config/categorias', [BidSettingsController::class, 'storeCategory'])->name('categories.store');
        Route::put('config/categorias/{category}', [BidSettingsController::class, 'updateCategory'])->name('categories.update');
        Route::delete('config/categorias/{category}', [BidSettingsController::class, 'destroyCategory'])->name('categories.destroy');
        Route::post('config/tipos', [BidSettingsController::class, 'storeType'])->name('types.store');
        Route::put('config/tipos/{type}', [BidSettingsController::class, 'updateType'])->name('types.update');
        Route::delete('config/tipos/{type}', [BidSettingsController::class, 'destroyType'])->name('types.destroy');
        Route::post('config/ramos', [BidSettingsController::class, 'storeLine'])->name('lines.store');
        Route::put('config/ramos/{line}', [BidSettingsController::class, 'updateLine'])->name('lines.update');
        Route::delete('config/ramos/{line}', [BidSettingsController::class, 'destroyLine'])->name('lines.destroy');
    });
});

// Captura rápida — Canal A (specs/16, Fases 2-3): as duas rotas abaixo ficam FORA do grupo `auth`
// padrão porque precisam aceitar identidade de formas que a rota normal não cobre.
//
// `captura/receber`: recebe o POST do Web Share Target (Android, sessão) OU do Atalho da Apple (iOS,
// token pessoal Sanctum, sem sessão de navegador) — por isso `auth:web,sanctum` (tenta sessão, senão
// token) em vez do `auth` simples do grupo acima. Isenta de CSRF (ver VerifyCsrfToken::$except) porque o
// POST é disparado pelo SO/Atalho, nunca por um form Blade.
Route::post('captura/receber', [CaptureController::class, 'receive'])
    ->middleware(['auth:web,sanctum', 'active', 'throttle:20,1'])
    ->name('captures.receive');

// `capturas/{capture}` (confirmação): o Atalho abre esta URL assinada no Safari sem sessão prévia —
// `auth` bloquearia a requisição antes mesmo do controller rodar a lógica de auto-login via assinatura,
// então a rota fica fora do grupo e a autorização real acontece dentro do controller (sessão OU
// assinatura válida). `active` é seguro aqui mesmo sem usuário: é um no-op para requests sem sessão.
Route::middleware('active')->group(function () {
    Route::get('capturas/{capture}', [CaptureController::class, 'show'])->name('captures.show');
});

require __DIR__.'/auth.php';
