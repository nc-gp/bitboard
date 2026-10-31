<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\State;
use App\Classes\Template;
use App\Interfaces\PageInterface;

class _500Page extends PageBase implements PageInterface
{
    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->forumDesc = 'Something went wrong.';
    }

    public function Do()
    {
        http_response_code(500);

        $this->template = new Template('500', '500');
        $this->template->AddEntry('{state}', State::Error(
            'Something went wrong',
            'The request could not be completed. The error has been logged. Try again, or head back to the forum.'
        ));

        parent::RenderPage('500');
    }
}
