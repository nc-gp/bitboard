<?php

namespace App\Forum\Widgets;

use App\Classes\Template;
use App\Classes\Database;
use App\Forum\Controllers\PrefixController;

/**
 * PrefixWidget represents a widget for displaying information about a forum prefix.
 */
class PrefixWidget
{
    /**
     * @var mixed The HTML template for the prefix information.
     */
    public $template;

    /**
     * Constructs a new PrefixWidget instance.
     *
     * @param Database db        The database connection.
     * @param int      prefixID  The ID of the forum prefix.
     */
    public function __construct(Database $db, int $prefixID)
    {
        $prefix = PrefixController::GetPrefixByID($db, $prefixID);
        $this->template = new Template('prefixes', 'main');
        $this->template->AddEntry('{prefix_class}', $prefix['prefix_class']);
        $this->template->AddEntry('{prefix_name}', $prefix['prefix_name']);
        $this->template->Replace();

        $this->template = $this->template->template;
    }
}

?>