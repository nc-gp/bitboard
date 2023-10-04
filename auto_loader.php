<?php

function loader($className)
{
    $filename = $className . '.php';

    if(file_exists($filename) && is_readable($filename))
        include_once $filename;
}

spl_autoload_register('loader');

?>