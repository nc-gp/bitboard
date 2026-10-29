<?php

namespace App\Forum\Widgets;

use App\Classes\Database;
use App\Classes\SessionManager;
use App\Classes\Template;

use App\Forum\Controllers\ForumSubscriptionController;

/**
 * Watch/unwatch form for a forum or subforum.
 */
class WatchWidget
{
    public $template = '';

    public function __construct(
        Database $db,
        int $forumID,
        int $subforumID,
        int $page,
        string $type
    )
    {
        if (!SessionManager::IsLogged())
            return;

        $watching = ForumSubscriptionController::IsWatching(
            $db,
            (int) $_SESSION['bitboard_user']->id,
            $forumID,
            $subforumID
        );

        $template = new Template('forum', 'watch_button');
        $template->AddEscapedEntry('{csrf_token}', SessionManager::CsrfToken());
        $template->AddEscapedEntry('{forum_id}', $forumID);
        $template->AddEscapedEntry('{subforum_id}', $subforumID);
        $template->AddEscapedEntry('{page}', $page);
        $template->AddEscapedEntry('{action}', $watching ? 'unsubscribe' : 'subscribe');
        $template->AddEscapedEntry('{label}', $watching ? 'Watching' : 'Watch ' . $type);
        $template->AddEscapedEntry('{icon}', $watching ? 'bell-check' : 'bell-plus');
        $template->AddEscapedEntry('{state_class}', $watching ? ' is-active' : '');
        $template->Replace();

        $this->template = $template->template;
    }
}
