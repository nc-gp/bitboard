<?php

require_once './app/template/template.php';

require_once './app/helpers/url_manager.php';
require_once './app/helpers/avatar.php';
require_once './app/helpers/username.php';
require_once './app/helpers/time.php';

require_once './app/forum/widgets/styles.php';
require_once './app/forum/widgets/head.php';
require_once './app/forum/widgets/header.php';
require_once './app/forum/widgets/footer.php';

require_once './app/forum/controllers/account.php';
require_once './app/forum/controllers/category.php';
require_once './app/forum/controllers/forum.php';

class IndexPage
{
    private $template;
    private string $theme;
    private string $forumName;
    private string $forumDesc;
    private $database;

    private $lastAccount;
    private int $totalPosts;
    private int $totalThreads;
    private int $totalUsers;

    private string $cats = '';

    public function __construct($db, array $forumData)
    {
        $this->theme = $forumData['forum_theme'];
        $this->forumName = $forumData['forum_name'];
        $this->forumDesc = $forumData['forum_description'];
        $this->database = $db;
        $this->Do();
    }

    private function FetchLastAccount()
    {
        $this->lastAccount = $this->database->Query('SELECT a.*, r.rank_format 
            FROM bit_accounts AS a 
            JOIN bit_ranks AS r ON a.rank_id = r.id 
            ORDER BY a.reg_date DESC 
            LIMIT 1'
        )->FetchArray();
        $this->lastAccount['avatar'] = Avatar::GetPath($this->theme, $this->lastAccount['avatar']);
        $this->lastAccount['formatted_username'] = Username::Format($this->lastAccount['rank_format'], $this->lastAccount['username']);
    }

    private function FetchStats()
    {
        $this->totalPosts = $this->database->Query('SELECT * FROM bit_posts')->NumRows();
        $this->totalThreads = $this->database->Query('SELECT * FROM bit_threads')->NumRows();
        $this->totalUsers = $this->database->Query('SELECT * FROM bit_accounts')->NumRows();
    }

