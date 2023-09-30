<?php

namespace App\Forum\Widgets;

use App\Classes\Template;
use App\Classes\Database;
use App\Forum\Controllers\PrefixController;

class PrefixWidget
{
    public $template;
    private array $prefix;

    public function __construct(Database $db, int $prefixID)
    {
        $this->prefix = PrefixController::GetPrefixByID($db, $prefixID);
        $this->Do();
    }

    private function Do()
    {
        $this->template = new Template('prefixes', 'main');
        $this->template->AddEntry('{prefix_class}', $this->prefix['prefix_class']);
        $this->template->AddEntry('{prefix_name}', $this->prefix['prefix_name']);
        $this->template->Replace();

        $this->template = $this->template->template;
    }
}

?>