<?php

function loader($className)
{
    $filename = $className . '.php';

    if(file_exists($filename) && is_readable($filename))
    {
        include_once $filename;
        echo '<script>console.log("' . 'loaded ' . $filename . '")</script>';
    }
}

spl_autoload_register('loader');

use App\BitBoard;

$bb = new BitBoard();
$bb->Run();

?>