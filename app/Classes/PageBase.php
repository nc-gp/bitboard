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
    protected string $theme;
    protected string $forumName;
    protected string $forumDesc;
    protected string $serverPath;

    protected array $forumData;

    protected Database $database;

    public function __construct(Database $db, array $forumData)
    {
        $this->theme = $forumData['forum_theme'];
        $this->forumName = $forumData['forum_name'];
        $this->forumDesc = '';
        $this->database = $db;
        $this->serverPath = UrlManager::GetPath();
        $this->forumData = $forumData;
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
        $footerTemplate = new FooterWidget($this->theme);

        $this->template->AddEntry('{head}', $headTemplate->Template->template);
        $this->template->AddEntry('{header}', $headerTemplate->Template->template);
        $this->template->AddEntry('{footer}', $footerTemplate->Template->template);
    }
}

?>