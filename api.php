<?php

require_once 'auto_loader.php';

session_start();

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type");

use Api\Classes\ApiMain;
new ApiMain();

?>