<?php

namespace App\Http\Controllers\Bid;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bid\BidBusinessLineRequest;
use App\Http\Requests\Bid\BidDocumentCategoryRequest;
use App\Http\Requests\Bid\BidDocumentTypeRequest;
use App\Models\BidBusinessLine;
use App\Models\BidDocumentCategory;
use App\Models\BidDocumentType;

/**
 * Configurações do módulo (ver specs/21 §9.8): categorias, tipos canônicos e ramos de atuação.
 * Três cadastros pequenos em uma tela de abas — é onde a precisão do matcher é afinada.
 */
class BidSettingsController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', BidDocumentCategory::class);

        return view('licitacoes.config.index', [
            'categories' => BidDocumentCategory::query()->ordered()->withCount(['documents', 'types'])->get(),
            'types' => BidDocumentType::query()->with('category')->ordered()->withCount('documents')->get(),
            'lines' => BidBusinessLine::query()->with('companies:id')->orderBy('name')->get(),
        ]);
    }

    // Categorias ------------------------------------------------------------

    public function storeCategory(BidDocumentCategoryRequest $request)
    {
        BidDocumentCategory::create($request->validated() + ['system' => false]);

        return back()->with('success', 'Categoria criada.');
    }

    public function updateCategory(BidDocumentCategoryRequest $request, BidDocumentCategory $category)
    {
        $data = $request->validated();

        // Categoria nativa: renomeável, mas o slug permanece (contrato com a IA — §6.3).
        if ($category->system) {
            unset($data['slug']);
        }

        $category->update($data);

        return back()->with('success', 'Categoria atualizada.');
    }

    public function destroyCategory(BidDocumentCategory $category)
    {
        $this->authorize('delete', $category);

        if ($category->system) {
            return back()->with('error', 'As categorias nativas não podem ser excluídas — desative-a se não usar.');
        }

        if ($category->documents()->exists() || $category->types()->exists()) {
            return back()->with('error', 'Categoria com documentos ou tipos vinculados não pode ser excluída.');
        }

        $category->delete();

        return back()->with('success', 'Categoria excluída.');
    }

    // Tipos de documento ----------------------------------------------------

    public function storeType(BidDocumentTypeRequest $request)
    {
        BidDocumentType::create($request->validated());

        return back()->with('success', 'Tipo de documento criado.');
    }

    public function updateType(BidDocumentTypeRequest $request, BidDocumentType $type)
    {
        $type->update($request->validated());

        return back()->with('success', 'Tipo de documento atualizado.');
    }

    public function destroyType(BidDocumentType $type)
    {
        $this->authorize('delete', $type);

        if ($type->documents()->exists()) {
            return back()->with('error', 'Este tipo já classifica documentos do acervo e não pode ser excluído.');
        }

        $type->delete();

        return back()->with('success', 'Tipo de documento excluído.');
    }

    // Ramos de atuação ------------------------------------------------------

    public function storeLine(BidBusinessLineRequest $request)
    {
        BidBusinessLine::create($request->validated());

        return back()->with('success', 'Ramo de atuação criado.');
    }

    public function updateLine(BidBusinessLineRequest $request, BidBusinessLine $line)
    {
        $line->update($request->validated());

        return back()->with('success', 'Ramo de atuação atualizado.');
    }

    public function destroyLine(BidBusinessLine $line)
    {
        $this->authorize('delete', $line);

        if ($line->companies()->exists()) {
            return back()->with('error', 'Ramo vinculado a empresas não pode ser excluído.');
        }

        $line->delete();

        return back()->with('success', 'Ramo de atuação excluído.');
    }
}
