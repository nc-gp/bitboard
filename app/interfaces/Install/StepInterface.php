<?php

namespace App\Interfaces\Install;

interface StepInterface
{
    public static function Execute();
    public static function Handler();
}

?>