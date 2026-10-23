<?php

namespace Api\Classes;

use App\Classes\SessionManager;
use App\Classes\Permissions;
use Api\Classes\ApiResponse;
use Api\Classes\ApiCodes;

use App\Classes\Database;

class ApiMain
{
    // Explicit route allowlist. Never derive a class name directly from request input.
    private const ROUTES = [
        'POST' => [
            'api/settings' => 'Settings',
        ],
    ];

    private $data;
    private $methods = array('POST');

    private $req_method;
    private $for;

    public function __construct()
    {
        $this->req_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if(!in_array($this->req_method, $this->methods))
            ApiResponse::Generate(ApiCodes::METHOD_NOT_ALLOWED, ['message' => 'Method not allowed']);

        if(!$this->IsAuthorizated())
            ApiResponse::Generate(ApiCodes::UNAUTHORIZED, ['message' => 'Unauthorized']);

        $this->HandleURL();
        $this->HandleData();

        if($this->req_method === 'POST' && !$this->IsCsrfTokenValid())
            ApiResponse::Generate(ApiCodes::FORBIDDEN, ['message' => 'Invalid CSRF token']);

        $className = '\Api\\' . $this->req_method . '\\' . $this->for;

        try
        {
            if(!class_exists($className))
                ApiResponse::Generate(ApiCodes::BAD_REQUEST, ['message' => 'Bad request']);

            require_once './app/config.php';
            $db = new Database($config['host'], $config['user'], $config['pass']);
            $db->SelectDatabase($config['name']);
            $api = new $className($db, $this->data);
            $api->Response();
            $db->Close();
        }
        catch (\Throwable $e)
        {
            error_log('BitBoard API error: ' . $e->getMessage());
            ApiResponse::Generate(ApiCodes::INTERNAL_SERVER_ERROR, ['message' => 'Internal server error']);
        }
    }

    private function IsCsrfTokenValid(): bool
    {
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if(SessionManager::ValidateCsrfToken($headerToken))
            return true;

        $bodyToken = is_array($this->data) ? ($this->data['csrf_token'] ?? '') : '';
        return SessionManager::ValidateCsrfToken($bodyToken);
    }

    private function IsAuthorizated()
    {
        return SessionManager::IsLogged() && $_SESSION['bitboard_user']->HasPermission(Permissions::ADMIN_PANEL_ACCESS);
    }

    private function HandleData()
    {
        if($this->req_method === 'POST')
        {
            $data = json_decode(file_get_contents('php://input'), true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data))
                ApiResponse::Generate(ApiCodes::BAD_REQUEST, ['message' => 'Invalid JSON body']);

            $this->data = $data;
        }
    }

    private function HandleURL()
    {
        $api = $_GET['data'] ?? null;

        if(!is_string($api))
            ApiResponse::Generate(ApiCodes::BAD_REQUEST, ['message' => 'Bad request']);

        // Apache on Windows can normalize "api" to the on-disk "Api" directory,
        // so compare routes case-insensitively.
        $api = strtolower(trim($api, '/'));
        $route = self::ROUTES[$this->req_method][$api] ?? null;

        if($route === null)
            ApiResponse::Generate(ApiCodes::BAD_REQUEST, ['message' => 'Bad request']);

        $this->for = $route;
    }
}

?>