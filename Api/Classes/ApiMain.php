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
    // Each route also declares the permission required to use it.
    private const ROUTES = [
        'POST' => [
            'api/settings' => [
                'class' => 'Settings',
                'permission' => Permissions::MANAGING_SETTINGS,
            ],
            // Prefix edit/delete requests arrive as api/prefixes/{edit|delete}/{id}.
            // HandleURL() maps those paths to this entry and injects the route parameters.
            'api/prefixes' => [
                'class' => 'Prefixes',
                'permission' => Permissions::MANAGING_SETTINGS,
            ],
        ],
    ];

    private $data;
    private $methods = array('POST');

    private $req_method;
    private $for;

    private int $requiredPermission = 0;
    private array $routeParameters = [];

    public function __construct()
    {
        $this->req_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if(!in_array($this->req_method, $this->methods))
            ApiResponse::Generate(ApiCodes::METHOD_NOT_ALLOWED, ['message' => 'Method not allowed']);

        if(!$this->IsAuthenticated())
            ApiResponse::Generate(ApiCodes::UNAUTHORIZED, ['message' => 'Unauthorized']);

        $this->HandleURL();

        if(!$this->HasRequiredPermission())
            ApiResponse::Generate(ApiCodes::FORBIDDEN, ['message' => 'Forbidden']);

        $this->HandleData();

        // Route parameters are authoritative over values repeated in the JSON body.
        if(!empty($this->routeParameters))
            $this->data = array_merge($this->data, $this->routeParameters);

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

    private function IsAuthenticated(): bool
    {
        return SessionManager::IsLogged()
            && isset($_SESSION['bitboard_user'])
            && is_object($_SESSION['bitboard_user'])
            && method_exists($_SESSION['bitboard_user'], 'HasPermission');
    }

    private function HasRequiredPermission(): bool
    {
        $user = $_SESSION['bitboard_user'];

        if(!$user->HasPermission(Permissions::ADMIN_PANEL_ACCESS))
            return false;

        return $this->requiredPermission === 0 || $user->HasPermission($this->requiredPermission);
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
        {
            // Prefix edits/deletes carry their action and ID in the path.
            $segments = explode('/', $api);

            if(
                count($segments) === 4 &&
                $segments[0] === 'api' &&
                $segments[1] === 'prefixes' &&
                in_array($segments[2], array('edit', 'delete'), true) &&
                ctype_digit($segments[3]) &&
                (int) $segments[3] > 0
            )
            {
                $route = self::ROUTES[$this->req_method]['api/prefixes'] ?? null;
                $this->routeParameters = [
                    'type' => $segments[2],
                    'prefixid' => (int) $segments[3],
                ];
            }
        }

        if($route === null)
            ApiResponse::Generate(ApiCodes::BAD_REQUEST, ['message' => 'Bad request']);

        $this->for = $route['class'];
        $this->requiredPermission = (int) $route['permission'];
    }
}

?>
