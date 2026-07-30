<?php

namespace App\Http\Controllers;

use App\Services\ConsultaCnpjException;
use App\Services\ConsultaCnpjService;

class CnpjLookupController extends Controller
{
    /**
     * Preenchimento automático da razão social nos cadastros rápidos de empresa/fornecedor
     * (ver card-panel.js `quickFornecedor`).
     */
    public function show(string $cnpj, ConsultaCnpjService $service)
    {
        try {
            return response()->json($service->find($cnpj));
        } catch (ConsultaCnpjException $e) {
            return response()->json(['message' => $e->getMessage()], $e->httpStatus());
        }
    }
}
