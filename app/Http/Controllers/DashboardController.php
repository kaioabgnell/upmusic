<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Card;
use App\Models\Empresa;
use App\Services\FinancialReportService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, FinancialReportService $reportService)
    {
        $user = $request->user();
        $isManager = $user->isAdmin() || $user->isCoordenador();

        $boards = Board::query()
            ->active()
            ->with('setor')
            ->withCount(['columns', 'cards' => fn ($q) => $q->whereNull('concluded_at')])
            ->when(! $isManager, fn ($q) => $q->whereHas('users', fn ($q) => $q->whereKey($user->id)))
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $boardIds = $boards->pluck('id');

        $activeCards = Card::whereIn('board_id', $boardIds)
            ->whereNull('concluded_at')
            ->whereHas('column', fn ($q) => $q->where('is_final', false))
            ->count();

        $stats = [
            ['label' => 'Quadros', 'icon' => 'fa-table-columns', 'value' => (string) $boards->count()],
            ['label' => 'Empresas ativas', 'icon' => 'fa-building', 'value' => (string) Empresa::active()->count()],
            ['label' => 'Cards ativos', 'icon' => 'fa-clipboard-list', 'value' => (string) $activeCards],
        ];

        if ($isManager) {
            $summary = $reportService->summary(['year' => now()->year, 'month' => now()->month]);
            $stats[] = [
                'label' => 'Realizado no mês',
                'icon' => 'fa-chart-line',
                'value' => 'R$ '.number_format($summary['actual'], 2, ',', '.'),
            ];
        } else {
            $myCards = Card::where('assignee_id', $user->id)
                ->whereNull('concluded_at')
                ->whereHas('column', fn ($q) => $q->where('is_final', false))
                ->count();
            $stats[] = ['label' => 'Meus cards', 'icon' => 'fa-user-check', 'value' => (string) $myCards];
        }

        $recentCards = Card::whereIn('board_id', $boardIds)
            ->whereNull('concluded_at')
            ->with(['board:id,name', 'empresa:id,corporate_name', 'assignee:id,name'])
            ->latest('updated_at')
            ->limit(6)
            ->get(['id', 'board_id', 'title', 'empresa_id', 'assignee_id', 'priority', 'updated_at']);

        return view('dashboard', compact('stats', 'boards', 'recentCards', 'isManager'));
    }
}
