<?php

namespace App\Classes;

use App\Classes\UrlManager;
use App\Classes\Template;

use App\Forum\Widgets\StylesWidget;
use App\Forum\Widgets\HeaderWidget;
use App\Forum\Widgets\HeadWidget;
use App\Forum\Widgets\FooterWidget;

class PageBase
{
    protected Template $template;
    protected string $forumName;
    protected string $forumDesc;
    protected string $serverPath;
    protected string $canonicalUrl = '';
    protected string $ogTitle = '';
    protected string $ogDescription = '';
    protected string $ogType = 'website';
    protected string $ogImage = '';

    protected object $forumData;

    protected Database $database;

    public function __construct(Database $db, object $data)
    {
        $this->forumName = $data->forum_name;
        $this->forumData = $data;
        $this->forumDesc = '';
        $this->database = $db;
        $this->serverPath = UrlManager::GetPath();
    }

    protected function RenderPage(string $templateCategory): void
    {
        $this->CommonSetup($templateCategory);
        $this->template->Render(true);
    }

    private function CommonSetup(string $templateCategory): void
    {
        $stylesTemplate = new StylesWidget($templateCategory);

        $ogTitle = $this->ogTitle !== '' ? $this->ogTitle : $this->forumName;
        $ogDescription = Text::Excerpt(
            $this->ogDescription !== '' ? $this->ogDescription : $this->forumDesc,
            160
        );
        $ogImage = $this->ogImage !== ''
            ? $this->ogImage
            : $this->serverPath . 'themes/default/images/logo.webp';

        $metadata = [
            'description' => $ogDescription,
            'canonical' => $this->canonicalUrl,
            'site_name' => $this->forumName,
            'og_title' => $ogTitle,
            'og_description' => $ogDescription,
            'og_type' => $this->ogType,
            'og_url' => $this->canonicalUrl,
            'og_image' => $ogImage,
            'feed_url' => $this->serverPath . 'feed.xml',
        ];

        $headTemplate = new HeadWidget($this->forumName, $this->forumDesc, $stylesTemplate->Template->template, $metadata);

        $offlineNotice = '';
        if (!$this->forumData->forum_online && SessionManager::IsStaff())
        {
            $offlineNotice = 'Forum is offline. Staff can still browse.';
            $offlineMessage = trim((string) ($this->forumData->forum_online_msg ?? ''));

            if ($offlineMessage !== '')
                $offlineNotice .= ' ' . $offlineMessage;
        }

        $headerTemplate = new HeaderWidget($this->database, $offlineNotice);
        $footerTemplate = new FooterWidget($this->forumData->forum_credits ?? true);

        /**
         * Here we can add more global variables in HTML.
         * Just remember we actually don't need to.
         */
        $this->template->AddEntry('{head}', $headTemplate->template);
        $this->template->AddEntry('{header}', $headerTemplate->template);
        $this->template->AddEntry('{footer}', $footerTemplate->template);
        $this->template->AddEntry('{csrf_token}', SessionManager::CsrfToken());

        // Adding global javascripts inside the page body.
        $globalJS = new Template('footer', 'end');
        $this->template->AddEntry('{scripts}', $globalJS->template);
    }
}

?>