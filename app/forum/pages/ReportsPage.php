<?php

namespace App\Forum\Pages;

use Throwable;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\PageNotFoundException;
use App\Classes\Permissions;
use App\Classes\RelativeTime;
use App\Classes\SessionManager;
use App\Classes\Slug;
use App\Classes\Template;
use App\Classes\Text;
use App\Classes\UrlManager;
use App\Classes\UsernameUtils;

use App\Forum\Controllers\ContentController;
use App\Forum\Controllers\ReportController;
use App\Forum\Widgets\PaginationWidget;

use App\Interfaces\PageInterface;

class ReportsPage extends PageBase implements PageInterface
{
    private const STATUS_LABELS = [
        'open' => 'Open',
        'resolved' => 'Resolved',
        'dismissed' => 'Dismissed',
        'all' => 'All',
    ];

    private int $page = 1;
    private int $maximumResults = 20;
    private string $status = 'open';
    private int $total = 0;
    private array $counts = [];
    private string $reports = '';
    private string $error = '';

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->forumDesc = 'Reports';
        $this->UrlHandler();
    }

    private function Forbidden(): void
    {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Forbidden';
        exit;
    }

    private function UrlHandler(): void
    {
        if (!SessionManager::IsLogged())
            UrlManager::Redirect($this->serverPath . 'login');

        if (!SessionManager::HasPermission(Permissions::VIEWING_REPORTS))
            $this->Forbidden();

        $parameters = $this->forumData->actionParameters;

        if (count($parameters) === 3 && ($parameters[1] ?? '') === 'page' && ctype_digit((string) ($parameters[2] ?? '')))
            $this->page = max(1, (int) $parameters[2]);
        elseif (count($parameters) !== 1)
            throw new PageNotFoundException();

        $source = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? $_POST : $_GET;
        $status = isset($source['status']) && is_string($source['status'])
            ? $source['status']
            : (isset($source['status_filter']) && is_string($source['status_filter']) ? $source['status_filter'] : 'open');

        if (array_key_exists($status, self::STATUS_LABELS))
            $this->status = $status;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
            $this->HandleSubmission();

        $this->canonicalUrl = $this->serverPath . 'reports/page/' . $this->page . ($this->status !== 'open' ? '?status=' . $this->status : '');
    }

    private function HandleSubmission(): void
    {
        if (!SessionManager::ValidateCsrfToken($_POST['csrf_token'] ?? ''))
        {
            $this->error = 'Invalid request. Please try again.';
            return;
        }

        $reportID = isset($_POST['report_id']) && is_string($_POST['report_id']) && ctype_digit($_POST['report_id'])
            ? (int) $_POST['report_id']
            : 0;
        $decision = isset($_POST['decision']) && is_string($_POST['decision']) ? $_POST['decision'] : '';
        $note = isset($_POST['note']) && is_string($_POST['note']) ? trim($_POST['note']) : '';
        $filter = isset($_POST['status_filter']) && is_string($_POST['status_filter']) ? $_POST['status_filter'] : 'open';
        $page = isset($_POST['page']) && is_string($_POST['page']) && ctype_digit($_POST['page'])
            ? max(1, (int) $_POST['page'])
            : 1;

        if (!array_key_exists($filter, self::STATUS_LABELS))
            $filter = 'open';

        if ($reportID <= 0 || !in_array($decision, ['resolved', 'dismissed'], true))
        {
            $this->error = 'Invalid report action.';
            return;
        }

        if (mb_strlen($note) > 1000)
        {
            $this->error = 'The moderator note must be 1000 characters or fewer.';
            return;
        }

        $report = ReportController::GetByID($this->database, $reportID);
        if (empty($report) || $report['status'] !== 'open')
        {
            $this->error = 'That report is no longer open.';
            return;
        }

        try
        {
            ReportController::Resolve(
                $this->database,
                $reportID,
                (int) $_SESSION['bitboard_user']->id,
                $decision,
                $note
            );

            UrlManager::Redirect($this->serverPath . 'reports/page/' . $page . '?status=' . $filter);
        }
        catch (Throwable $e)
        {
            error_log('BitBoard report update failed: ' . $e->getMessage());
            $this->error = 'Unable to update this report. Please try again.';
        }
    }

    private function BuildTabs(): string
    {
        $tabs = '';

        foreach (self::STATUS_LABELS as $value => $label)
        {
            $count = (int) ($this->counts[$value] ?? 0);
            $active = $this->status === $value ? ' is-active' : '';
            $tabs .= '<a class="report-tab' . $active . '" href="'
                . $this->serverPath . 'reports/page/1?status=' . $value . '">'
                . Template::Escape($label)
                . '<span class="report-tab-count">' . $count . '</span></a>';
        }

        return $tabs;
    }

    private function BuildTargetUrl(array $report): string
    {
        if ((int) $report['post_id'] > 0)
        {
            $page = ContentController::PostPageNumber($this->database, [
                'thread_id' => (int) $report['thread_id'],
                'post_timestamp' => $report['post_timestamp'],
                'id' => (int) $report['post_id'],
            ]);

            return UrlManager::ThreadUrl(
                (int) $report['thread_id'],
                Slug::FromTitle($report['thread_title']),
                $page
            ) . '#post-' . (int) $report['post_id'];
        }

        return UrlManager::ThreadUrl(
            (int) $report['thread_id'],
            Slug::FromTitle($report['thread_title']),
            1
        );
    }

    private function BuildActions(array $report): string
    {
        if ($report['status'] !== 'open')
            return '';

        $template = new Template('reports', 'actions');
        $template->AddEscapedEntry('{report_id}', $report['id']);
        $template->AddEscapedEntry('{page}', $this->page);
        $template->AddEscapedEntry('{status_filter}', $this->status);
        $template->AddEscapedEntry('{csrf_token}', SessionManager::CsrfToken());
        $template->Replace();

        return $template->template;
    }

    private function BuildResolution(array $report): string
    {
        if ($report['status'] === 'open')
            return '';

        $by = $report['resolver_name'] ?? '';
        $time = $report['resolved_at'] ? RelativeTime::Convert($report['resolved_at']) : '';
        $label = $report['status'] === 'resolved' ? 'Resolved' : 'Dismissed';

        if ($by !== '')
            return $label . ' by ' . $by . ($time !== '' ? ' ' . $time : '');

        return $label . ($time !== '' ? ' ' . $time : '');
    }

    private function FetchReports(): void
    {
        $this->counts = ReportController::CountsByStatus($this->database);
        $this->total = ReportController::CountReports($this->database, $this->status);

        if ($this->total <= 0)
        {
            $empty = new Template('reports', 'empty');
            $empty->AddEscapedEntry('{status}', self::STATUS_LABELS[$this->status]);
            $this->reports = $empty->template;

            return;
        }

        $totalPages = max(1, (int) ceil($this->total / $this->maximumResults));

        if ($this->page > $totalPages)
            UrlManager::Redirect($this->serverPath . 'reports/page/' . $totalPages . '?status=' . $this->status);

        $rows = ReportController::GetReports(
            $this->database,
            $this->status,
            ($this->page - 1) * $this->maximumResults,
            $this->maximumResults
        );

        foreach ($rows as $report)
        {
            $reason = ReportController::REASONS[$report['reason']] ?? $report['reason'];
            $statusLabel = self::STATUS_LABELS[$report['status']] ?? ucfirst($report['status']);
            $targetTitle = $report['thread_title'];
            $excerpt = '';

            if ((int) $report['post_id'] > 0)
            {
                $excerpt = Text::Excerpt((string) $report['post_content'], 180);
                if ($report['post_author'])
                    $excerpt = 'Reply by ' . $report['post_author'] . ': ' . $excerpt;
            }

            $item = new Template('reports', 'report');
            $item->AddEscapedEntry('{report_id}', $report['id']);
            $item->AddEscapedEntry('{status_class}', ' is-' . $report['status']);
            $item->AddEscapedEntry('{status_label}', $statusLabel);
            $item->AddEscapedEntry('{target_type}', (int) $report['post_id'] > 0 ? 'Reply' : 'Thread');
            $item->AddEscapedEntry('{target_url}', $this->BuildTargetUrl($report));
            $item->AddEscapedEntry('{target_title}', $targetTitle);
            $item->AddEscapedEntry('{target_excerpt}', $excerpt);
            $item->AddEntry('{reporter}', UsernameUtils::Format($report['reporter_rank'], $report['reporter_name']));
            $item->AddEscapedEntry('{created}', RelativeTime::Convert($report['created_at']));
            $item->AddEscapedEntry('{reason}', $reason);
            $item->AddEscapedEntry('{details}', $report['details']);
            $item->AddEntry('{actions}', $this->BuildActions($report));
            $item->AddEscapedEntry('{note}', $report['note']);
            $item->AddEscapedEntry('{resolution}', $this->BuildResolution($report));
            $item->Replace();

            $this->reports .= $item->template;
        }
    }

    private function BuildError(): string
    {
        if ($this->error === '')
            return '';

        $template = new Template('post', 'error');
        $template->AddEscapedEntry('{message}', $this->error);
        $template->Replace();

        return $template->template;
    }

    public function Do(): void
    {
        $this->FetchReports();

        $summary = $this->status === 'all'
            ? ($this->total === 1 ? '1 report' : $this->total . ' reports')
            : ($this->total === 1
                ? '1 ' . strtolower(self::STATUS_LABELS[$this->status]) . ' report'
                : $this->total . ' ' . strtolower(self::STATUS_LABELS[$this->status]) . ' reports');

        $pagination = new PaginationWidget(
            $this->page,
            $this->total,
            $this->maximumResults,
            'reports/page/',
            ['status' => $this->status]
        );

        $this->template = new Template('reports', 'reports');
        $this->template->AddEntry('{tabs}', $this->BuildTabs());
        $this->template->AddEscapedEntry('{summary}', $summary);
        $this->template->AddEntry('{error}', $this->BuildError());
        $this->template->AddEntry('{reports}', $this->reports);
        $this->template->AddEntry('{pagination}', $this->total > $this->maximumResults ? $pagination->Template->template : '');

        parent::RenderPage('reports');
    }
}

?>
