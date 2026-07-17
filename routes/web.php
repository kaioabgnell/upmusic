<?php

use App\Http\Controllers\BoardColumnController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\BoardFieldController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExternalFormController;
use App\Http\Controllers\FinancialEntryController;
use App\Http\Controllers\FinancialPlanController;
use App\Http\Controllers\FinancialReportController;
use App\Http\Controllers\FornecedorCategoriaController;
use App\Http\Controllers\FornecedorController;
use App\Http\Controllers\PriceCategoriaController;
use App\Http\Controllers\PriceHistoryController;
use App\Http\Controllers\PriceRecordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicFormController;
use App\Http\Controllers\SetorController;
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

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

    // Usuários (Admin/Coordenador) — ver specs/04.
    Route::resource('usuarios', UserController::class)
        ->parameters(['usuarios' => 'user'])
        ->names('users')
        ->except('show')
        ->middleware('role:admin,coordenador');

    // Empresas — busca e cadastro inline disponíveis a qualquer autenticado (fluxo do card).
    Route::get('empresas/buscar', [EmpresaController::class, 'search'])->name('empresas.search');
    Route::post('empresas/quick', [EmpresaController::class, 'quick'])->name('empresas.quick');

    // Fornecedores — cadastro inline disponível a qualquer autenticado (fluxo do card).
    Route::post('fornecedores/quick', [FornecedorController::class, 'quick'])->name('fornecedores.quick');

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

        // Colunas (JSON)
        Route::post('quadros/{board}/colunas', [BoardColumnController::class, 'store'])->name('columns.store');
        Route::put('colunas/{column}', [BoardColumnController::class, 'update'])->name('columns.update');
        Route::delete('colunas/{column}', [BoardColumnController::class, 'destroy'])->name('columns.destroy');
        Route::post('quadros/{board}/colunas/reordenar', [BoardColumnController::class, 'reorder'])->name('columns.reorder');

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
    Route::post('cards/{card}/comentarios', [CardController::class, 'storeComment'])->name('cards.comments.store');
    Route::post('cards/{card}/anexos', [CardController::class, 'storeAttachment'])->name('cards.attachments.store');
    Route::delete('anexos/{attachment}', [CardController::class, 'destroyAttachment'])->name('cards.attachments.destroy');
    Route::get('anexos/{attachment}/download', [CardController::class, 'downloadAttachment'])->name('cards.attachments.download');

    // Importar template — qualquer usuário com acesso ao quadro (autorizado no controller).
    Route::post('templates/{template}/importar', [TemplateController::class, 'import'])->name('templates.import');

    // Banco de preços por categoria — leitura liberada a qualquer autenticado. Ver specs/15.
    Route::get('precos/evolucao', [PriceHistoryController::class, 'index'])->name('prices.history');
    Route::get('precos/categorias', [PriceCategoriaController::class, 'index'])->name('prices.categorias.index');
    Route::get('precos/categorias/{fornecedorCategoria}', [PriceCategoriaController::class, 'show'])->name('prices.categorias.show');

    // Dados assíncronos do quadro (colunas + cards) — ver specs/14.
    Route::get('quadros/{board}/kanban', [BoardController::class, 'kanbanData'])->name('boards.kanban.data');

    // Wildcard de exibição por último para não capturar as rotas literais acima.
    Route::get('quadros/{board}', [BoardController::class, 'show'])->name('boards.show');

    // Cadastros base (Admin/Coordenador) — ver specs/05.
    Route::middleware('role:admin,coordenador')->group(function () {
        Route::resource('setores', SetorController::class)
            ->parameters(['setores' => 'setor'])->except('show');
        Route::resource('empresas', EmpresaController::class)->except('show');
        Route::resource('fornecedores', FornecedorController::class)
            ->parameters(['fornecedores' => 'fornecedor'])->except('show');
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

        // Planejamento financeiro — ver specs/09.
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
});

require __DIR__.'/auth.php';
