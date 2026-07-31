<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Services\VisibleNotificationsQuery;
use App\Support\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Painel do sino (specs/22 §4.3). Tudo em JSON e sempre escopado ao usuário logado — nenhum
 * endpoint aceita `user_id` por parâmetro.
 */
class NotificationController extends Controller
{
    private const PER_PAGE = 10;

    public function __construct(private VisibleNotificationsQuery $visible) {}

    /**
     * Lista paginada por cursor (`antes=<id>`), 10 por vez. Cursor e não `?page=`: como novas
     * notificações entram no topo, o offset faria a página 2 repetir itens da página 1 durante o
     * scroll infinito. Com ordem por `id DESC`, `antes` é estável e usa o índice (user_id, id).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = $this->visible->for($user)
            ->when($request->query('filtro') === 'nao_lidas', fn ($q) => $q->unread())
            ->when($request->integer('antes'), fn ($q, $id) => $q->where('user_notifications.id', '<', $id))
            ->with('actor:id,name,avatar_path')
            ->orderByDesc('user_notifications.id')
            ->limit(self::PER_PAGE + 1)   // o 11º item só responde "tem mais?"
            ->get();

        $hasMore = $items->count() > self::PER_PAGE;
        $items = $items->take(self::PER_PAGE);

        return response()->json([
            'items' => $items->map(fn ($n) => NotificationPresenter::item($n))->values(),
            'next_cursor' => $hasMore ? $items->last()->id : null,
            'unread_count' => $this->unreadCount($request),
        ]);
    }

    /** Endpoint mínimo do polling do badge: devolve só o inteiro. */
    public function count(Request $request): JsonResponse
    {
        return response()->json(['unread_count' => $this->unreadCount($request)]);
    }

    public function read(Request $request, UserNotification $notification): JsonResponse
    {
        // Notificação é sempre pessoal: não há Policy nem exceção para admin.
        abort_unless($notification->user_id === $request->user()->id, 403);

        // Idempotente: já lida não reescreve o read_at.
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(['unread_count' => $this->unreadCount($request)]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->visible->for($request->user())->unread()->update(['read_at' => now()]);

        return response()->json(['unread_count' => $this->unreadCount($request)]);
    }

    private function unreadCount(Request $request): int
    {
        return $this->visible->for($request->user())->unread()->count();
    }
}
