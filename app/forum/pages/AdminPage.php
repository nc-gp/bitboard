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
use App\Forum\Controllers\ForumController;
use App\Forum\Controllers\RankController;
use App\Forum\Controllers\SubForumController;

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
            case 'forums':
            {
                if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
                    $this->HandleForumSubmission();

                $this->content = $this->BuildForums();
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

    private function AdminError(string $message): void
    {
        $this->error = (new NotifyWidget($message, '255, 53, 53'))->template;
    }

    private function HandleCategorySubmission(): void
    {
        if (!SessionManager::ValidateCsrfToken($_POST['csrf_token'] ?? ''))
        {
            $this->AdminError('Invalid request. Please try again.');
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
                    $this->AdminError('That category could not be found.');
                    return;
                }

                if (CategoryController::CountForums($this->database, $categoryID) > 0)
                {
                    $this->AdminError('Move or delete the forums in this category first.');
                    return;
                }

                CategoryController::Delete($this->database, $categoryID);
                UrlManager::Redirect($this->serverPath . 'admin/categories');
            }

            if ($action !== 'create' && $action !== 'edit')
            {
                $this->AdminError('Invalid category action.');
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
                $this->AdminError('Category name must be between 1 and 128 characters.');
                return;
            }

            if (mb_strlen($description) > 256)
            {
                $this->AdminError('Category description must be 256 characters or fewer.');
                return;
            }

            if ($icon !== '' && !preg_match('/^[a-z0-9-]{1,64}$/i', $icon))
            {
                $this->AdminError('Icon must be a Tabler icon name such as “world” or “users”.');
                return;
            }

            if ($action === 'edit')
            {
                if ($categoryID <= 0 || empty(CategoryController::GetByID($this->database, $categoryID)))
                {
                    $this->AdminError('That category could not be found.');
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
            $this->AdminError('Unable to save this category. Please try again.');
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

    private function PostedID(string $field): int
    {
        return isset($_POST[$field]) && is_string($_POST[$field]) && ctype_digit($_POST[$field])
            ? (int) $_POST[$field]
            : 0;
    }

    private function PostedString(string $field): string
    {
        return isset($_POST[$field]) && is_string($_POST[$field])
            ? trim($_POST[$field])
            : '';
    }

    private function HandleForumSubmission(): void
    {
        if (!SessionManager::ValidateCsrfToken($_POST['csrf_token'] ?? ''))
        {
            $this->AdminError('Invalid request. Please try again.');
            return;
        }

        $entity = $this->PostedString('entity');
        $action = $this->PostedString('action');

        if (!in_array($entity, ['forum', 'subforum'], true) || !in_array($action, ['create', 'edit', 'delete'], true))
        {
            $this->AdminError('Invalid forum action.');
            return;
        }

        try
        {
            if ($entity === 'forum')
                $this->HandleForumAction($action);
            else
                $this->HandleSubforumAction($action);
        }
        catch (Throwable $e)
        {
            error_log('BitBoard forum update failed: ' . $e->getMessage());
            $this->AdminError('Unable to save this forum. Please try again.');
        }
    }

    private function HandleForumAction(string $action): void
    {
        $forumID = $this->PostedID('forum_id');

        if ($action === 'delete')
        {
            if ($forumID <= 0 || empty(ForumController::GetByID($this->database, $forumID)))
            {
                $this->AdminError('That forum could not be found.');
                return;
            }

            if (ForumController::CountThreads($this->database, $forumID) > 0)
            {
                $this->AdminError('Move or delete the threads in this forum before deleting it.');
                return;
            }

            ForumController::Delete($this->database, $forumID);
            UrlManager::Redirect($this->serverPath . 'admin/forums');
        }

        $categoryID = $this->PostedID('category_id');
        $name = $this->PostedString('forum_name');
        $description = $this->PostedString('forum_desc');
        $icon = $this->PostedString('forum_icon');
        $position = $this->PostedID('forum_position');
        $isLocked = isset($_POST['is_locked']);

        if (mb_strlen($name) < 1 || mb_strlen($name) > 128)
        {
            $this->AdminError('Forum name must be between 1 and 128 characters.');
            return;
        }

        if (mb_strlen($description) > 1024)
        {
            $this->AdminError('Forum description must be 1024 characters or fewer.');
            return;
        }

        if ($position > 999999)
        {
            $this->AdminError('Forum position must be between 0 and 999999.');
            return;
        }

        if ($icon !== '' && !preg_match('/^[a-z0-9-]{1,64}$/i', $icon))
        {
            $this->AdminError('Icon must be a Tabler icon name such as “messages” or “world”.');
            return;
        }

        if ($categoryID <= 0 || empty(CategoryController::GetByID($this->database, $categoryID)))
        {
            $this->AdminError('Choose a valid category for this forum.');
            return;
        }

        if ($action === 'edit')
        {
            if ($forumID <= 0 || empty(ForumController::GetByID($this->database, $forumID)))
            {
                $this->AdminError('That forum could not be found.');
                return;
            }

            ForumController::Update($this->database, $forumID, $categoryID, $name, $description, $icon, $position, $isLocked);
        }
        else
        {
            ForumController::Create($this->database, $categoryID, $name, $description, $icon, $position, $isLocked);
        }

        UrlManager::Redirect($this->serverPath . 'admin/forums');
    }

    private function HandleSubforumAction(string $action): void
    {
        $subforumID = $this->PostedID('subforum_id');

        if ($action === 'delete')
        {
            if ($subforumID <= 0 || empty(SubForumController::GetByID($this->database, $subforumID)))
            {
                $this->AdminError('That subforum could not be found.');
                return;
            }

            if (SubForumController::CountThreads($this->database, $subforumID) > 0)
            {
                $this->AdminError('Move or delete the threads in this subforum before deleting it.');
                return;
            }

            SubForumController::Delete($this->database, $subforumID);
            UrlManager::Redirect($this->serverPath . 'admin/forums');
        }

        $name = $this->PostedString('subforum_name');
        $description = $this->PostedString('subforum_desc');
        $isLocked = isset($_POST['is_locked']);

        if (mb_strlen($name) < 1 || mb_strlen($name) > 128)
        {
            $this->AdminError('Subforum name must be between 1 and 128 characters.');
            return;
        }

        if (mb_strlen($description) > 256)
        {
            $this->AdminError('Subforum description must be 256 characters or fewer.');
            return;
        }

        if ($action === 'edit')
        {
            if ($subforumID <= 0 || empty(SubForumController::GetByID($this->database, $subforumID)))
            {
                $this->AdminError('That subforum could not be found.');
                return;
            }

            SubForumController::Update($this->database, $subforumID, $name, $description, $isLocked);
        }
        else
        {
            $forumID = $this->PostedID('forum_id');

            if ($forumID <= 0 || empty(ForumController::GetByID($this->database, $forumID)))
            {
                $this->AdminError('Choose a valid forum for this subforum.');
                return;
            }

            SubForumController::Create($this->database, $forumID, $name, $description, $isLocked);
        }

        UrlManager::Redirect($this->serverPath . 'admin/forums');
    }

    private function BuildCategoryOptions(array $categories, int $selectedID): string
    {
        $options = '';

        foreach ($categories as $category)
        {
            $categoryID = (int) $category['id'];
            $options .= '<option value="' . $categoryID . '"' . ($categoryID === $selectedID ? ' selected' : '') . '>'
                . Template::Escape($category['category_name']) . '</option>';
        }

        return $options;
    }

    private function CountLabel(int $count, string $singular): string
    {
        return $count . ' ' . $singular . ($count === 1 ? '' : 's');
    }

    private function BuildSubforumRow(array $subforum): string
    {
        $row = new Template('admin/main/forums', 'subforum');
        $row->AddEscapedEntry('{subforum_id}', $subforum['id']);
        $row->AddEscapedEntry('{subforum_name}', $subforum['subforum_name']);
        $row->AddEscapedEntry('{subforum_desc}', $subforum['subforum_desc']);
        $row->AddEntry('{locked_checked}', (int) $subforum['is_locked'] === 1 ? 'checked' : '');
        $row->Replace();

        return $row->template;
    }

    private function BuildForumRow(array $forum, array $subforums, array $categories): string
    {
        $icon = trim((string) $forum['forum_icon']);
        $iconName = $icon !== '' ? $icon : 'messages';
        $isLocked = (int) $forum['is_locked'] === 1;

        $subforumRows = '';
        foreach ($subforums as $subforum)
            $subforumRows .= $this->BuildSubforumRow($subforum);

        $create = new Template('admin/main/forums', 'subforum_create');
        $create->AddEscapedEntry('{forum_id}', $forum['id']);
        $create->Replace();

        $row = new Template('admin/main/forums', 'forum');
        $row->AddEscapedEntry('{forum_id}', $forum['id']);
        $row->AddEscapedEntry('{forum_name}', $forum['forum_name']);
        $row->AddEscapedEntry('{forum_desc}', $forum['forum_desc']);
        $row->AddEscapedEntry('{forum_icon}', $forum['forum_icon']);
        $row->AddEscapedEntry('{forum_position}', $forum['forum_position']);
        $row->AddEscapedEntry('{thread_label}', $this->CountLabel((int) $forum['thread_count'], 'thread'));
        $row->AddEscapedEntry('{subforum_label}', $this->CountLabel((int) $forum['subforum_count'], 'subforum'));
        $row->AddEntry('{icon_preview}', '<i class="ti ti-' . Template::Escape($iconName) . '" aria-hidden="true"></i>');
        $row->AddEntry('{locked_badge}', $isLocked ? '<span class="admin-forum-locked">Locked</span>' : '');
        $row->AddEntry('{locked_checked}', $isLocked ? 'checked' : '');
        $row->AddEntry('{category_options}', $this->BuildCategoryOptions($categories, (int) $forum['category_id']));
        $row->AddEntry('{subforums}', $subforumRows);
        $row->AddEntry('{subforum_create}', $create->template);
        $row->Replace();

        return $row->template;
    }

    private function BuildForums(): string
    {
        $categories = CategoryController::GetAll($this->database);
        $forums = ForumController::GetAll($this->database);
        $subforums = SubForumController::GetAll($this->database);

        $subforumsByForum = [];
        foreach ($subforums as $subforum)
            $subforumsByForum[(int) $subforum['forum_id']][] = $subforum;

        $forumsByCategory = [];
        foreach ($forums as $forum)
            $forumsByCategory[(int) $forum['category_id']][] = $forum;

        if (empty($categories))
        {
            $create = (new Template('admin/main/forums', 'no_categories'))->template;
        }
        else
        {
            $create = new Template('admin/main/forums', 'create');
            $create->AddEntry('{category_options}', $this->BuildCategoryOptions($categories, (int) $categories[0]['id']));
            $create->Replace();
            $create = $create->template;
        }

        $emptyForumRow = (new Template('admin/main/forums', 'empty'))->template;
        $groups = '';

        foreach ($categories as $category)
        {
            $categoryID = (int) $category['id'];
            $icon = trim((string) $category['category_icon']);

            if ($icon === '')
                $icon = 'category';

            $rows = '';
            foreach ($forumsByCategory[$categoryID] ?? [] as $forum)
                $rows .= $this->BuildForumRow($forum, $subforumsByForum[(int) $forum['id']] ?? [], $categories);

            $group = new Template('admin/main/forums', 'group');
            $group->AddEscapedEntry('{category_icon}', $icon);
            $group->AddEscapedEntry('{category_name}', $category['category_name']);
            $group->AddEntry('{forums}', $rows !== '' ? $rows : $emptyForumRow);
            $group->Replace();
            $groups .= $group->template;
        }

        $content = new Template('admin/main', 'forums');
        $content->AddEntry('{create_forum}', $create);
        $content->AddEntry('{groups}', $groups);
        $content->Replace();

        return $content->template;
    }

    private function BuildAdminNav(): string
    {
        $sections = [
            'home' => ['Dashboard', 'ti-home', 'admin'],
            'settings' => ['Settings', 'ti-settings', 'admin/settings'],
            'categories' => ['Categories', 'ti-category', 'admin/categories'],
            'forums' => ['Forums', 'ti-messages', 'admin/forums'],
            'prefixes' => ['Prefixes', 'ti-tag', 'admin/prefixes'],
        ];

        $option = isset($this->forumData->actionParameters[1]) && is_string($this->forumData->actionParameters[1])
            ? $this->forumData->actionParameters[1]
            : '';
        $current = array_key_exists($option, $sections) ? $option : 'home';
        $items = '';

        foreach ($sections as $key => [$label, $icon, $route])
        {
            $item = new Template('admin', 'nav_item');
            $item->AddEscapedEntry('{url}', $this->serverPath . $route);
            $item->AddEscapedEntry('{label}', $label);
            $item->AddEscapedEntry('{icon}', $icon);
            $item->AddEscapedEntry('{active_class}', $key === $current ? ' is-active' : '');
            $item->Replace();
            $items .= $item->template;
        }

        $nav = new Template('admin', 'nav');
        $nav->AddEntry('{items}', $items);
        $nav->Replace();

        return $nav->template;
    }

    public function Do()
    {
        $this->CheckError();
        $this->template = new Template('admin', 'admin');
        $this->template->AddEntry('{admin_nav}', $this->BuildAdminNav());
        $this->template->AddEntry('{content}', $this->content);
        $this->template->AddEntry('{error}', $this->error);

        parent::RenderPage('admin');
    }
}

?>