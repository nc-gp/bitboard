<?php

namespace App\Forum\Widgets;

use App\Classes\Template;

class NotifyWidget
{
    public $template = '';

    public function __construct($notifyMessage, $rgb = '255, 53, 53', $duration = 5000, $gravity = 'bottom', $position = 'right')
    {
        $this->template = new Template('notify', 'main');
        $this->template->AddEntry('{notify_msg}', $notifyMessage);
        $this->template->AddEntry('{duration}', $duration);
        $this->template->AddEntry('{rgb}', $rgb);
        $this->template->AddEntry('{gravity}', $gravity);
        $this->template->AddEntry('{position}', $position);
        $this->template->Replace();

        $this->template = $this->template->template;
    }
}

?>