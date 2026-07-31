<?php

namespace App\View\Composers;

use App\Services\VisibleNotificationsQuery;
use Illuminate\View\View;

/**
 * Injeta o total de não lidas no componente do sino (specs/22 §5.1), para que o badge já venha
 * correto no HTML renderizado em vez de piscar em 0 até o primeiro fetch do Alpine.
 */
class NotificationComposer
{
    public function __construct(private VisibleNotificationsQuery $visible) {}

    public function compose(View $view): void
    {
        $user = auth()->user();

        $view->with('unreadCount', $user
            ? $this->visible->for($user)->unread()->count()
            : 0);
    }
}
