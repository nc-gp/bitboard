<?php

namespace App\Forum\Pages;

use App\Classes\Console;
use App\Classes\PageBase;
use App\Classes\Database;
use App\Classes\Permissions;
use App\Classes\RelativeTime;
use App\Classes\SessionManager;
use App\Classes\UrlManager;
use App\Interfaces\PageInterface;
use App\Classes\Template;
use App\Forum\Controllers\AccountController;
use App\Forum\Controllers\CategoryController;
use App\Forum\Controllers\ForumController;
use App\Forum\Controllers\RankController;
use App\Forum\Controllers\SubForumController;

use App\Forum\Widgets\NotifyWidget;
use App\Forum\Widgets\PaginationWidget;

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

        if ($option === 'ranks' && !SessionManager::HasPermission(Permissions::MANAGING_ROLES))
            UrlManager::Redirect($this->serverPath . 'admin');

        if ($option === 'users' && !SessionManager::HasPermission(Permissions::MANAGING_USERS))
            UrlManager::Redirect($this->serverPath . 'admin');

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
            case 'ranks':
            {
                if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
                    $this->HandleRankSubmission();

                $this->content = $this->BuildRanks();
                break;
            }
            case 'users':
            {
                if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
                    $this->HandleUserSubmission();

                $this->content = $this->BuildUsers();
                break;
            }
            default:
            {
                $this->content = new Template('admin/main', 'home');
                $this->content->AddEscapedEntry('{username}', $_SESSION['bitboard_user']->username);
                $this->content->AddEscapedEntry('{rankname}', RankController::GetRankNameByID($this->database, $_SESSION['bitboard_user']->rank_id));
                $this->content->AddEntry('{ranks_link}', SessionManager::HasPermission(Permissions::MANAGING_ROLES)
                    ? '<a class="admin-quick-link" href="' . $this->serverPath . 'admin/ranks">'
                        . '<i class="ti ti-shield" aria-hidden="true"></i> Ranks</a>'
                    : '');
                $this->content->AddEntry('{users_link}', SessionManager::HasPermission(Permissions::MANAGING_USERS)
                    ? '<a class="admin-quick-link" href="' . $this->serverPath . 'admin/users">'
                        . '<i class="ti ti-users" aria-hidden="true"></i> Users</a>'
                    : '');
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

    private function ActorAuthority(): array
    {
        $rank = RankController::GetByID($this->database, (int) $_SESSION['bitboard_user']->rank_id);
        $position = (int) ($rank['rank_position'] ?? 0);
        $maximum = RankController::MaxPosition($this->database);

        return [$position, $position >= $maximum];
    }

    private function CanManageRankPosition(int $actorPosition, int $targetPosition, bool $actorIsTop): bool
    {
        return $targetPosition < $actorPosition || ($actorIsTop && $targetPosition <= $actorPosition);
    }

    private function CanManageUserPosition(int $actorPosition, int $targetPosition): bool
    {
        return $targetPosition <= $actorPosition;
    }

    private function PostedPermissions(): int
    {
        $valid = [];
        foreach (Permissions::GROUPS as $permissions)
            foreach (array_keys($permissions) as $permission)
                $valid[$permission] = true;

        $flags = 0;

        if (isset($_POST['permissions']) && is_array($_POST['permissions']))
        {
            foreach ($_POST['permissions'] as $value)
            {
                if (!is_string($value) || !ctype_digit($value))
                    continue;

                $permission = (int) $value;
                if (isset($valid[$permission]))
                    $flags |= $permission;
            }
        }

        return $flags;
    }

    private function BuildPermissionMatrix(int $flags, string $prefix, bool $disabled = false): string
    {
        $html = '<div class="admin-permissions">';
        $disabledAttribute = $disabled ? ' disabled' : '';

        foreach (Permissions::GROUPS as $group => $permissions)
        {
            $html .= '<fieldset class="admin-permission-group">';
            $html .= '<legend>' . Template::Escape($group) . '</legend>';
            $html .= '<div class="admin-permission-list">';

            foreach ($permissions as $permission => $label)
            {
                $id = $prefix . '-' . $permission;
                $checked = (($flags & (int) $permission) !== 0) ? ' checked' : '';

                $html .= '<label class="admin-permission" for="' . Template::Escape($id) . '">'
                    . '<input type="checkbox" id="' . Template::Escape($id) . '" name="permissions[]" value="' . (int) $permission . '"' . $checked . $disabledAttribute . '>'
                    . '<span>' . Template::Escape($label) . '</span>'
                    . '</label>';
            }

            $html .= '</div></fieldset>';
        }

        return $html . '</div>';
    }

    private function HandleRankSubmission(): void
    {
        if (!SessionManager::ValidateCsrfToken($_POST['csrf_token'] ?? ''))
        {
            $this->AdminError('Invalid request. Please try again.');
            return;
        }

        $action = $this->PostedString('action');
        $rankID = $this->PostedID('rank_id');

        if (!in_array($action, ['create', 'edit', 'delete'], true))
        {
            $this->AdminError('Invalid rank action.');
            return;
        }

        try
        {
            [$actorPosition, $actorIsTop] = $this->ActorAuthority();
            $existing = $rankID > 0 ? RankController::GetByID($this->database, $rankID) : [];

            if ($action === 'delete')
            {
                if ($rankID <= 0 || empty($existing))
                {
                    $this->AdminError('That rank could not be found.');
                    return;
                }

                if (!$this->CanManageRankPosition($actorPosition, (int) $existing['rank_position'], $actorIsTop))
                {
                    $this->AdminError('You cannot manage a rank at or above your own.');
                    return;
                }

                if (RankController::IsBuiltIn($rankID) && !$actorIsTop)
                {
                    $this->AdminError('Built-in ranks can only be managed by the top rank.');
                    return;
                }

                if (RankController::IsBuiltIn($rankID))
                {
                    $this->AdminError('Built-in ranks cannot be deleted.');
                    return;
                }

                if (RankController::CountUsers($this->database, $rankID) > 0)
                {
                    $this->AdminError('Move the users in this rank to another rank before deleting it.');
                    return;
                }

                RankController::Delete($this->database, $rankID);
                UrlManager::Redirect($this->serverPath . 'admin/ranks');
            }

            if ($action === 'edit' && empty($existing))
            {
                $this->AdminError('That rank could not be found.');
                return;
            }

            if ($action === 'edit' && !$this->CanManageRankPosition($actorPosition, (int) $existing['rank_position'], $actorIsTop))
            {
                $this->AdminError('You cannot manage a rank at or above your own.');
                return;
            }

            if ($action === 'edit' && RankController::IsBuiltIn($rankID) && !$actorIsTop)
            {
                $this->AdminError('Built-in ranks can only be managed by the top rank.');
                return;
            }

            $builtIn = $action === 'edit' && RankController::IsBuiltIn($rankID);

            $name = $this->PostedString('rank_name');

            // Built-in rank names are fixed: registration and other pages look
            // them up by name.
            if ($builtIn)
                $name = (string) $existing['rank_name'];

            $format = $this->PostedString('rank_format');
            $flags = $this->PostedPermissions();
            $rankPosition = $this->PostedID('rank_position');

            if ($builtIn)
            {
                $rankPosition = (int) $existing['rank_position'];
            }
            else
            {
                if ($rankPosition < 1 || $rankPosition > RankController::MAX_CUSTOM_POSITION)
                {
                    $this->AdminError('Rank position must be between 1 and 99.');
                    return;
                }

                if ($rankPosition >= $actorPosition)
                {
                    $this->AdminError('Rank position must be lower than your own rank.');
                    return;
                }
            }

            if (mb_strlen($name) < 1 || mb_strlen($name) > 64)
            {
                $this->AdminError('Rank name must be between 1 and 64 characters.');
                return;
            }

            if (mb_strlen($format) < 1 || mb_strlen($format) > 256)
            {
                $this->AdminError('Rank format must be between 1 and 256 characters.');
                return;
            }

            $duplicate = RankController::GetByName($this->database, $name);
            if (!empty($duplicate) && (int) $duplicate['id'] !== $rankID)
            {
                $this->AdminError('A rank with that name already exists.');
                return;
            }

            if ($action === 'edit' && $rankID === (int) $_SESSION['bitboard_user']->rank_id)
            {
                $required = Permissions::ADMIN_PANEL_ACCESS | Permissions::MANAGING_ROLES;

                if (($flags & $required) !== $required)
                {
                    $this->AdminError('You cannot remove admin panel or rank management access from your own rank.');
                    return;
                }
            }

            // Keep the built-in Admin rank as a recovery path even when another
            // custom rank also has administrative permissions.
            if ($action === 'edit' && $rankID === 1)
            {
                $required = Permissions::ADMIN_PANEL_ACCESS | Permissions::MANAGING_ROLES;

                if (($flags & $required) !== $required)
                {
                    $this->AdminError('The built-in Admin rank must keep admin panel and rank management access.');
                    return;
                }
            }

            if ($action === 'edit')
                RankController::Update($this->database, $rankID, $name, $format, $flags, $rankPosition);
            else
                RankController::Create($this->database, $name, $format, $flags, $rankPosition);

            UrlManager::Redirect($this->serverPath . 'admin/ranks');
        }
        catch (Throwable $e)
        {
            error_log('BitBoard rank update failed: ' . $e->getMessage());
            $this->AdminError('Unable to save this rank. Please try again.');
        }
    }

    private function BuildRankRow(array $rank, int $actorPosition, bool $actorIsTop): string
    {
        $rankID = (int) $rank['id'];
        $flags = (int) $rank['rank_flags'];
        $position = (int) $rank['rank_position'];
        $builtIn = RankController::IsBuiltIn($rankID);
        $canManage = $this->CanManageRankPosition($actorPosition, $position, $actorIsTop)
            && (!$builtIn || $actorIsTop);

        $permissionCount = 0;
        foreach (Permissions::GROUPS as $permissions)
            foreach ($permissions as $permission => $label)
                if (($flags & (int) $permission) !== 0)
                    $permissionCount++;

        $actions = '';

        if ($canManage)
        {
            $actions .= '<button type="submit" form="edit-rank-' . $rankID . '">Save</button>';

            if (!$builtIn)
                $actions .= '<button type="submit" form="delete-rank-' . $rankID . '" class="danger-button">Delete</button>';
        }

        $row = new Template('admin/main/ranks', 'rank');
        $row->AddEscapedEntry('{rank_id}', $rankID);
        $row->AddEscapedEntry('{rank_name}', $rank['rank_name']);
        $row->AddEscapedEntry('{rank_format}', $rank['rank_format']);
        $row->AddEscapedEntry('{rank_position}', $position);
        $row->AddEscapedEntry('{position_label}', 'Position ' . $position);
        $row->AddEscapedEntry('{max_position}', max(1, min(RankController::MAX_CUSTOM_POSITION, $actorPosition - 1)));
        $row->AddEntry('{disabled}', $canManage ? '' : ' disabled');
        $row->AddEntry('{name_readonly}', $builtIn ? ' readonly' : '');
        $row->AddEntry('{position_readonly}', $builtIn ? ' readonly' : '');
        $row->AddEscapedEntry('{user_label}', $this->CountLabel((int) $rank['user_count'], 'user'));
        $row->AddEscapedEntry('{permission_label}', $this->CountLabel($permissionCount, 'permission'));
        $row->AddEntry('{builtin_badge}', $builtIn ? '<span class="admin-rank-builtin">Built-in</span>' : '');
        $row->AddEntry('{readonly_badge}', $canManage ? '' : '<span class="admin-rank-readonly">Read only</span>');
        $row->AddEntry('{permissions}', $this->BuildPermissionMatrix($flags, 'rank-' . $rankID, !$canManage));
        $row->AddEntry('{actions}', $actions);
        $row->Replace();

        return $row->template;
    }

    private function BuildRanks(): string
    {
        [$actorPosition, $actorIsTop] = $this->ActorAuthority();
        $ranks = RankController::GetAll($this->database);
        $rows = '';

        foreach ($ranks as $rank)
            $rows .= $this->BuildRankRow($rank, $actorPosition, $actorIsTop);

        if ($rows === '')
            $rows = (new Template('admin/main/ranks', 'empty'))->template;

        $maxNewPosition = min(RankController::MAX_CUSTOM_POSITION, $actorPosition - 1);

        if ($maxNewPosition >= 1)
        {
            $create = new Template('admin/main/ranks', 'create');
            $create->AddEscapedEntry('{max_position}', $maxNewPosition);
            $create->AddEscapedEntry('{default_position}', min(10, $maxNewPosition));
            $create->AddEntry('{permissions}', $this->BuildPermissionMatrix(0, 'rank-new'));
            $create->Replace();
            $create = $create->template;
        }
        else
        {
            $create = (new Template('admin/main/ranks', 'no_create'))->template;
        }

        $content = new Template('admin/main', 'ranks');
        $content->AddEscapedEntry('{actor_position}', $actorPosition);
        $content->AddEntry('{create_rank}', $create);
        $content->AddEntry('{ranks}', $rows);
        $content->Replace();

        return $content->template;
    }

    private function WouldRemoveLastAdmin(array $user): bool
    {
        if (((int) $user['rank_flags'] & Permissions::ADMIN_PANEL_ACCESS) === 0)
            return false;

        return AccountController::CountWithAdminAccessExcept($this->database, (int) $user['id']) === 0;
    }

    private function HandleUserSubmission(): void
    {
        if (!SessionManager::ValidateCsrfToken($_POST['csrf_token'] ?? ''))
        {
            $this->AdminError('Invalid request. Please try again.');
            return;
        }

        $action = $this->PostedString('action');
        $userID = $this->PostedID('user_id');

        if (!in_array($action, ['edit', 'ban', 'unban'], true))
        {
            $this->AdminError('Invalid user action.');
            return;
        }

        try
        {
            if ($userID <= 1)
            {
                $this->AdminError('That user could not be found.');
                return;
            }

            $user = AccountController::GetWithRank($this->database, $userID);
            if (empty($user))
            {
                $this->AdminError('That user could not be found.');
                return;
            }

            $currentUserID = (int) $_SESSION['bitboard_user']->id;
            [$actorPosition] = $this->ActorAuthority();

            if (
                $userID !== $currentUserID
                && !$this->CanManageUserPosition($actorPosition, (int) $user['rank_position'])
            )
            {
                $this->AdminError('You cannot manage a user at or above your own rank.');
                return;
            }

            if ($action === 'ban' || $action === 'unban')
            {
                if ($userID === $currentUserID)
                {
                    $this->AdminError('You cannot ban your own account.');
                    return;
                }

                $targetRankName = $action === 'ban' ? 'Banned' : 'User';
                $targetRank = RankController::GetByName($this->database, $targetRankName);

                if (empty($targetRank))
                {
                    $this->AdminError('The ' . $targetRankName . ' rank could not be found.');
                    return;
                }

                if ((int) $targetRank['rank_position'] > $actorPosition)
                {
                    $this->AdminError('You cannot assign a rank higher than your own.');
                    return;
                }

                if ($action === 'ban' && $this->WouldRemoveLastAdmin($user))
                {
                    $this->AdminError('At least one account must keep admin panel access.');
                    return;
                }

                AccountController::SetRank($this->database, $userID, (int) $targetRank['id']);
                UrlManager::Redirect($this->serverPath . 'admin/users');
            }

            $rankID = $this->PostedID('rank_id');
            $rank = $rankID > 0 ? RankController::GetByID($this->database, $rankID) : [];

            if (empty($rank))
            {
                $this->AdminError('Choose a valid rank.');
                return;
            }

            if ((int) $rank['rank_position'] > $actorPosition)
            {
                $this->AdminError('You cannot assign a rank higher than your own.');
                return;
            }

            if ($userID === $currentUserID && $rankID !== (int) $user['rank_id'])
            {
                $this->AdminError('You cannot change your own rank.');
                return;
            }

            if (
                $rankID !== (int) $user['rank_id']
                && ((int) $rank['rank_flags'] & Permissions::ADMIN_PANEL_ACCESS) === 0
                && $this->WouldRemoveLastAdmin($user)
            )
            {
                $this->AdminError('At least one account must keep admin panel access.');
                return;
            }

            $reputationValue = isset($_POST['reputation']) && is_string($_POST['reputation'])
                ? trim($_POST['reputation'])
                : '';

            if ($reputationValue === '' || !preg_match('/^-?\d+$/', $reputationValue))
            {
                $this->AdminError('Reputation must be a whole number.');
                return;
            }

            $reputation = (int) $reputationValue;

            if ($reputation < -999999 || $reputation > 999999)
            {
                $this->AdminError('Reputation must be between -999999 and 999999.');
                return;
            }

            AccountController::SetRank($this->database, $userID, $rankID);
            AccountController::SetReputation($this->database, $userID, $reputation);

            UrlManager::Redirect($this->serverPath . 'admin/users');
        }
        catch (Throwable $e)
        {
            error_log('BitBoard user update failed: ' . $e->getMessage());
            $this->AdminError('Unable to save this user. Please try again.');
        }
    }

    private function BuildRankOptions(array $ranks, int $selectedID, bool $includeAll = false, ?int $maxPosition = null): string
    {
        $options = $includeAll
            ? '<option value="0"' . ($selectedID === 0 ? ' selected' : '') . '>All ranks</option>'
            : '';

        foreach ($ranks as $rank)
        {
            $rankID = (int) $rank['id'];
            $rankPosition = (int) $rank['rank_position'];

            if ($maxPosition !== null && $rankPosition > $maxPosition)
                continue;

            $options .= '<option value="' . $rankID . '"' . ($rankID === $selectedID ? ' selected' : '') . '>'
                . Template::Escape($rank['rank_name']) . ' (position ' . $rankPosition . ')</option>';
        }

        return $options;
    }

    private function BuildUserRow(
        array $user,
        array $ranks,
        int $bannedRankID,
        int $currentUserID,
        int $actorPosition
    ): string
    {
        $userID = (int) $user['id'];
        $isBanned = $bannedRankID > 0 && (int) $user['rank_id'] === $bannedRankID;
        $isSelf = $userID === $currentUserID;
        $canManage = $isSelf || $this->CanManageUserPosition($actorPosition, (int) $user['rank_position']);

        $rankControl = '';
        $reputationDisabled = '';
        $actions = '';
        $badge = '';

        if (!$canManage)
        {
            $rankControl = '<select disabled aria-label="Rank">'
                . $this->BuildRankOptions($ranks, (int) $user['rank_id'])
                . '</select>';
            $reputationDisabled = ' disabled';
            $badge = '<span class="admin-user-readonly">Read only</span>';
        }
        elseif ($isSelf)
        {
            $rankControl = '<input type="hidden" name="rank_id" value="' . $userID . '">'
                . '<p class="admin-user-rank-static">' . Template::Escape($user['rank_name']) . '</p>';
            $actions = '<button type="submit" form="edit-user-' . $userID . '">Save</button>';
            $badge = '<span class="admin-user-self">You</span>';
        }
        else
        {
            $rankControl = '<select name="rank_id">'
                . $this->BuildRankOptions($ranks, (int) $user['rank_id'], false, $actorPosition)
                . '</select>';
            $actions = '<button type="submit" form="edit-user-' . $userID . '">Save</button>'
                . '<button type="submit" form="moderate-user-' . $userID . '" class="'
                . ($isBanned ? '' : 'danger-button') . '">' . ($isBanned ? 'Unban' : 'Ban') . '</button>';
        }

        $row = new Template('admin/main/users', 'user');
        $row->AddEscapedEntry('{user_id}', $userID);
        $row->AddEscapedEntry('{username}', $user['username']);
        $row->AddEscapedEntry('{email}', $user['email']);
        $row->AddEscapedEntry('{rank_name}', $user['rank_name']);
        $row->AddEscapedEntry('{reputation}', $user['reputation']);
        $row->AddEscapedEntry('{post_label}', $this->CountLabel((int) $user['post_count'], 'post'));
        $row->AddEscapedEntry('{thread_label}', $this->CountLabel((int) $user['thread_count'], 'thread'));
        $row->AddEscapedEntry('{joined}', RelativeTime::Format($user['reg_date'], false));
        $row->AddEscapedEntry('{last_active}', RelativeTime::Convert($user['last_active']));
        $row->AddEntry('{rank_control}', $rankControl);
        $row->AddEntry('{reputation_disabled}', $reputationDisabled);
        $row->AddEntry('{actions}', $actions);
        $row->AddEntry('{readonly_badge}', $badge);
        $row->AddEscapedEntry('{moderation_action}', $isBanned ? 'unban' : 'ban');
        $row->Replace();

        return $row->template;
    }

    private function BuildUsers(): string
    {
        $search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
        $search = mb_substr($search, 0, 64);

        $rankFilter = isset($_GET['rank']) && is_string($_GET['rank']) && ctype_digit($_GET['rank'])
            ? (int) $_GET['rank']
            : 0;

        $page = 1;
        $parameters = $this->forumData->actionParameters;

        if (
            ($parameters[2] ?? '') === 'page'
            && isset($parameters[3])
            && is_string($parameters[3])
            && ctype_digit($parameters[3])
        )
            $page = (int) $parameters[3];

        if ($page <= 0)
            $page = 1;

        $ranks = RankController::GetAll($this->database);
        $rankIDs = array_map(static fn(array $rank): int => (int) $rank['id'], $ranks);

        if ($rankFilter > 0 && !in_array($rankFilter, $rankIDs, true))
            $rankFilter = 0;

        $bannedRankID = 0;
        foreach ($ranks as $rank)
            if ($rank['rank_name'] === 'Banned')
                $bannedRankID = (int) $rank['id'];

        [$actorPosition] = $this->ActorAuthority();

        $perPage = 20;
        $total = AccountController::CountAdminUsers($this->database, $search, $rankFilter);
        $totalPages = max(1, (int) ceil($total / $perPage));

        $filterParameters = [];
        if ($search !== '')
            $filterParameters['q'] = $search;
        if ($rankFilter > 0)
            $filterParameters['rank'] = $rankFilter;

        $queryString = count($filterParameters) > 0 ? '?' . http_build_query($filterParameters) : '';

        if ($total > 0 && $page > $totalPages)
            UrlManager::Redirect($this->serverPath . 'admin/users/page/' . $totalPages . $queryString);

        $users = $total > 0
            ? AccountController::GetAdminUsers($this->database, $search, $rankFilter, ($page - 1) * $perPage, $perPage)
            : [];

        $rows = '';
        foreach ($users as $user)
            $rows .= $this->BuildUserRow(
                $user,
                $ranks,
                $bannedRankID,
                (int) $_SESSION['bitboard_user']->id,
                $actorPosition
            );

        if ($rows === '')
            $rows = (new Template('admin/main/users', 'empty'))->template;

        $pagination = '';
        if ($totalPages > 1)
        {
            $widget = new PaginationWidget($page, $total, $perPage, 'admin/users/page/', $filterParameters);
            $pagination = $widget->Template->template;
        }

        $content = new Template('admin/main', 'users');
        $content->AddEscapedEntry('{search}', $search);
        $content->AddEntry('{rank_filter_options}', $this->BuildRankOptions($ranks, $rankFilter, true));
        $content->AddEscapedEntry('{actor_position}', $actorPosition);
        $content->AddEscapedEntry('{total_label}', $this->CountLabel($total, 'member'));
        $content->AddEntry('{pagination}', $pagination);
        $content->AddEntry('{users}', $rows);
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
            'users' => ['Users', 'ti-users', 'admin/users'],
            'ranks' => ['Ranks', 'ti-shield', 'admin/ranks'],
        ];

        if (!SessionManager::HasPermission(Permissions::MANAGING_USERS))
            unset($sections['users']);

        if (!SessionManager::HasPermission(Permissions::MANAGING_ROLES))
            unset($sections['ranks']);

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