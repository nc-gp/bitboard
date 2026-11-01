<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\PageNotFoundException;
use App\Classes\Permissions;
use App\Classes\SessionManager;
use App\Forum\Controllers\AttachmentController;
use App\Interfaces\PageInterface;

/**
 * Streams a private attachment after checking forum visibility.
 */
class AttachmentPage extends PageBase implements PageInterface
{
    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->forumDesc = 'Attachment';
    }

    public function Do(): void
    {
        $parameters = $this->forumData->actionParameters;
        $attachmentID = isset($parameters[1]) && is_string($parameters[1]) && ctype_digit($parameters[1])
            ? (int) $parameters[1]
            : 0;

        if ($attachmentID <= 0)
            throw new PageNotFoundException();

        $attachment = AttachmentController::GetByID($this->database, $attachmentID);

        if (empty($attachment))
            throw new PageNotFoundException();

        $thread = $this->database->Query('SELECT t.id, t.forum_id,
                f.is_locked AS forum_locked,
                sf.is_locked AS subforum_locked
            FROM bit_threads AS t
            JOIN bit_forums AS f ON f.id = t.forum_id
            LEFT JOIN bit_subforums AS sf ON sf.id = t.subforum_id
            WHERE t.id = ?', (int) $attachment['thread_id'])->FetchArray();

        if (empty($thread))
            throw new PageNotFoundException();

        $locked = (int) $thread['forum_locked'] === 1 || (int) ($thread['subforum_locked'] ?? 0) === 1;

        if ($locked && !SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED))
            throw new PageNotFoundException();

        if ((int) $attachment['post_id'] > 0)
        {
            $post = $this->database->Query('SELECT id FROM bit_posts
                WHERE id = ? AND thread_id = ?',
                (int) $attachment['post_id'],
                (int) $attachment['thread_id']
            )->FetchArray();

            if (empty($post))
                throw new PageNotFoundException();
        }

        if (!AttachmentController::Send($attachment))
            throw new PageNotFoundException();
    }
}
