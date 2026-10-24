<?php

namespace App\Classes;

use RuntimeException;

/**
 * Thrown when a route points to a page entity that does not exist.
 */
class PageNotFoundException extends RuntimeException
{
}

?>
