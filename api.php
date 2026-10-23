<?php

require_once 'auto_loader.php';

use App\Classes\SessionManager;
use Api\Classes\ApiMain;

SessionManager::Start();

header('Content-Type: application/json');

new ApiMain();

?>