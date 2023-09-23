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

    protected function RenderPage(string $stylesTemplatePath): void
    {
        $this->CommonSetup($stylesTemplatePath);
        $this->template->Render(true);
    }

    private function CommonSetup(string $stylesTemplatePath): void
    {
        $stylesTemplate = new StylesWidget($this->theme, $stylesTemplatePath);
        $headTemplate = new HeadWidget($this->theme, $this->forumName, $this->forumDesc, $stylesTemplate->Template->templ);
        $headerTemplate = new HeaderWidget($this->theme);
        $footerTemplate = new FooterWidget($this->theme);

        $this->template->AddEntry('{head}', $headTemplate->Template->templ);
        $this->template->AddEntry('{header}', $headerTemplate->Template->templ);
        $this->template->AddEntry('{footer}', $footerTemplate->Template->templ);
    }
}

?>