<?php

require_once './app/template/template.php';
require_once './app/forum/widgets/head.php';

class OfflinePage
{
    private $template;
    private string $theme;
    private string $message;
    private string $forumName;

    public function __construct(array $forumData)
    {
        $this->theme = $forumData['forum_theme'];
        $this->message = $forumData['forum_online_msg'];
        $this->forumName = $forumData['forum_name'];
        $this->Do();
    }

    private function Do()
    {
        $stylesTemplate = new Template('./themes/' . $this->theme . '/templates/offline/styles.html');

        $headTemplate = new HeadWidget($this->theme, $this->forumName, $this->message, $stylesTemplate->templ);

        $this->template = new Template('./themes/' . $this->theme . '/templates/offline/main.html');
        $this->template->AddEntry('{head}', $headTemplate->Template->templ);
        $this->template->AddEntry('{reason}', $this->message);
        $this->template->Render(true);
    }
}

?>