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
        $headTemplate = new HeadWidget($this->forumName, $this->forumDesc, $stylesTemplate->Template->template);
        $headerTemplate = new HeaderWidget();
        $footerTemplate = new FooterWidget();

        /**
         * Here we can add more global variables in HTML.
         * Just remember we actually don't need to.
         */
        $this->template->AddEntry('{head}', $headTemplate->template);
        $this->template->AddEntry('{header}', $headerTemplate->template);
        $this->template->AddEntry('{footer}', $footerTemplate->template);
        $this->template->Addentry('{server_url}', $this->serverPath);
    }
}

?>