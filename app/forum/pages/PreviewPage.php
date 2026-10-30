<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\HtmlSanitizer;
use App\Classes\PageBase;
use App\Classes\PageNotFoundException;
use App\Classes\SessionManager;
use App\Classes\Text;
use App\Classes\UrlManager;

use App\Forum\Controllers\MentionController;

use App\Interfaces\PageInterface;

/**
 * JSON endpoint that renders a Markdown preview with the same pipeline used
 * when a post is saved.
 */
class PreviewPage extends PageBase implements PageInterface
{
    private const MAX_CONTENT_LENGTH = 4000;

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->forumDesc = 'Preview';
        $this->UrlHandler();
    }

    private function Json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function UrlHandler(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST')
            throw new PageNotFoundException();

        if (!SessionManager::IsLogged())
        {
            if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest')
                $this->Json(['error' => 'login_required'], 401);

            UrlManager::Redirect($this->serverPath . 'login');
        }

        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!SessionManager::ValidateCsrfToken($token) && !SessionManager::ValidateCsrfToken($_POST['csrf_token'] ?? ''))
            $this->Json(['error' => 'invalid_request'], 403);

        $content = isset($_POST['content']) && is_string($_POST['content'])
            ? trim($_POST['content'])
            : '';

        if (mb_strlen($content) > self::MAX_CONTENT_LENGTH)
            $this->Json(['error' => 'too_long', 'message' => 'Your message is too long (maximum 4000 characters).'], 422);

        $html = HtmlSanitizer::Sanitize(Text::ToHtml($content));
        $html = MentionController::Link($this->database, $html);

        $this->Json(['html' => $html]);
    }

    public function Do(): void
    {
        // The preview endpoint responds during construction.
    }
}
