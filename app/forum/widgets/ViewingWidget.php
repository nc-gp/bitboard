<?php

namespace App\Forum\Widgets;

use App\Classes\AvatarUtils;
use App\Classes\Database;
use App\Classes\SessionManager;
use App\Classes\Template;
use App\Classes\UsernameUtils;

use App\Forum\Controllers\ViewingController;

/**
 * Shows which members are currently viewing a forum, subforum, or thread.
 */
class ViewingWidget
{
    private const MAX_USERS = 8;

    public $Template = '';

    /**
     * @param string $type The target type: forum, subforum, or thread.
     * @param int    $id   The target ID.
     */
    public function __construct(Database $db, string $type, int $id)
    {
        if (!SessionManager::IsLogged() || $id <= 0)
            return;

        $count = ViewingController::CountViewing($db, $type, $id);

        if ($count <= 0)
            return;

        $users = ViewingController::GetViewing($db, $type, $id, self::MAX_USERS);
        $userList = '';

        foreach ($users as $user)
        {
            $userTemplate = new Template('viewing', 'viewing_user');
            $userTemplate->AddEscapedEntry('{id}', $user['id']);
            $userTemplate->AddEntry('{avatar}', AvatarUtils::GetPath($user['avatar']));
            $userTemplate->AddEscapedEntry('{username_plain}', $user['username']);
            $userTemplate->AddEntry('{username}', UsernameUtils::Format($user['rank_format'], $user['username']));
            $userTemplate->Replace();

            $userList .= $userTemplate->template;
        }

        $more = '';

        if ($count > count($users))
        {
            $moreTemplate = new Template('viewing', 'viewing_more');
            $moreTemplate->AddEscapedEntry('{count}', $count - count($users));
            $moreTemplate->Replace();

            $more = $moreTemplate->template;
        }

        $noun = $type === 'forum' || $type === 'subforum' ? $type : 'thread';
        $label = $count === 1
            ? '1 member viewing this ' . $noun
            : $count . ' members viewing this ' . $noun;

        $template = new Template('viewing', 'viewing');
        $template->AddEscapedEntry('{viewing_label}', $label);
        $template->AddEntry('{viewing_users}', $userList);
        $template->AddEntry('{viewing_more}', $more);
        $template->Replace();

        $this->Template = $template->template;
    }
}
