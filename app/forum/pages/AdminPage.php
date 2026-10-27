<?php

namespace App\Forum\Pages;

use App\Classes\Console;
use App\Classes\PageBase;
use App\Classes\Database;
use App\Classes\Permissions;
use App\Classes\SessionManager;
use App\Classes\UrlManager;
use App\Interfaces\PageInterface;
use App\Classes\Template;
use App\Forum\Controllers\CategoryController;
use App\Forum\Controllers\RankController;

use App\Forum\Widgets\NotifyWidget;

use Throwable;

class AdminPage extends PageBase implements PageInterface
{
    private $content = '';
    private string $error = '';

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->UrlHandler();
        $this->forumDesc = 'Admin panel';
    }

    private function CheckError()
    {
        if(!isset($_SESSION['bb-info-settings']) || !is_array($_SESSION['bb-info-settings']))
            return;

        $message = $_SESSION['bb-info-settings']['msg'] ?? '';
        $rgb = $_SESSION['bb-info-settings']['rgb'] ?? '255, 53, 53';
        $errorTemplate = new NotifyWidget($message, $rgb);
        $this->error = $errorTemplate->template;

        SessionManager::RemoveInformation('settings');
    }

    private function UrlHandler()
    {
        if(!SessionManager::IsLogged() || !$_SESSION['bitboard_user']->HasPermission(Permissions::ADMIN_PANEL_ACCESS))
            UrlManager::Redirect($this->serverPath);

        $option = isset($this->forumData->actionParameters[1]) ? $this->forumData->actionParameters[1] : 0;

        switch($option)
        {
            case 'settings':
            {
                $this->content = new Template('admin/main', 'settings');
                $this->content->AddEscapedEntry('{forum_name}', $this->forumData->forum_name);
                $this->content->AddEscapedEntry('{forum_desc}', $this->forumData->forum_description);
                $this->content->AddEntry('{forum_online}', $this->forumData->forum_online ? 'checked' : '');
                $this->content->AddEscapedEntry('{forum_online_msg}', $this->forumData->forum_online_msg);
                $this->content->AddEntry('{forum_force_login}', $this->forumData->forum_force_login ? 'checked' : '');
                $this->content->AddEntry('{forum_credits}', $this->forumData->forum_credits ? 'checked' : '');
                $this->content->Replace();
                break;
            }
            case 'prefixes':
            {
                $prefixes = new Template('admin/main/prefixes', 'empty');
                $prefixes = $prefixes->template;
                $data = $this->database->Query('SELECT * FROM bit_prefixes WHERE id > 2')->FetchAll();

                if(!empty($data))
                {
                    $prefixes = '';
                    $prefix = '';
                    foreach ($data as $prefix_data) 
                    {
                        $prefix = new Template('admin/main/prefixes', 'prefix');
                        $prefix->AddEscapedEntry('{prefix_name}', $prefix_data['prefix_name']);
                        $prefix->AddEscapedEntry('{prefix_class}', $prefix_data['prefix_class']);
                        $prefix->AddEscapedEntry('{prefix_id}', $prefix_data['id']);
                        $prefix->Replace();
                        $prefixes .= $prefix->template;
                    }
                }

                $this->content = new Template('admin/main', 'prefixes');
                $this->content->AddEntry('{prefixes}', $prefixes);
                $this->content->Replace();
                break;
            }
            case 'categories':
            {
                if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
                    $this->HandleCategorySubmission();

                $this->content = $this->BuildCategories();
                break;
            }
            default:
            {
                $this->content = new Template('admin/main', 'home');
                $this->content->AddEscapedEntry('{username}', $_SESSION['bitboard_user']->username);
                $this->content->AddEscapedEntry('{rankname}', RankController::GetRankNameByID($this->database, $_SESSION['bitboard_user']->rank_id));
                $this->content->Replace();
                break;
            }
        }

        if ($this->content instanceof Template)
            $this->content = $this->content->template;
    }

    private function CategoryError(string $message): void
    {
        $this->error = (new NotifyWidget($message, '255, 53, 53'))->template;
    }

    private function HandleCategorySubmission(): void
    {
        if (!SessionManager::ValidateCsrfToken($_POST['csrf_token'] ?? ''))
        {
            $this->CategoryError('Invalid request. Please try again.');
            return;
        }

        $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
        $categoryID = isset($_POST['category_id']) && is_string($_POST['category_id']) && ctype_digit($_POST['category_id'])
            ? (int) $_POST['category_id']
            : 0;

        try
        {
            if ($action === 'delete')
            {
                if ($categoryID <= 0 || empty(CategoryController::GetByID($this->database, $categoryID)))
                {
                    $this->CategoryError('That category could not be found.');
                    return;
                }

                if (CategoryController::CountForums($this->database, $categoryID) > 0)
                {
                    $this->CategoryError('Move or delete the forums in this category first.');
                    return;
                }

                CategoryController::Delete($this->database, $categoryID);
                UrlManager::Redirect($this->serverPath . 'admin/categories');
            }

            if ($action !== 'create' && $action !== 'edit')
            {
                $this->CategoryError('Invalid category action.');
                return;
            }

            $name = isset($_POST['category_name']) && is_string($_POST['category_name'])
                ? trim($_POST['category_name'])
                : '';
            $description = isset($_POST['category_desc']) && is_string($_POST['category_desc'])
                ? trim($_POST['category_desc'])
                : '';
            $icon = isset($_POST['category_icon']) && is_string($_POST['category_icon'])
                ? trim($_POST['category_icon'])
                : '';
            $position = isset($_POST['category_position']) && is_string($_POST['category_position']) && ctype_digit($_POST['category_position'])
                ? (int) $_POST['category_position']
                : 0;

            if (mb_strlen($name) < 1 || mb_strlen($name) > 128)
            {
                $this->CategoryError('Category name must be between 1 and 128 characters.');
                return;
            }

            if (mb_strlen($description) > 256)
            {
                $this->CategoryError('Category description must be 256 characters or fewer.');
                return;
            }

            if ($icon !== '' && !preg_match('/^[a-z0-9-]{1,64}$/i', $icon))
            {
                $this->CategoryError('Icon must be a Tabler icon name such as “world” or “users”.');
                return;
            }

            if ($action === 'edit')
            {
                if ($categoryID <= 0 || empty(CategoryController::GetByID($this->database, $categoryID)))
                {
                    $this->CategoryError('That category could not be found.');
                    return;
                }

                CategoryController::Update($this->database, $categoryID, $name, $description, $icon, $position);
            }
            else
            {
                CategoryController::Create($this->database, $name, $description, $icon, $position);
            }

            UrlManager::Redirect($this->serverPath . 'admin/categories');
        }
        catch (Throwable $e)
        {
            error_log('BitBoard category update failed: ' . $e->getMessage());
            $this->CategoryError('Unable to save this category. Please try again.');
        }
    }

    private function BuildCategories(): string
    {
        $categories = CategoryController::GetAll($this->database);
        $counts = CategoryController::GetForumCounts($this->database);
        $rows = '';

        foreach ($categories as $category)
        {
            $icon = (string) $category['category_icon'];
            $preview = $icon !== ''
                ? '<i class="ti ti-' . Template::Escape($icon) . '" aria-hidden="true"></i>'
                : '<i class="ti ti-category" aria-hidden="true"></i>';

            $row = new Template('admin/main/categories', 'category');
            $row->AddEscapedEntry('{category_id}', $category['id']);
            $row->AddEscapedEntry('{category_name}', $category['category_name']);
            $row->AddEscapedEntry('{category_desc}', $category['category_desc']);
            $row->AddEscapedEntry('{category_icon}', $category['category_icon']);
            $row->AddEscapedEntry('{category_position}', $category['category_position']);
            $row->AddEscapedEntry('{forum_count}', $counts[(int) $category['id']] ?? 0);
            $row->AddEntry('{icon_preview}', $preview);
            $row->Replace();
            $rows .= $row->template;
        }

        if ($rows === '')
        {
            $empty = new Template('admin/main/categories', 'empty');
            $rows = $empty->template;
        }

        $content = new Template('admin/main', 'categories');
        $content->AddEscapedEntry('{next_position}', CategoryController::NextPosition($this->database));
        $content->AddEntry('{categories}', $rows);
        $content->Replace();

        return $content->template;
    }

    public function Do()
    {
        $this->CheckError();
        $this->template = new Template('admin', 'admin');
        $this->template->AddEntry('{content}', $this->content);
        $this->template->AddEntry('{error}', $this->error);

        parent::RenderPage('admin');
    }
}

?>