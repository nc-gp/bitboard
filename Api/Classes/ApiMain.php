<?php

namespace Api\Classes;

use App\Classes\SessionManager;
use App\Classes\Permissions;
use Api\Classes\ApiResponse;
use Api\Classes\ApiCodes;

use App\Classes\Database;

class ApiMain
{
    private $data;
    private $methods = array('POST');

    private $req_method;
    private $for;

    public function __construct()
    {
        $this->req_method = $_SERVER['REQUEST_METHOD'];
        if(!in_array($this->req_method, $this->methods))
            ApiResponse::Generate(ApiCodes::METHOD_NOT_ALLOWED, ['message' => 'Method not allowed']);

        if(!$this->IsAuthorizated())
            ApiResponse::Generate(ApiCodes::UNAUTHORIZED, ['message' => 'Unauthorized']);

        $this->HandleURL();
        $this->HandleData();

        $className = '\Api\\' . $this->req_method . '\\' . $this->for;

        if(!class_exists($className))
            ApiResponse::Generate(ApiCodes::BAD_REQUEST, ['message' => 'Bad request']);

        require_once './app/config.php';
        $db = new Database($config['host'], $config['user'], $config['pass'], $config['name']);
        $api = new $className($db, $this->data);
        $api->Response();
        $db->Close();
    }

    private function IsAuthorizated()
    {
        return SessionManager::IsLogged() && $_SESSION['bitboard_user']->HasPermission(Permissions::ADMIN_PANEL_ACCESS);
    }

    private function HandleData()
    {
        if($this->req_method === 'POST')
        {
            $this->data = json_decode(file_get_contents('php://input'), true);

            if (json_last_error() !== JSON_ERROR_NONE)
                ApiResponse::Generate(ApiCodes::BAD_REQUEST, ['message' => 'Invalid JSON: ' . json_last_error_msg()]);
        }
    }

    private function HandleURL()
    {
        $api = $_GET['data'] ?? null;

        if(!$api)
            ApiResponse::Generate(ApiCodes::BAD_REQUEST, ['message' => 'Bad request']);

        $parts = explode('/', $api);
        $this->for = ucfirst($parts[1] ?? '');
    }
}

?>