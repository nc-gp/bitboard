<?php

require_once 'auto_loader.php';

use App\Classes\SessionManager;
use Api\Classes\ApiMain;

SessionManager::Start();

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type");

new ApiMain();

?>