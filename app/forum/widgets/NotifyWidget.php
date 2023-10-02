<?php

namespace App\Forum\Widgets;

use App\Classes\Template;

/**
 * NotifyWidget represents a widget for displaying notification messages.
 */
class NotifyWidget
{
    /**
     * @var mixed The HTML template for the notification.
     */
    public $template = '';

    /**
     * Constructs a new NotifyWidget instance.
     *
     * @param string notifyMessage The message to be displayed in the notification.
     * @param string rgb           The RGB color code for the notification background (default: '255, 53, 53').
     * @param int    duration      The duration (in milliseconds) the notification should be displayed (default: 5000).
     * @param string gravity       The gravity of the notification (default: 'bottom').
     * @param string position      The position of the notification (default: 'right').
     */
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