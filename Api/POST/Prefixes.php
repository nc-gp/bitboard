<?php

namespace Api\POST;

use Api\Classes\ApiResponse;
use Api\Classes\ApiCodes;

use App\Classes\Database;

class Settings
{
    private int $code = ApiCodes::OK;
    private string $message = 'Prefix "%s" updated!';

    public function __construct(Database $db, array $data)
    {
        
    }

    public function Response(): void
    {
        ApiResponse::Generate($this->code, ['message' => $this->message]);
    }
}

?>