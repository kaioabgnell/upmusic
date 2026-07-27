<?php

namespace App\Http\Controllers\Bid;

use App\Http\Controllers\Controller;
use App\Models\BidCompany;
use App\Services\Bid\BidDashboardService;

/** Painel do módulo de Licitações (ver specs/21 §9.1). */
class BidDashboardController extends Controller
{
    public function index(BidDashboardService $dashboard)
    {
        $this->authorize('viewAny', BidCompany::class);

        return view('licitacoes.dashboard', [
            'counters' => $dashboard->counters(),
            'alerts' => $dashboard->alerts(),
            'health' => $dashboard->companiesHealth(),
            'notices' => $dashboard->recentNotices(),
        ]);
    }
}
