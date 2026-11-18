<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Card;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $boards = Board::query()
            ->active()
            ->with('setor')
            // Contagem de cards por quadro respeita o escopo por evento do coordenador restrito (specs/20).
            ->withCount(['columns', 'cards' => fn ($q) => $q->whereNull('concluded_at')->visibleTo($user)])
            // Mesma régua do menu, com a regra dos quadros de custos fixos (specs/27 §1.2).
            ->visibleTo($user)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $boardIds = $boards->pluck('id');

        $dueTodayCards = Card::whereIn('board_id', $boardIds)
            ->whereNull('concluded_at')
            ->visibleTo($user)
            ->whereDate('due_date', today())
            ->with(['board:id,name', 'empresa:id,corporate_name', 'assignee:id,name'])
            ->orderByRaw("field(priority, 'alta', 'media', 'baixa')")
            ->get(['id', 'board_id', 'title', 'empresa_id', 'assignee_id', 'priority', 'due_date']);

        $recentCards = Card::whereIn('board_id', $boardIds)
            ->whereNull('concluded_at')
            ->visibleTo($user)
            ->with(['board:id,name', 'empresa:id,corporate_name', 'assignee:id,name'])
            ->latest('updated_at')
            ->limit(6)
            ->get(['id', 'board_id', 'title', 'empresa_id', 'assignee_id', 'priority', 'updated_at']);

        return view('dashboard', compact('boards', 'dueTodayCards', 'recentCards'));
    }
}