    // Todo probably to make it faster or optimize it idk about anything when optimizing in php but yeah. that works tho.
    private function FetchCategories()
    {
        $ServerPath = UrlManager::GetPath();

        // Query to select categories
        $categoriesData = $this->database->Query('SELECT id AS category_id, category_name, category_icon, category_desc, category_position
            FROM bit_categories'
        )->FetchAll();

        // Array to hold the final result
        $result = array();
        $categoriesCount = count($categoriesData);

        if ($categoriesCount <= 0)
        {
            $nocategoriesTemplate = new Template('./themes/' . $this->theme . '/templates/index/forum/category_nocategories.html');
            $this->cats = $nocategoriesTemplate->templ;
            return;
        }

        // Loop through categories data
        foreach ($categoriesData as $category) {
            $categoryId = $category['category_id'];
        
            // Query to select forums for the current category
            $forumsData = $this->database->Query('SELECT f.id AS forum_id, f.forum_name, f.forum_icon, f.forum_desc, f.forum_position,
                                                    COUNT(DISTINCT t.id) AS thread_count,
                                                    COUNT(p.id) AS post_count
                                                    FROM bit_forums AS f
                                                    LEFT JOIN bit_threads AS t ON t.forum_id = f.id
                                                    LEFT JOIN bit_posts AS p ON p.thread_id = t.id
                                                    WHERE f.category_id = ?
                                                    GROUP BY f.id
                                                    ORDER BY f.forum_position ASC', $categoryId)->FetchAll();
        
            // Create an array to hold the forums for the current category
            $forums = array();
        
            // Loop through forums data
            foreach ($forumsData as $forum) {
                $forumId = $forum['forum_id'];
        
                // Query to select subforums for the current forum
                $subforumsData = $this->database->Query('SELECT id AS subforum_id, subforum_name, subforum_desc
                                                          FROM bit_subforums
                                                          WHERE forum_id = ?', $forumId)->FetchAll();
        
                // Create an array to hold the subforums for the current forum
                $subforums = array();
        
                // Loop through subforums data
                foreach ($subforumsData as $subforum) {
                    $subforums[] = array(
                        'id' => $subforum['subforum_id'],
                        'name' => $subforum['subforum_name'],
                        'description' => $subforum['subforum_desc'],
                    );
                }
        
                // Add the forum and its associated subforums to the forums array
                $forums[] = array(
                    'id' => $forum['forum_id'],
                    'name' => $forum['forum_name'],
                    'icon' => $forum['forum_icon'],
                    'description' => $forum['forum_desc'],
                    'pos' => $forum['forum_position'],
                    'subforums_count' => count($subforums),
                    'subforums' => $subforums,
                    'thread_count' => $forum['thread_count'],
                    'post_count' => $forum['post_count'],
                );
            }
        
            // Add the category and its associated forums to the result array
            $result[] = array(
                'category_id' => $category['category_id'],
                'category_name' => $category['category_name'],
                'category_icon' => $category['category_icon'],
                'category_desc' => $category['category_desc'],
                'category_pos' => $category['category_position'],
                'forums_count' => count($forums),
                'forums' => $forums,
            );
        }

        foreach($result as $r)
        {
            if($r['forums_count'] <= 0)
                continue;

            $forums = '';

            foreach($r['forums'] as $forum)
            {
                $subforums = '';

                foreach($forum['subforums'] as $subforum)
                {
                    $subforumTemplate = new Template('./themes/' . $this->theme . '/templates/index/forum/subforum.html');
                    $subforumTemplate->AddEntry('{subforum_title}', $subforum['name']);
                    $subforumTemplate->AddEntry('{subforum_id}', $subforum['id']);
                    $subforumTemplate->AddEntry('{server_url}', $ServerPath);
                    $subforumTemplate->Replace();

                    $subforums .= $subforumTemplate->templ;
                }

                $lastPostTemplate = new Template('./themes/' . $this->theme . '/templates/index/forum/forum_nolastpost.html');
                if ($forum['post_count'] > 0)
                {
                    $lastPost = ForumController::GetLastPost($this->database, $forum['id']);

                    $lastPostTemplate = new Template('./themes/' . $this->theme . '/templates/index/forum/forum_lastpost.html');
                    $lastPostTemplate->AddEntry('{user_id}', $lastPost['id']);
                    $lastPostTemplate->AddEntry('{avatar}', Avatar::GetPath($this->theme, $lastPost['avatar']));
                    $lastPostTemplate->AddEntry('{thread_id}', $lastPost['thread_id']);
                    $lastPostTemplate->AddEntry('{thread_title}', $lastPost['thread_title']);
                    $lastPostTemplate->AddEntry('{post_date}', RelativeTime::Convert($lastPost['post_timestamp']));
                    $lastPostTemplate->AddEntry('{username}', Username::Format($lastPost['rank_format'], $lastPost['username']));
                    $lastPostTemplate->AddEntry('{server_url}', $ServerPath);
                    $lastPostTemplate->Replace();
                }

                $forumTemplate = new Template('./themes/' . $this->theme . '/templates/index/forum/forum.html');
                $forumTemplate->AddEntry('{forum_id}', $forum['id']);
                $forumTemplate->AddEntry('{forum_icon}', $forum['icon']);
                $forumTemplate->AddEntry('{forum_title}', $forum['name']);
                $forumTemplate->AddEntry('{forum_description}', $forum['description']);
                $forumTemplate->AddEntry('{forum_posts}', $forum['post_count']);
                $forumTemplate->AddEntry('{forum_threads}', $forum['thread_count']);
                $forumTemplate->AddEntry('{subforums}', $subforums);
                $forumTemplate->AddEntry('{forum_lastpost}', $lastPostTemplate->templ);
                $forumTemplate->AddEntry('{server_url}', $ServerPath);
                $forumTemplate->Replace();

                $forums .= $forumTemplate->templ;
            }

            $categoryTemplate = new Template('./themes/' . $this->theme . '/templates/index/forum/category.html');
            $categoryTemplate->AddEntry('{category_icon}', $r['category_icon']);
            $categoryTemplate->AddEntry('{category_title}', $r['category_name']);
            $categoryTemplate->AddEntry('{category_description}', $r['category_desc']);
            $categoryTemplate->AddEntry('{forums}', $forums);
            $categoryTemplate->Replace();

            $this->cats .= $categoryTemplate->templ;
        }
    }

    private function Do()
    {
        $this->FetchCategories();
        $this->FetchLastAccount();
        $this->FetchStats();

        $stylesTemplate = new StylesWidget($this->theme, '/templates/index/styles.html');

        $headTemplate = new HeadWidget($this->theme, $this->forumName, $this->forumDesc, $stylesTemplate->Template->templ);

        $headerTemplate = new HeaderWidget($this->theme);

        $lastRegistered = new Template('./themes/' . $this->theme . '/templates/index/stats/last_registered.html');
        $lastRegistered->AddEntry('{id}', $this->lastAccount['id']);
        $lastRegistered->AddEntry('{avatar}', $this->lastAccount['avatar']);
        $lastRegistered->AddEntry('{username}', $this->lastAccount['formatted_username']);
        $lastRegistered->AddEntry('{regdate}', RelativeTime::Convert($this->lastAccount['reg_date']));
        $lastRegistered->AddEntry('{server_url}', UrlManager::GetPath());
        $lastRegistered->Replace();

        $statsTemplate = new Template('./themes/' . $this->theme . '/templates/index/stats.html');
        $statsTemplate->AddEntry('{totalPosts}', $this->totalPosts);
        $statsTemplate->AddEntry('{totalThreads}', $this->totalThreads);
        $statsTemplate->AddEntry('{totalUsers}', $this->totalUsers);
        $statsTemplate->AddEntry('{lastRegistered}', $lastRegistered->templ);
        $statsTemplate->Replace();

        $footerTemplate = new FooterWidget($this->theme);

        $this->template = new Template('./themes/' . $this->theme . '/templates/index/index.html');
        $this->template->AddEntry('{head}', $headTemplate->Template->templ);
        $this->template->AddEntry('{header}', $headerTemplate->Template->templ);
        $this->template->AddEntry('{categories}', $this->cats);
        $this->template->AddEntry('{stats}', $statsTemplate->templ);
        $this->template->AddEntry('{footer}', $footerTemplate->Template->templ);
        $this->template->Render(true);
    }
}

?>