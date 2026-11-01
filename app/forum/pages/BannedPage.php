<?php

namespace App\Forum\Pages;

use App\Classes\PageBase;
use App\Classes\Database;
use App\Classes\Template;
use App\Classes\UrlManager;
use App\Classes\Permissions;
use App\Classes\SessionManager;
use App\Forum\Controllers\BanController;
use App\Interfaces\PageInterface;

class BannedPage extends PageBase implements PageInterface
{
    private array $ban = [];

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->forumDesc = 'You are banned!';
        $this->UrlHandler();
    }

    private function UrlHandler()
    {
        if(!SessionManager::IsLogged() || !isset($_SESSION['bitboard_user']))
            UrlManager::Redirect(UrlManager::GetPath());

        $this->ban = BanController::GetActiveForUser($this->database, (int) $_SESSION['bitboard_user']->id);

        // Legacy Banned-rank accounts have no record but also no forum permission.
        if(empty($this->ban) && SessionManager::HasPermission(Permissions::VIEWING_FORUM))
            UrlManager::Redirect(UrlManager::GetPath());
    }

    public function Do()
    {
        $hasRecord = !empty($this->ban);
        $expiresAt = $hasRecord ? $this->ban['expires_at'] : null;

        $this->template = new Template('banned', 'banned');
        $this->template->AddEscapedEntry('{reason}', $hasRecord
            ? $this->ban['reason']
            : 'Your account does not have access to this forum.');
        $this->template->AddEscapedEntry('{expiry}', $hasRecord
            ? BanController::ExpiryLabel(is_string($expiresAt) ? $expiresAt : null)
            : 'Contact a staff member for details.');
        $this->template->AddEscapedEntry('{issuer}', $hasRecord
            ? ($this->ban['issuer_name'] ?: 'Staff')
            : 'Staff');
        $this->template->AddEscapedEntry('{logout_url}', $this->serverPath . 'logout');
        parent::RenderPage('banned');
    }
}

?>
